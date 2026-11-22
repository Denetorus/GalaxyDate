<?php
declare(strict_types=1);

namespace Galaxy\Date\Tests;

use Galaxy\Date\{DateResult, GDate};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TypedConversionTest extends TestCase
{

    public function testDateBecomesMidnightInExplicitZone(): void
    {

        $options = ['format' => 'Y-m-d', 'timezone' => 'Europe/Berlin'];
        $result = GDate::toDateTime('2026-11-22', $options);
        self::assertTrue($result->isValid());
        self::assertInstanceOf(\DateTimeImmutable::class, $result->value());
        self::assertSame('2026-11-22 00:00:00.000000+01:00', $result->value()->format('Y-m-d H:i:s.uP'));
        self::assertSame('Europe/Berlin', $result->value()->getTimezone()->getName());
        self::assertSame($result->value()->getTimestamp(), GDate::toTimestamp('2026-11-22', $options)->value());

    }

    public function testFullDatetimeStillRequiresTime(): void
    {

        self::assertSame('invalid_datetime', GDate::toDateTime('2026-11-22', [
            'format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Berlin',
        ])->reason);
        self::assertSame('timezone_required', GDate::toDateTime('2026-11-22', 'Y-m-d')->reason);
        self::assertSame('timezone_required', GDate::toTimestamp('2026-11-22 12:00', 'Y-m-d H:i')->reason);

    }

    public function testMidnightTransitionRules(): void
    {

        foreach ([
            ['2011-12-30', 'Pacific/Apia', 'nonexistent_local_time'],
            ['2018-11-04', 'America/Sao_Paulo', 'nonexistent_local_time'],
            ['2024-11-03', 'America/Havana', 'ambiguous_local_time'],
        ] as [$value, $zone, $reason]) {
            self::assertSame($reason, GDate::toDateTime($value, ['format' => 'Y-m-d', 'timezone' => $zone])->reason);
        }
        foreach (['-04:00', '-05:00'] as $offset) {

            $result = GDate::toDateTime('2024-11-03', [
                'format' => 'Y-m-d', 'timezone' => 'America/Havana', 'offset' => $offset,
            ]);
            self::assertSame('2024-11-03 00:00:00' . $offset, $result->value()->format('Y-m-d H:i:sP'));

        }

    }

    public function testObjectPreservesFractionAndTimestampRejectsPrecisionLoss(): void
    {

        $options = ['format' => 'Y-m-d H:i:s.uP'];
        $value = '1969-12-31 23:59:59.123456+00:00';
        self::assertSame($value, GDate::toDateTime($value, $options)->value()->format($options['format']));
        self::assertSame('precision_loss', GDate::toTimestamp($value, $options)->reason);
        self::assertSame(-1, GDate::toTimestamp('1969-12-31 23:59:59.000000+00:00', $options)->value());
        self::assertSame(0, GDate::toTimestamp('1970-01-01 00:00:00+00:00', 'Y-m-d H:i:sP')->value());

    }

    public function testDateTimeInterfaceIsNotMutated(): void
    {

        foreach ([\DateTime::class, \DateTimeImmutable::class] as $class) {

            $input = new $class('2024-10-27 02:30:00.123456+01:00');
            $before = $input->format('Y-m-d H:i:s.uP');
            $result = GDate::fromDateTime($input, ['format' => 'Y-m-d H:i:s.uP', 'timezone' => 'UTC']);
            self::assertSame('2024-10-27 01:30:00.123456+00:00', $result->value());
            self::assertSame($before, $input->format('Y-m-d H:i:s.uP'));
            self::assertSame($before, GDate::fromDateTime($input, 'Y-m-d H:i:s.uP')->value());

        }

    }

    public function testTimestampRoundTripAndTargetDay(): void
    {

        self::assertSame(
            '1969-12-31 20:30:00-03:30',
            GDate::fromTimestamp(0, ['format' => 'Y-m-d H:i:sP', 'offset' => '-03:30'])->value(),
        );
        self::assertSame(
            '1970-01-01',
            GDate::fromTimestamp(0, ['format' => 'Y-m-d', 'timezone' => 'UTC'])->value(),
        );
        foreach ([-1, 0, 1729992600] as $timestamp) {

            $target = ['format' => 'Y-m-d H:i:sP', 'timezone' => 'Europe/Berlin'];
            $string = GDate::fromTimestamp($timestamp, $target)->value();
            self::assertSame($timestamp, GDate::toTimestamp($string, $target)->value());

        }

    }

    public function testTimestampNeedsExplicitTargetZone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GDate::fromTimestamp(0, 'Y-m-d H:i:sP');
    }

    public static function invalidTimestamps(): iterable
    {

        foreach (['0', 0.0, false, null, [], new \stdClass()] as $value) {
            yield [$value];
        }

    }

    #[DataProvider('invalidTimestamps')]
    public function testTimestampIsStrictlyInteger(mixed $value): void
    {
        self::assertSame('type_mismatch', GDate::fromTimestamp($value, [
            'format' => 'Y-m-d H:i:sP', 'timezone' => 'UTC',
        ])->reason);
    }

    public function testTimestampRangeAndInvalidObject(): void
    {

        if (PHP_INT_SIZE === 8) {

            foreach ([PHP_INT_MIN, PHP_INT_MAX] as $value) {
                self::assertSame('datetime_out_of_range', GDate::fromTimestamp($value, [
                    'format' => 'Y-m-d H:i:sP', 'timezone' => 'UTC',
                ])->reason);
            }

        }
        self::assertSame('type_mismatch', GDate::fromDateTime('2026-11-22', 'Y-m-d')->reason);

    }

    public function testStringAndArrayFormatsAreEquivalent(): void
    {

        self::assertSame('22.11.2026', GDate::formatDate('2026-11-22', 'Y-m-d', ['format' => 'd.m.Y'])->value());
        self::assertSame('22.11.2026', GDate::formatDate('2026-11-22', ['format' => 'Y-m-d'], 'd.m.Y')->value());
        self::assertSame('2026-11-22', GDate::validateDate('2026-11-22', 'Y-m-d')->value());
        self::assertTrue(GDate::validateDateTime('2026-11-22 14:30', 'Y-m-d H:i')->isValid());
        self::assertSame('22.11.2026 14:30', GDate::formatDateTime(
            '2026-11-22 14:30', 'Y-m-d H:i', ['format' => 'd.m.Y H:i'],
        )->value());
        self::assertSame('22.11.2026 14:30+03:00', GDate::convertTimezone(
            '2026-11-22 14:30+03:00', 'Y-m-d H:iP', 'd.m.Y H:iP',
        )->value());

    }

    public function testDatetimeTargetZoneTriggersConversion(): void
    {

        self::assertSame('2024-07-01 10:00:00+00:00', GDate::formatDateTime(
            '2024-07-01 12:00:00',
            ['format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Berlin'],
            ['format' => 'Y-m-d H:i:sP', 'timezone' => 'UTC'],
        )->value());
        self::assertSame('timezone_required', GDate::formatDateTime(
            '2024-07-01 12:00', 'Y-m-d H:i', ['format' => 'Y-m-d H:iP', 'timezone' => 'UTC'],
        )->reason);

    }

    public function testDateOnlyCanBeFormattedButNeverAssignedATargetZoneAsSource(): void
    {

        self::assertSame('2026-11-22 00:00+03:00', GDate::fromDateTime(
            GDate::toDateTime('2026-11-22', ['format' => 'Y-m-d', 'offset' => '+03:00'])->value(),
            'Y-m-d H:iP',
        )->value());
        $this->expectException(\InvalidArgumentException::class);
        GDate::formatDate('2026-11-22', 'Y-m-d', ['format' => 'd.m.Y', 'timezone' => 'UTC']);

    }

    public function testTypedResultKeepsValuesOutOfDebugInformation(): void
    {

        $object = new \DateTimeImmutable('2026-11-22', new \DateTimeZone('UTC'));
        foreach ([$object, 0, '2026-11-22'] as $value) {

            $result = DateResult::success($value);
            self::assertSame($value, $result->value());
            self::assertSame(['reason' => null], $result->__debugInfo());

        }

    }

}