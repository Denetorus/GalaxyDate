<?php
declare(strict_types=1);

namespace Galaxy\Date\Tests;

use Galaxy\Date\GDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarTest extends TestCase
{

    public static function dates(): iterable
    {

        foreach ([true, false] as $strict) {

            foreach ([
                ['Y-m-d', '2026-11-22'], ['d.m.Y', '22.11.2026'], ['Y/m/d', '2026/11/22'],
                ['Y-n-j', '2026-2-3'], ['Ymd', '20261122'], ['d M Y', '22 Nov 2026'],
                ['j F Y', '3 February 2026'], ['d-m-y', '22-11-26'],
                ['Y-m-d', '2024-02-29'], ['Y-m-d', '2000-02-29'], ['Y-m-d', '0001-01-01'],
                ['Y-m-d', '9999-12-31'], ['Y\\Y-m-d', '2026Y-11-22'],
            ] as [$format, $value]) {
                yield [$strict, $format, $value, null];
            }
            foreach ([
                '', '2026-02-30', '2026-02-29', '1900-02-29', '2026-00-01',
                '2026-13-01', '2026-01-00', '2026-01-32', '0000-01-01',
                '2026-2-03', '26-11-22', '2026/11/22', ' 2026-11-22', '2026-11-22 ',
                "2026-11-22\n", "2026-11-22\0", '2026-11-22T12:00:00',
            ] as $value) {
                yield [$strict, 'Y-m-d', $value, 'invalid_date'];
            }
            foreach ([null, false, 20261122, 1.5, [], new \DateTimeImmutable('2026-11-22')] as $value) {
                yield [$strict, 'Y-m-d', $value, 'type_mismatch'];
            }

        }

    }

    public static function values(): iterable
    {

        foreach ([true, false] as $strict) {

            $plain = ['format' => 'Y-m-d H:i:s'];
            yield [$strict, $plain, '2024-02-29 23:59:59', null];
            yield [$strict, ['format' => 'd.m.Y G:i'], '22.11.2026 7:05', null];
            yield [$strict, ['format' => 'Y-m-d H:i:s.u'], '2024-01-01 00:00:00.123456', null];
            yield [$strict, ['format' => 'Y-m-d H:i:s.v'], '2024-01-01 00:00:00.123', null];
            foreach (['2024-02-30 00:00:00', '2024-01-01 24:00:00', '2024-01-01 12:60:00',
                '2024-01-01 12:00:60', '2024-01-01 1:00:00', '2024-01-01 12:00:00 ',
                "2024-01-01 12:00:00\0", '0000-01-01 00:00:00'] as $value) {
                yield [$strict, $plain, $value, 'invalid_datetime'];
            }
            foreach ([null, false, 123, [], new \DateTimeImmutable()] as $value) {
                yield [$strict, $plain, $value, 'type_mismatch'];
            }
            yield [$strict, $plain + ['offset' => '+03:00'], '2024-01-01 12:00:00', null];
            $berlin = $plain + ['timezone' => 'Europe/Berlin'];
            yield [$strict, $berlin, '2024-01-01 12:00:00', null];
            yield [$strict, $berlin, '2024-07-01 12:00:00', null];
            yield [$strict, $berlin, '2024-03-31 02:30:00', 'nonexistent_local_time'];
            yield [$strict, $berlin, '2024-10-27 02:30:00', 'ambiguous_local_time'];
            yield [$strict, $berlin + ['offset' => '+02:00'], '2024-10-27 02:30:00', null];
            yield [$strict, $berlin + ['offset' => '+01:00'], '2024-10-27 02:30:00', null];
            yield [$strict, $berlin + ['offset' => '+03:00'], '2024-10-27 02:30:00', 'timezone_offset_mismatch'];
            yield [$strict, $berlin + ['offset' => '+01:00'], '2024-07-01 12:00:00', 'timezone_offset_mismatch'];
            yield [$strict, $berlin + ['offset' => '+01:00'], '2024-03-31 02:30:00', 'nonexistent_local_time'];
            yield [$strict, $berlin, '2024-03-31 01:59:59', null];
            yield [$strict, $berlin, '2024-03-31 03:00:00', null];
            $inline = ['format' => 'Y-m-d\\TH:i:sP'];
            yield [$strict, $inline, '2024-01-01T12:00:00+05:45', null];
            yield [$strict, $inline, '2024-01-01T12:00:00-03:30', null];
            yield [$strict, $inline, '2024-01-01T12:00:00+14:00', null];
            yield [$strict, $inline, '2024-01-01T12:00:00+14:01', 'invalid_offset'];
            yield [$strict, $inline, '2024-01-01T12:00:00+03:60', 'invalid_offset'];
            yield [$strict, $inline, '2024-01-01T12:00:00Z', 'invalid_datetime'];
            $zonedInline = $inline + ['timezone' => 'Europe/Berlin'];
            yield [$strict, $zonedInline, '2024-10-27T02:30:00+02:00', null];
            yield [$strict, $zonedInline, '2024-10-27T02:30:00+01:00', null];
            yield [$strict, $zonedInline, '2024-10-27T02:30:00+03:00', 'timezone_offset_mismatch'];
            yield [$strict, $plain + ['timezone' => 'America/New_York'], '2024-03-10 02:30:00',
                'nonexistent_local_time'];
            yield [$strict, $plain + ['timezone' => 'America/New_York'], '2024-11-03 01:30:00',
                'ambiguous_local_time'];
            yield [$strict, $plain + ['timezone' => 'Australia/Lord_Howe'], '2024-04-07 01:45:00',
                'ambiguous_local_time'];
            yield [$strict, $plain + ['timezone' => 'Australia/Lord_Howe'], '2024-10-06 02:15:00',
                'nonexistent_local_time'];
            yield [$strict, $plain + ['timezone' => 'Pacific/Apia'], '2011-12-30 12:00:00',
                'nonexistent_local_time'];
            yield [$strict, $plain + ['timezone' => 'UTC'], '2024-10-27 02:30:00', null];
            yield [$strict, $plain + ['timezone' => 'Asia/Kathmandu', 'offset' => '+05:45'],
                '2024-01-01 12:00:00', null];
            yield [$strict, $plain + ['timezone' => 'UTC', 'offset' => '-00:00'], '2024-01-01 12:00:00', null];

        }

    }

    public static function dateOptions(): iterable
    {

        foreach (['date', 'array[date]'] as $type) {

            foreach ([
                [], ['format' => ''], ['format' => null], ['format' => 12],
                ['format' => 'Y-m-d', 'timezone' => 'UTC'], ['format' => 'Y-m'],
                ['format' => 'Y-m-d H:i:s'], ['format' => '!Y-m-d'], ['format' => 'Y-m-d+'],
                ['format' => 'Y-m-d Y'], ['format' => "Y-m-d\0"], ['format' => 'Y-m-d\\'],
                ['format' => 'c'], ['format' => 'U'], ['format' => 'Y-m-d P'],
            ] as $options) {
                yield [$type, $options];
            }

        }

    }

    public static function dateTimeOptions(): iterable
    {

        foreach (['datetime', 'array[datetime]'] as $type) {

            foreach ([
                [], ['format' => ''], ['format' => 12], ['format' => 'Y-m-d'], ['format' => 'H:i:s'],
                ['format' => 'Y-m-d h:i:s a'], ['format' => 'Y-m-d H:i.u'], ['format' => 'Y-m-d H:i:suv'],
                ['format' => 'Y-m-d H:i:sPP'], ['format' => 'Y-m-d H:i:s e'],
                ['format' => 'Y-m-d H:i:s', 'offset' => '+14:01'],
                ['format' => 'Y-m-d H:i:s', 'offset' => '+25:00'],
                ['format' => 'Y-m-d H:i:s', 'offset' => 'UTC'],
                ['format' => 'Y-m-d H:i:s', 'offset' => null],
                ['format' => 'Y-m-d H:i:sP', 'offset' => '+00:00'],
                ['format' => 'Y-m-d H:i:s', 'timezone' => '+03:00'],
                ['format' => 'Y-m-d H:i:s', 'timezone' => 'CET'],
                ['format' => 'Y-m-d H:i:s', 'timezone' => 'Unknown/Zone'],
                ['format' => 'Y-m-d H:i:s', 'timezone' => null],
                ['format' => 'Y-m-d H:i:s', 'unexpected' => true],
            ] as $options) {
                yield [$type, $options];
            }

        }

    }


    #[DataProvider('dates')]
    public function testDates(bool $strict, string $format, mixed $value, ?string $reason): void
    {

        $result = GDate::validateDate($value, ['format' => $format]);
        self::assertSame($reason, $result->reason);
        self::assertSame($reason === null, $result->isValid());
        if ($reason === null) {
            self::assertSame($value, $result->value());
        }

    }

    #[DataProvider('values')]
    public function testDateTimes(bool $strict, array $options, mixed $value, ?string $reason): void
    {

        $result = GDate::validateDateTime($value, $options);
        self::assertSame($reason, $result->reason);
        self::assertSame($reason === null, $result->isValid());
        if ($reason === null) {
            self::assertSame($value, $result->value());
        }

    }

    #[DataProvider('dateOptions')]
    public function testInvalidDateOptions(string $type, array $options): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GDate::checkDateOptions($options);
    }

    #[DataProvider('dateTimeOptions')]
    public function testInvalidDateTimeOptions(string $type, array $options): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GDate::checkDateTimeOptions($options);
    }

    public function testDatesRemainCalendarValuesAcrossSkippedDays(): void
    {

        $old = date_default_timezone_get();
        try {

            date_default_timezone_set('Pacific/Apia');
            $value = '2011-12-30';
            self::assertSame($value, GDate::validateDate($value, ['format' => 'Y-m-d'])->value());
            self::assertTrue(GDate::validateDateTime($value . ' 12:00', ['format' => 'Y-m-d H:i'])->isValid());
            $zoned = GDate::validateDateTime($value . ' 12:00', [
                'format' => 'Y-m-d H:i', 'timezone' => 'Pacific/Apia',
            ]);
            self::assertSame('nonexistent_local_time', $zoned->reason);

        }
        finally {
            date_default_timezone_set($old);
        }

    }

}