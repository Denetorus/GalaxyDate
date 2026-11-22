<?php
declare(strict_types=1);

namespace Galaxy\Date\Tests;

use Galaxy\Date\GDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConversionTest extends TestCase
{

    public function testDateFormatting(): void
    {

        self::assertSame(
            '29.02.2024',
            GDate::formatDate('2024-02-29', ['format' => 'Y-m-d'], 'd.m.Y')->value(),
        );
        self::assertSame('invalid_date', GDate::formatDate('2026-02-30', ['format' => 'Y-m-d'], 'd.m.Y')->reason);
        self::assertSame('type_mismatch', GDate::formatDate(null, ['format' => 'Y-m-d'], 'd.m.Y')->reason);

    }

    public function testLocalFormattingDoesNotInventTimezone(): void
    {

        $source = ['format' => 'Y-m-d H:i:s.u'];
        self::assertSame(
            '22.11.2026 14:30:01.123456',
            GDate::formatDateTime('2026-11-22 14:30:01.123456', $source, 'd.m.Y H:i:s.u')->value(),
        );
        self::assertSame(
            'timezone_required',
            GDate::formatDateTime('2026-11-22 14:30:01.123456', $source, 'Y-m-d H:i:sP')->reason,
        );
        self::assertSame(
            'timezone_required',
            GDate::convertTimezone('2026-11-22 14:30:01.123456', $source, [
                'format' => 'Y-m-d H:i:sP', 'timezone' => 'UTC',
            ])->reason,
        );

    }

    public static function conversions(): iterable
    {

        $format = 'Y-m-d H:i:s';
        yield ['2024-07-01 12:00:00', ['format' => $format, 'timezone' => 'Europe/Berlin'],
            ['format' => $format . 'P', 'timezone' => 'UTC'], '2024-07-01 10:00:00+00:00'];
        yield ['2024-01-01 12:00:00', ['format' => $format, 'timezone' => 'Europe/Berlin'],
            ['format' => $format . 'P', 'timezone' => 'UTC'], '2024-01-01 11:00:00+00:00'];
        yield ['2024-01-01 00:15:00', ['format' => $format, 'offset' => '+03:00'],
            ['format' => $format . 'P', 'offset' => '-03:30'], '2023-12-31 17:45:00-03:30'];
        yield ['2024-01-01 00:00:00+05:45', ['format' => $format . 'P'],
            ['format' => $format . 'P', 'timezone' => 'UTC'], '2023-12-31 18:15:00+00:00'];
        yield ['2024-03-31 00:30:00', ['format' => $format, 'timezone' => 'UTC'],
            ['format' => $format . 'P', 'timezone' => 'Europe/Berlin'], '2024-03-31 01:30:00+01:00'];
        yield ['2024-03-31 01:30:00', ['format' => $format, 'timezone' => 'UTC'],
            ['format' => $format . 'P', 'timezone' => 'Europe/Berlin'], '2024-03-31 03:30:00+02:00'];
        yield ['2024-10-27 00:30:00', ['format' => $format, 'timezone' => 'UTC'],
            ['format' => $format . 'P', 'timezone' => 'Europe/Berlin'], '2024-10-27 02:30:00+02:00'];
        yield ['2024-10-27 01:30:00', ['format' => $format, 'timezone' => 'UTC'],
            ['format' => $format . 'P', 'timezone' => 'Europe/Berlin'], '2024-10-27 02:30:00+01:00'];
        yield ['2024-10-27 02:30:00', ['format' => $format, 'timezone' => 'Europe/Berlin', 'offset' => '+02:00'],
            ['format' => $format . 'P', 'timezone' => 'UTC'], '2024-10-27 00:30:00+00:00'];
        yield ['2024-10-27 02:30:00', ['format' => $format, 'timezone' => 'Europe/Berlin', 'offset' => '+01:00'],
            ['format' => $format . 'P', 'timezone' => 'UTC'], '2024-10-27 01:30:00+00:00'];
        yield ['2024-04-07 01:45:00', ['format' => $format, 'timezone' => 'Australia/Lord_Howe', 'offset' => '+10:30'],
            ['format' => $format . 'P', 'timezone' => 'UTC'], '2024-04-06 15:15:00+00:00'];
        yield ['2024-01-01 00:00:00.123456+05:45', ['format' => $format . '.uP'],
            ['format' => $format . '.uP', 'timezone' => 'UTC'], '2023-12-31 18:15:00.123456+00:00'];

    }

    #[DataProvider('conversions')]
    public function testConversions(string $value, array $source, array $target, string $expected): void
    {

        $before = $value;
        $result = GDate::convertTimezone($value, $source, $target);
        self::assertTrue($result->isValid(), $result->reason ?? '');
        self::assertSame($expected, $result->value());
        self::assertSame($before, $value);

    }

    public function testFormattingRetainsTheSourceZone(): void
    {

        self::assertSame(
            '27.10.2024 02:30+02:00',
            GDate::formatDateTime('2024-10-27 02:30:00', [
                'format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Berlin', 'offset' => '+02:00',
            ], 'd.m.Y H:iP')->value(),
        );
        self::assertSame(
            '2024-01-01 12:00:00-03:30',
            GDate::formatDateTime('2024-01-01 12:00', [
                'format' => 'Y-m-d H:i', 'offset' => '-03:30',
            ], 'Y-m-d H:i:sP')->value(),
        );

    }

    public static function failures(): iterable
    {

        yield ['2024-03-31 02:30:00', 'nonexistent_local_time'];
        yield ['2024-10-27 02:30:00', 'ambiguous_local_time'];
        yield ['2024-02-30 12:00:00', 'invalid_datetime'];
        yield [null, 'type_mismatch'];

    }

    #[DataProvider('failures')]
    public function testInvalidSourceIsNotConverted(mixed $value, string $reason): void
    {

        $source = ['format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Berlin'];
        $target = ['format' => 'Y-m-d H:i:sP', 'timezone' => 'UTC'];
        self::assertSame($reason, GDate::formatDateTime($value, $source, $target['format'])->reason);
        $result = GDate::convertTimezone($value, $source, $target);
        self::assertSame($reason, $result->reason);
        $this->expectException(\LogicException::class);
        $result->value();

    }

    public static function invalidTargets(): iterable
    {

        foreach ([
            ['format' => 'Y-m-d H:i:s', 'unknown' => true],
            ['format' => 'Y-m-d H:i:s', 'timezone' => 'Unknown/Zone'],
            ['format' => 'Y-m-d H:i:s', 'offset' => '+15:00'],
            ['format' => 'Y-m-d H:i:s', 'timezone' => 'UTC', 'offset' => '+00:00'],
        ] as $target) {
            yield [$target];
        }

    }

    #[DataProvider('invalidTargets')]
    public function testInvalidTargetIsConfigurationError(array $target): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GDate::convertTimezone('2024-01-01 00:00:00', ['format' => 'Y-m-d H:i:s', 'timezone' => 'UTC'], $target);
    }

    public function testInvalidOutputFormatIsConfigurationError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GDate::formatDate('2024-01-01', ['format' => 'Y-m-d'], 'H:i:s');
    }


    public function testConversionOutsideCalendarRangeFails(): void
    {

        foreach ([
            ['0001-01-01 00:00:00', '+14:00', '-14:00'],
            ['9999-12-31 23:59:59', '-14:00', '+14:00'],
        ] as [$value, $from, $to]) {

            $result = GDate::convertTimezone($value, ['format' => 'Y-m-d H:i:s', 'offset' => $from], [
                'format' => 'Y-m-d H:i:sP', 'offset' => $to,
            ]);
            self::assertFalse($result->isValid());
            self::assertSame('datetime_out_of_range', $result->reason);

        }

    }

    public function testTimezoneMismatchAndOffsetErrorsArePreserved(): void
    {

        $source = ['format' => 'Y-m-d H:i:sP', 'timezone' => 'Europe/Berlin'];
        $target = ['format' => 'Y-m-d H:i:sP', 'timezone' => 'UTC'];
        self::assertSame(
            'timezone_offset_mismatch',
            GDate::convertTimezone('2024-07-01 12:00:00+01:00', $source, $target)->reason,
        );
        self::assertSame(
            'invalid_offset',
            GDate::convertTimezone('2024-07-01 12:00:00+15:00', $source, $target)->reason,
        );

    }

    public function testRoundTripPreservesResolvedMoment(): void
    {

        $value = '2024-10-27 02:30:00.123456+01:00';
        $format = 'Y-m-d H:i:s.uP';
        $utc = GDate::convertTimezone($value, ['format' => $format, 'timezone' => 'Europe/Berlin'], [
            'format' => $format, 'timezone' => 'UTC',
        ])->value();
        $back = GDate::convertTimezone($utc, ['format' => $format], [
            'format' => $format, 'timezone' => 'Europe/Berlin',
        ]);
        self::assertSame($value, $back->value());

    }

    public function testHistoricalOffsetIsNotSilentlyRounded(): void
    {

        $source = ['format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Paris'];
        self::assertSame(
            'unrepresentable_offset',
            GDate::formatDateTime('1890-01-01 12:00:00', $source, 'Y-m-d H:i:sP')->reason,
        );
        self::assertSame(
            '1890-01-01 11:50:39+00:00',
            GDate::convertTimezone('1890-01-01 12:00:00', $source, [
                'format' => 'Y-m-d H:i:sP', 'timezone' => 'UTC',
            ])->value(),
        );

    }
}