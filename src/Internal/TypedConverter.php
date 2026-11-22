<?php
declare(strict_types=1);

namespace Galaxy\Date\Internal;

use Galaxy\Date\DateResult;

final class TypedConverter
{

    public static function toDateTime(mixed $value, array $source): DateResult
    {

        $resolved = TemporalInput::resolve($value, $source);
        if ($resolved[0] !== null) {
            return DateResult::failure($resolved[0]);
        }
        if (!$resolved[2]) {
            return DateResult::failure('timezone_required');
        }
        return DateResult::success($resolved[1]);

    }

    public static function toTimestamp(mixed $value, array $source): DateResult
    {

        try {

            $result = self::toDateTime($value, $source);
            if (!$result->isValid()) {
                return $result;
            }
            $date = $result->value();
            if ($date->format('u') !== '000000') {
                return DateResult::failure('precision_loss');
            }
            return DateResult::success($date->getTimestamp());

        }
        catch (\DateRangeError) {
            return DateResult::failure('timestamp_out_of_range');
        }

    }
    public static function fromDateTime(mixed $value, array $target): DateResult
    {

        $zone = TemporalOptions::targetZone($target);
        if (!$value instanceof \DateTimeInterface) {
            return DateResult::failure('type_mismatch');
        }
        $date = \DateTimeImmutable::createFromInterface($value);
        return DateFormatter::format($zone === null ? $date : $date->setTimezone($zone), $target['format']);

    }

    public static function fromTimestamp(mixed $value, array $target): DateResult
    {

        $zone = TemporalOptions::targetZone($target, required: true);
        if (!is_int($value)) {
            return DateResult::failure('type_mismatch');
        }
        try {
            $date = new \DateTimeImmutable('@' . $value);
        }
        catch (\DateException|\DateError) {
            return DateResult::failure('timestamp_out_of_range');
        }
        return DateFormatter::format($date->setTimezone($zone), $target['format']);

    }

}