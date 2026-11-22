<?php
declare(strict_types=1);

namespace Galaxy\Date\Internal;

final class TimezoneDatabaseReport
{

    public static function build(array $info, ?string $available, ?string $failure = null): array
    {

        $status = 'unknown';
        $reason = $failure;
        if ($failure === null) {

            if ($available === null || !self::isVersion($available)) {
                $reason = 'invalid_response';
            }
            elseif (!self::isVersion($info['installedVersion'])) {
                $reason = 'installed_version_unknown';
            }
            else {
                $status = version_compare($available, $info['installedVersion'], '>') ? 'update_available' : 'up_to_date';
            }

        }
        return $info + [
            'availableVersion' => $available !== null && self::isVersion($available) ? $available : null,
            'status' => $status,
            'reason' => $reason,
            'checkedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'source' => 'https://pecl.php.net/rest/r/timezonedb/stable.txt',
        ];

    }

    private static function isVersion(string $version): bool
    {
        return preg_match('/\\A[0-9]{4}\\.[0-9]+(?:\\.[0-9]+)?\\z/D', $version) === 1;
    }

}