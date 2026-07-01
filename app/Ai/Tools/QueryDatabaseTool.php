<?php

namespace App\Ai\Tools;

use App\Services\Ai\DbSchemaService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class QueryDatabaseTool implements Tool
{
    private const REPAIR_GUIDANCE = [
        'Retry once with a corrected SELECT-only query.',
        'Use only allowed tables from the schema.',
        'Use canonical joins from the relationship map.',
        'Include the required main_contractor_id scope.',
        'Keep one statement only and include a LIMIT.',
        'If the corrected query still fails, explain the failure instead of retrying again.',
    ];

    private const FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'REPLACE', 'EXEC', 'EXECUTE', 'CALL',
    ];

    private const PROJECT_SCOPED_TABLES = [
        'assignments',
        'sites',
        'projects',
        'assignment_survey_data',
        'assignment_construction_data',
        'assignment_bast_data',
        'assignment_pln_data',
        'reports',
        'report_assignments',
        'assignment_audit_logs',
        'clients',
    ];

    private const SUBCONTRACTOR_SCOPED_TABLES = [
        'subcontractors',
        'main_contractor_subcontractor',
    ];

    private const USER_SCOPED_TABLES = [
        'users',
    ];

    /** @param array{mode: string, max_rows: int} $preferences */
    public function __construct(
        private readonly array $preferences,
        private readonly ToolResultBag $bag,
        private readonly int $mainContractorId,
    ) {}

    public function name(): string
    {
        return 'query_database';
    }

    public function description(): string
    {
        return 'Run a read-only SELECT query against the NexPM database. Use this to answer any question that requires looking up specific data — counts, lists, filters, aggregations. Always write valid MySQL SELECT syntax.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()
                ->description('A valid MySQL SELECT query. Must start with SELECT (or WITH ... SELECT). No INSERT, UPDATE, DELETE, or DDL statements.'),
        ];
    }

    public function handle(Request $request): string
    {
        $sql = rtrim(trim((string) ($request['sql'] ?? '')), ';');

        if ($sql === '') {
            return $this->errorResponse('No SQL provided.');
        }

        $validationError = $this->validateSql($sql);

        if ($validationError !== null) {
            return $this->errorResponse($validationError, ['sql' => $sql]);
        }

        // Enforce row limit
        $maxRows = (int) ($this->preferences['max_rows'] ?? 500);
        if (! preg_match('/\bLIMIT\b/i', $sql)) {
            $sql .= " LIMIT {$maxRows}";
        }

        try {
            $results = DB::select($sql);
            $rowCount = count($results);

            $payload = [
                'sql' => $sql,
                'rows_returned' => $rowCount,
                'rows' => array_map(fn ($row) => (array) $row, $results),
            ];

            $this->bag->toolName = $this->name();
            $this->bag->toolPayload = ['sql' => $sql, 'rows_returned' => $rowCount];

            return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return $this->errorResponse('Query failed: '.$e->getMessage(), ['sql' => $sql]);
        }
    }

    /** @param array<string, mixed> $extra */
    private function errorResponse(string $error, array $extra = []): string
    {
        $payload = [
            'error' => $error,
            'repairable' => true,
            'repair_guidance' => self::REPAIR_GUIDANCE,
        ] + $extra;
        $this->bag->toolName = $this->name();
        $this->bag->toolPayload = $payload;

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function validateSql(string $sql): ?string
    {
        if (preg_match('/;\s*\S/', $sql)) {
            return 'Only a single SELECT statement is allowed.';
        }

        if (preg_match('/(--|#|\/\*)/', $sql)) {
            return 'SQL comments are not allowed.';
        }

        if (! preg_match('/^\s*(?:WITH\b[\s\S]+?\)\s*)?SELECT\s+/si', $sql)) {
            return 'Only SELECT queries are allowed.';
        }

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/i', $sql)) {
                return "Forbidden keyword in query: {$keyword}";
            }
        }

        $aliasesByTable = $this->aliasesByTable($sql);

        foreach (array_keys($aliasesByTable) as $table) {
            if (! in_array($table, DbSchemaService::ALLOWED_TABLES, true)) {
                return "Table '{$table}' is not in the allowed list.";
            }
        }

        if ($this->referencesAny($aliasesByTable, self::PROJECT_SCOPED_TABLES) && ! $this->hasProjectScope($sql, $aliasesByTable)) {
            return "Queries for project/site/assignment/report data must scope to main_contractor_id {$this->mainContractorId}.";
        }

        if ($this->referencesAny($aliasesByTable, self::SUBCONTRACTOR_SCOPED_TABLES) && ! $this->hasSubcontractorScope($sql, $aliasesByTable)) {
            return "Queries for subcontractor data must scope through main_contractor_subcontractor.main_contractor_id {$this->mainContractorId} or a scoped project join.";
        }

        if ($this->referencesAny($aliasesByTable, self::USER_SCOPED_TABLES) && ! $this->hasUserScope($sql, $aliasesByTable)) {
            return "Queries for user data must scope to users.main_contractor_id {$this->mainContractorId} or a scoped subcontractor join.";
        }

        if (isset($aliasesByTable['main_contractors']) && ! $this->hasAnyMainContractorScope($sql, $aliasesByTable)) {
            return "Queries for main contractors must scope to main_contractors.id {$this->mainContractorId}.";
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function aliasesByTable(string $sql): array
    {
        preg_match_all(
            '/\b(?:FROM|JOIN)\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?(?:\s+(?:AS\s+)?`?([a-zA-Z_][a-zA-Z0-9_]*)`?)?/i',
            $sql,
            $matches,
            PREG_SET_ORDER,
        );

        $aliases = [];

        foreach ($matches as $match) {
            $table = strtolower($match[1]);
            $alias = strtolower($match[2] ?? $table);

            if (in_array($alias, ['on', 'where', 'join', 'left', 'right', 'inner', 'outer', 'cross', 'group', 'order', 'limit'], true)) {
                $alias = $table;
            }

            $aliases[$table] ??= [];
            $aliases[$table][] = $table;
            $aliases[$table][] = $alias;
            $aliases[$table] = array_values(array_unique($aliases[$table]));
        }

        return $aliases;
    }

    /** @param array<string, list<string>> $aliasesByTable */
    private function referencesAny(array $aliasesByTable, array $tables): bool
    {
        return collect($tables)->contains(fn (string $table): bool => isset($aliasesByTable[$table]));
    }

    /** @param array<string, list<string>> $aliasesByTable */
    private function hasProjectScope(string $sql, array $aliasesByTable): bool
    {
        return $this->hasColumnEquals($sql, $this->aliasesFor($aliasesByTable, ['projects']), 'main_contractor_id')
            || $this->hasColumnEquals($sql, $this->aliasesFor($aliasesByTable, ['main_contractors']), 'id');
    }

    /** @param array<string, list<string>> $aliasesByTable */
    private function hasSubcontractorScope(string $sql, array $aliasesByTable): bool
    {
        return $this->hasColumnEquals($sql, $this->aliasesFor($aliasesByTable, ['main_contractor_subcontractor']), 'main_contractor_id')
            || $this->hasProjectScope($sql, $aliasesByTable);
    }

    /** @param array<string, list<string>> $aliasesByTable */
    private function hasUserScope(string $sql, array $aliasesByTable): bool
    {
        return $this->hasColumnEquals($sql, $this->aliasesFor($aliasesByTable, ['users']), 'main_contractor_id')
            || $this->hasSubcontractorScope($sql, $aliasesByTable);
    }

    /** @param array<string, list<string>> $aliasesByTable */
    private function hasMainContractorScope(string $sql, array $aliasesByTable): bool
    {
        return $this->hasColumnEquals($sql, $this->aliasesFor($aliasesByTable, ['main_contractors']), 'id');
    }

    /** @param array<string, list<string>> $aliasesByTable */
    private function hasAnyMainContractorScope(string $sql, array $aliasesByTable): bool
    {
        return $this->hasMainContractorScope($sql, $aliasesByTable)
            || $this->hasProjectScope($sql, $aliasesByTable)
            || $this->hasSubcontractorScope($sql, $aliasesByTable)
            || $this->hasUserScope($sql, $aliasesByTable);
    }

    /**
     * @param  array<string, list<string>>  $aliasesByTable
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function aliasesFor(array $aliasesByTable, array $tables): array
    {
        return collect($tables)
            ->flatMap(fn (string $table): array => $aliasesByTable[$table] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<string> $aliases */
    private function hasColumnEquals(string $sql, array $aliases, string $column): bool
    {
        foreach ($aliases as $alias) {
            $quotedAlias = preg_quote($alias, '/');
            $quotedColumn = preg_quote($column, '/');

            if (preg_match('/`?'.$quotedAlias.'`?\.`?'.$quotedColumn.'`?\s*=\s*'.$this->mainContractorId.'\b/i', $sql)) {
                return true;
            }

            if (preg_match('/\b'.$this->mainContractorId.'\s*=\s*`?'.$quotedAlias.'`?\.`?'.$quotedColumn.'`?/i', $sql)) {
                return true;
            }
        }

        return false;
    }
}
