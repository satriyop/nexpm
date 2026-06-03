<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Support\GoogleMapsCoordinates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class BackfillSiteCoordinatesFromGoogleMaps extends Command
{
    protected $signature = 'sites:backfill-coordinates-from-google-maps
                            {--limit=50 : Maximum number of sites to inspect in this run}
                            {--sleep=250 : Milliseconds to sleep between outbound requests}
                            {--timeout=10 : HTTP timeout in seconds}
                            {--dry-run : Preview updates without saving}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Backfill missing site latitude/longitude by parsing Google Maps URLs and resolving short links.';

    /**
     * Hosts this command is allowed to request while resolving redirects.
     *
     * @var list<string>
     */
    private const ALLOWED_HOSTS = [
        'maps.app.goo.gl',
        'goo.gl',
        'maps.google.com',
        'www.google.com',
        'google.com',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $sleepMilliseconds = max(0, (int) $this->option('sleep'));
        $timeout = max(1, (int) $this->option('timeout'));
        $isDryRun = (bool) $this->option('dry-run');

        $sites = Site::query()
            ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
            ->whereNotNull('google_map_url')
            ->where('google_map_url', '!=', '')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'site_code', 'location_name', 'google_map_url', 'latitude', 'longitude']);

        if ($sites->isEmpty()) {
            $this->info('No sites with missing coordinates and Google Maps URLs were found.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '%sInspecting %d site(s) with missing coordinates.',
            $isDryRun ? '[DRY RUN] ' : '',
            $sites->count(),
        ));

        if (! $isDryRun && ! $this->option('force')) {
            if (! $this->confirm('This will update site latitude/longitude values. Continue?', false)) {
                $this->info('Aborted.');

                return self::FAILURE;
            }
        }

        $updated = 0;
        $resolved = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($sites as $site) {
            $googleMapUrl = (string) $site->google_map_url;

            if (! $this->isAllowedUrl($googleMapUrl)) {
                $skipped++;
                $this->warn("Skipping non-Google Maps URL for {$site->site_code}: {$googleMapUrl}");

                continue;
            }

            $coordinates = GoogleMapsCoordinates::fromUrl($googleMapUrl);
            $source = 'direct';

            if ($coordinates === null) {
                $finalUrl = $this->resolveFinalUrl($googleMapUrl, $timeout);

                if ($finalUrl !== null) {
                    $coordinates = GoogleMapsCoordinates::fromUrl($finalUrl);
                    $source = 'redirect';
                }
            }

            if ($coordinates === null) {
                $failed++;
                $this->warn("Unable to resolve coordinates for {$site->site_code}: {$googleMapUrl}");
                $this->sleep($sleepMilliseconds);

                continue;
            }

            $resolved++;
            $this->line(sprintf(
                '%s %s => %s, %s (%s)',
                $isDryRun ? 'Would update' : 'Updating',
                $site->site_code,
                $coordinates['latitude'],
                $coordinates['longitude'],
                $source,
            ));

            if ($isDryRun) {
                $skipped++;
                $this->sleep($sleepMilliseconds);

                continue;
            }

            $site->forceFill([
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
            ])->save();

            $updated++;
            $this->sleep($sleepMilliseconds);
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. inspected=%d resolved=%d updated=%d dry_run=%d failed=%d skipped=%d',
            $sites->count(),
            $resolved,
            $updated,
            $isDryRun ? 1 : 0,
            $failed,
            $skipped,
        ));

        return self::SUCCESS;
    }

    private function resolveFinalUrl(string $url, int $timeout): ?string
    {
        $currentUrl = trim($url);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            if (! $this->isAllowedUrl($currentUrl)) {
                return null;
            }

            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout(min(5, $timeout))
                    ->withOptions(['allow_redirects' => false])
                    ->get($currentUrl);
            } catch (Throwable) {
                return null;
            }

            $location = $response->header('Location');

            if (! is_string($location) || $location === '') {
                return $currentUrl;
            }

            $currentUrl = $this->absoluteUrl($location, $currentUrl);

            if (GoogleMapsCoordinates::fromUrl($currentUrl) !== null) {
                return $currentUrl;
            }
        }

        return null;
    }

    private function isAllowedUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return false;
        }

        return in_array(mb_strtolower($host), self::ALLOWED_HOSTS, true);
    }

    private function absoluteUrl(string $location, string $baseUrl): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host)) {
            return $location;
        }

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$location}";
        }

        return "{$scheme}://{$host}/{$location}";
    }

    private function sleep(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
