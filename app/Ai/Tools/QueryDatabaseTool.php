<?php

namespace App\Ai\Tools;

use App\Services\Ai\DbSchemaService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class QueryDatabaseTool implements Tool
{
    private const FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'REPLACE', 'EXEC', 'EXECUTE', 'CALL',
    ];

    /** @param array{mode: string, max_rows: int} $preferences */
    public function __construct(
        private readonly array $preferences,
        private readonly ToolResultBag $bag,
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
        $sql = trim((string) ($request['sql'] ?? ''));

        if ($sql === '') {
            return (string) json_encode(['error' => 'No SQL provided.']);
        }

        // Must be a SELECT query (optionally preceded by a WITH CTE clause)
        if (! preg_match('/^\s*(?:WITH\b[\s\S]+?\)\s*)?SELECT\s+/si', $sql)) {
            return (string) json_encode(['error' => 'Only SELECT queries are allowed.']);
        }

        // Reject any write/DDL keywords
        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/i', $sql)) {
                return (string) json_encode(['error' => "Forbidden keyword in query: {$keyword}"]);
            }
        }

        // Reject queries referencing tables outside the allowlist
        preg_match_all('/\bFROM\s+`?(\w+)`?|\bJOIN\s+`?(\w+)`?/i', $sql, $matches);
        $referencedTables = array_filter(array_merge($matches[1], $matches[2]));
        foreach ($referencedTables as $table) {
            if (! in_array(strtolower($table), DbSchemaService::ALLOWED_TABLES, true)) {
                return (string) json_encode(['error' => "Table '{$table}' is not in the allowed list."]);
            }
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
            return (string) json_encode(['error' => 'Query failed: '.$e->getMessage()]);
        }
    }
}
