<?php
declare(strict_types=1);

namespace Galaxy\Date\Tests;

use Galaxy\Date\Diagnostics\TimezoneDatabase;
use Galaxy\Date\Internal\TimezoneDatabaseReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimezoneDatabaseTest extends TestCase
{

    public function testInfoOnlyDescribesLocalRuntime(): void
    {

        $before = date_default_timezone_get();
        $info = TimezoneDatabase::info();
        self::assertSame(timezone_version_get(), $info['installedVersion']);
        self::assertSame(PHP_VERSION, $info['phpVersion']);
        self::assertSame($before, $info['defaultTimezone']);
        self::assertContains($info['databaseSource'], ['system', 'extension', 'bundled']);
        self::assertSame($before, date_default_timezone_get());

    }

    public static function comparisons(): iterable
    {

        yield ['2026.5', '2026.6', null, 'update_available', null, '2026.6'];
        yield ['2026.6', '2026.6', null, 'up_to_date', null, '2026.6'];
        yield ['2026.7', '2026.6', null, 'up_to_date', null, '2026.6'];
        yield ['2026.9', '2026.10', null, 'update_available', null, '2026.10'];
        yield ['2025.2', '2025.2.2', null, 'update_available', null, '2025.2.2'];
        yield ['0.system', '2026.6', null, 'unknown', 'installed_version_unknown', '2026.6'];
        yield ['unknown', '2026.6', null, 'unknown', 'installed_version_unknown', '2026.6'];
        yield ['2026.5', null, 'network_unavailable', 'unknown', 'network_unavailable', null];
        foreach ([null, '', '<html>Error</html>', '2026a', '2026.6beta', '2026.6\nother'] as $version) {
            yield ['2026.5', $version, null, 'unknown', 'invalid_response', null];
        }

    }

    #[DataProvider('comparisons')]
    public function testComparison(
        string $installed,
        ?string $available,
        ?string $failure,
        string $status,
        ?string $reason,
        ?string $expectedAvailable,
    ): void {

        $report = TimezoneDatabaseReport::build(['installedVersion' => $installed], $available, $failure);
        self::assertSame($installed, $report['installedVersion']);
        self::assertSame($expectedAvailable, $report['availableVersion']);
        self::assertSame($status, $report['status']);
        self::assertSame($reason, $report['reason']);
        self::assertSame('https://pecl.php.net/rest/r/timezonedb/stable.txt', $report['source']);
        self::assertSame('+00:00', (new \DateTimeImmutable($report['checkedAt']))->format('P'));

    }

    public static function invalidTimeouts(): iterable
    {

        foreach ([0.0, -1.0, 31.0, INF, NAN] as $timeout) {
            yield [$timeout];
        }

    }

    #[DataProvider('invalidTimeouts')]
    public function testInvalidTimeout(float $timeout): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TimezoneDatabase::checkForUpdates($timeout);
    }

}