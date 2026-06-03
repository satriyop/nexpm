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
        'share.google',
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
            $originalGoogleMapUrl = (string) $site->google_map_url;
            $googleMapUrl = $this->normalizeGoogleMapUrl($originalGoogleMapUrl);

            if (! $this->isAllowedUrl($googleMapUrl)) {
                $skipped++;
                $this->warn("Skipping non-Google Maps URL for {$site->site_code}: {$originalGoogleMapUrl}");

                continue;
            }

            $coordinates = GoogleMapsCoordinates::fromUrl($googleMapUrl);
            $source = 'direct';

            if ($coordinates === null) {
                $coordinates = $this->resolveRemoteCoordinates($googleMapUrl, $timeout);
                $source = 'redirect';
            }

            if ($coordinates === null) {
                $failed++;
                $this->warn("Unable to resolve coordinates for {$site->site_code}: {$originalGoogleMapUrl}");
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

    private function normalizeGoogleMapUrl(string $url): string
    {
        $normalizedUrl = preg_replace('/\s+/', '', trim($url)) ?? trim($url);
        $host = parse_url($normalizedUrl, PHP_URL_HOST);

        if (mb_strtolower((string) $host) !== 'maps.app.goo.gl') {
            return $normalizedUrl;
        }

        $query = parse_url($normalizedUrl, PHP_URL_QUERY);

        if (! is_string($query) || $query === '' || str_contains($query, '=')) {
            return $normalizedUrl;
        }

        $scheme = parse_url($normalizedUrl, PHP_URL_SCHEME) ?: 'https';
        $path = parse_url($normalizedUrl, PHP_URL_PATH);
        $shortCode = ltrim((string) $path, '/').$query;

        return "{$scheme}://{$host}/{$shortCode}";
    }

    /**
     * @return array{latitude: string, longitude: string}|null
     */
    private function resolveRemoteCoordinates(string $url, int $timeout): ?array
    {
        $currentUrl = $this->normalizeGoogleMapUrl($url);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            if (! $this->isAllowedUrl($currentUrl)) {
                return null;
            }

            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout(min(5, $timeout))
                    ->withUserAgent($this->userAgent())
                    ->accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                    ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
                    ->withOptions(['allow_redirects' => false])
                    ->get($currentUrl);
            } catch (Throwable) {
                return null;
            }

            $location = $response->header('Location');

            if (! is_string($location) || $location === '') {
                $coordinates = GoogleMapsCoordinates::fromText($response->body());

                if ($coordinates !== null) {
                    return $coordinates;
                }

                return $this->resolveLookupCoordinates($currentUrl, $response->body(), $timeout);
            }

            $currentUrl = $this->absoluteUrl($location, $currentUrl);

            $coordinates = GoogleMapsCoordinates::fromUrl($currentUrl);

            if ($coordinates !== null) {
                return $coordinates;
            }
        }

        return null;
    }

    /**
     * @return array{latitude: string, longitude: string}|null
     */
    private function resolveLookupCoordinates(string $url, ?string $body, int $timeout): ?array
    {
        foreach ($this->googleMapsLookupUrls($url, $body) as $lookupUrl) {
            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout(min(5, $timeout))
                    ->withUserAgent($this->userAgent())
                    ->accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                    ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
                    ->withOptions(['allow_redirects' => false])
                    ->get($lookupUrl);
            } catch (Throwable) {
                continue;
            }

            $location = $response->header('Location');

            if (is_string($location) && $location !== '') {
                $coordinates = GoogleMapsCoordinates::fromUrl($this->absoluteUrl($location, $lookupUrl));

                if ($coordinates !== null) {
                    return $coordinates;
                }
            }

            $coordinates = GoogleMapsCoordinates::fromText($response->body());

            if ($coordinates !== null) {
                return $coordinates;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function googleMapsLookupUrls(string $url, ?string $body): array
    {
        $lookupUrls = [];
        $searchText = urldecode($url."\n".($body ?? ''));

        if (preg_match_all('/0x[0-9a-f]+:0x([0-9a-f]+)/i', $searchText, $matches)) {
            foreach ($matches[1] as $hexPlaceId) {
                $lookupUrls[] = 'https://www.google.com/maps?cid='.$this->hexToUnsignedDecimal($hexPlaceId);
            }
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path) && preg_match('#/maps/place/([^/@?]+)#', $path, $matches) === 1) {
            $placeQuery = str_replace('+', ' ', rawurldecode($matches[1]));

            if ($placeQuery !== '') {
                $lookupUrls[] = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($placeQuery);
            }
        }

        return array_values(array_unique($lookupUrls));
    }

    private function hexToUnsignedDecimal(string $hex): string
    {
        $decimal = '0';

        foreach (str_split(mb_strtolower($hex)) as $character) {
            $value = hexdec($character);
            $decimal = $this->decimalMultiply($decimal, 16);
            $decimal = $this->decimalAdd($decimal, $value);
        }

        return ltrim($decimal, '0') ?: '0';
    }

    private function decimalMultiply(string $decimal, int $multiplier): string
    {
        $carry = 0;
        $result = '';

        for ($index = strlen($decimal) - 1; $index >= 0; $index--) {
            $product = ((int) $decimal[$index] * $multiplier) + $carry;
            $result = (string) ($product % 10).$result;
            $carry = intdiv($product, 10);
        }

        while ($carry > 0) {
            $result = (string) ($carry % 10).$result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private function decimalAdd(string $decimal, int $addend): string
    {
        $carry = $addend;
        $result = '';

        for ($index = strlen($decimal) - 1; $index >= 0; $index--) {
            $sum = ((int) $decimal[$index]) + $carry;
            $result = (string) ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        while ($carry > 0) {
            $result = (string) ($carry % 10).$result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private function userAgent(): string
    {
        return 'Mozilla/5.0 (compatible; NexPMCoordinateBackfill/1.0; +https://nexpm.id)';
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
