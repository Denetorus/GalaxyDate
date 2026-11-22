<?php
declare(strict_types=1);

namespace Galaxy\Date\Diagnostics;

use Galaxy\Date\Internal\TimezoneDatabaseReport;

final class TimezoneDatabase
{

    public static function info(): array
    {

        $version = timezone_version_get();
        return [
            'installedVersion' => $version,
            'databaseSource' => $version === '0.system' ? 'system'
                : (extension_loaded('timezonedb') ? 'extension' : 'bundled'),
            'phpVersion' => PHP_VERSION,
            'defaultTimezone' => date_default_timezone_get(),
        ];

    }

    public static function checkForUpdates(float $timeoutSeconds = 5.0): array
    {

        if (!is_finite($timeoutSeconds) || $timeoutSeconds <= 0 || $timeoutSeconds > 30) {
            throw new \InvalidArgumentException('Timeout must be finite, greater than zero and at most 30 seconds.');
        }
        $info = self::info();
        $context = stream_context_create([
            'http' => [
                'method' => 'GET', 'timeout' => $timeoutSeconds, 'follow_location' => 0,
                'ignore_errors' => false,
                'header' => "Accept: text/plain\r\nConnection: close\r\nUser-Agent: GalaxyDate\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        try {
            $stream = @fopen('https://pecl.php.net/rest/r/timezonedb/stable.txt', 'rb', false, $context);
        }
        catch (\Throwable) {
            return TimezoneDatabaseReport::build($info, null, 'network_unavailable');
        }
        if ($stream === false) {
            return TimezoneDatabaseReport::build($info, null, 'network_unavailable');
        }
        try {

            $metadata = stream_get_meta_data($stream);
            $headers = $metadata['wrapper_data'] ?? [];
            if (!is_array($headers) || !preg_match('/\\AHTTP\\/\\S+ 200(?: |$)/', $headers[0] ?? '')) {
                return TimezoneDatabaseReport::build($info, null, 'network_unavailable');
            }
            $body = @stream_get_contents($stream, 129);
            $metadata = stream_get_meta_data($stream);
            if ($body === false || ($metadata['timed_out'] ?? false)) {
                return TimezoneDatabaseReport::build($info, null, 'network_unavailable');
            }
            if (strlen($body) > 128) {
                return TimezoneDatabaseReport::build($info, null, 'invalid_response');
            }
            return TimezoneDatabaseReport::build($info, trim($body));

        }
        catch (\Throwable) {
            return TimezoneDatabaseReport::build($info, null, 'network_unavailable');
        }
        finally {
            fclose($stream);
        }

    }

}