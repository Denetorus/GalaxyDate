<?php
declare(strict_types=1);

namespace Galaxy\Date\Internal;

use Galaxy\Date\DateResult;

final class DateFormatter
{

    public static function date(mixed $value, array $source, array $target): DateResult
    {

        DateValidator::checkOptions($source);
        DateValidator::checkOptions($target);
        [$reason] = DateValidator::validate($value, $source['format']);
        if ($reason !== null) {
            return DateResult::failure($reason);
        }
        $date = \DateTimeImmutable::createFromFormat('!' . $source['format'], $value, new \DateTimeZone('UTC'));
        return DateResult::success($date->format($target['format']));

    }

    public static function dateTime(mixed $value, array $source, array $target): DateResult
    {

        TemporalOptions::checkSource($source);
        $zone = TemporalOptions::targetZone($target);
        $resolved = TemporalInput::resolve($value, $source);
        if ($resolved[0] !== null) {
            return DateResult::failure($resolved[0]);
        }
        if (($zone !== null || DateTimeValidator::hasOffset($target['format'])) && !$resolved[2]) {
            return DateResult::failure('timezone_required');
        }
        $date = $zone === null ? $resolved[1] : $resolved[1]->setTimezone($zone);
        return self::format($date, $target['format']);

    }

    public static function convert(mixed $value, array $source, array $target): DateResult
    {

        TemporalOptions::checkSource($source);
        $zone = TemporalOptions::targetZone($target);
        $resolved = TemporalInput::resolve($value, $source);
        if ($resolved[0] !== null) {
            return DateResult::failure($resolved[0]);
        }
        if (!$resolved[2]) {
            return DateResult::failure('timezone_required');
        }
        $date = $zone === null ? $resolved[1] : $resolved[1]->setTimezone($zone);
        return self::format($date, $target['format']);

    }

    public static function format(\DateTimeImmutable $date, string $format): DateResult
    {

        $year = (int) $date->format('Y');
        if ($year < 1 || $year > 9999) {
            return DateResult::failure('datetime_out_of_range');
        }
        if (DateTimeValidator::hasOffset($format) && ($date->getOffset() % 60 !== 0 || abs($date->getOffset()) > 50400)) {
            return DateResult::failure('unrepresentable_offset');
        }
        return DateResult::success($date->format($format));

    }

}