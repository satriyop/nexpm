<?php

namespace App\Support;

class GoogleMapsCoordinates
{
    /**
     * @return array{latitude: string, longitude: string}|null
     */
    public static function fromUrl(?string $url): ?array
    {
        if (blank($url)) {
            return null;
        }

        $decodedUrl = urldecode(trim($url));

        if (preg_match('/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/', $decodedUrl, $matches) === 1) {
            return self::validPair($matches[1], $matches[2]);
        }

        if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $decodedUrl, $matches) === 1) {
            return self::validPair($matches[1], $matches[2]);
        }

        $query = parse_url($decodedUrl, PHP_URL_QUERY);

        if (! is_string($query)) {
            return null;
        }

        parse_str($query, $parameters);

        foreach (['q', 'll', 'query'] as $key) {
            if (! isset($parameters[$key]) || ! is_string($parameters[$key])) {
                continue;
            }

            if (preg_match('/(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/', $parameters[$key], $matches) === 1) {
                return self::validPair($matches[1], $matches[2]);
            }
        }

        return null;
    }

    /**
     * @return array{latitude: string, longitude: string}|null
     */
    private static function validPair(string $latitude, string $longitude): ?array
    {
        $lat = (float) $latitude;
        $lng = (float) $longitude;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }
}
