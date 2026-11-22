<?php
declare(strict_types=1);

namespace Galaxy\Date;

use Galaxy\Date\Internal\{DateValidator, DateTimeValidator, TemporalOptions};

final class GDate
{

    public static function checkDateOptions(string|array $options): void
    {
        DateValidator::checkOptions(TemporalOptions::normalize($options));
    }

    public static function checkDateTimeOptions(string|array $options): void
    {
        DateTimeValidator::checkOptions(TemporalOptions::normalize($options));
    }

    public static function validateDate(mixed $value, string|array $options): DateResult
    {
        $options = TemporalOptions::normalize($options);
        self::checkDateOptions($options);
        [$reason, $original] = DateValidator::validate($value, $options['format']);
        return $reason === null ? DateResult::success($original) : DateResult::failure($reason);
    }

    public static function validateDateTime(mixed $value, string|array $options): DateResult
    {
        $options = TemporalOptions::normalize($options);
        self::checkDateTimeOptions($options);
        [$reason, $original] = DateTimeValidator::validate($value, $options);
        return $reason === null ? DateResult::success($original) : DateResult::failure($reason);
    }

    public static function formatDate(mixed $value, string|array $source, string|array $target): DateResult
    {
        $source = TemporalOptions::normalize($source);
        $target = TemporalOptions::normalize($target);
        return Internal\DateFormatter::date($value, $source, $target);
    }

    public static function formatDateTime(mixed $value, string|array $source, string|array $target): DateResult
    {
        $source = TemporalOptions::normalize($source);
        $target = TemporalOptions::normalize($target);
        return Internal\DateFormatter::dateTime($value, $source, $target);
    }

    public static function convertTimezone(mixed $value, string|array $source, string|array $target): DateResult
    {
        $source = TemporalOptions::normalize($source);
        $target = TemporalOptions::normalize($target);
        return Internal\DateFormatter::convert($value, $source, $target);
    }

    public static function toDateTime(mixed $value, string|array $source): DateResult
    {
        return Internal\TypedConverter::toDateTime($value, TemporalOptions::normalize($source));
    }

    public static function toTimestamp(mixed $value, string|array $source): DateResult
    {
        return Internal\TypedConverter::toTimestamp($value, TemporalOptions::normalize($source));
    }

    public static function fromDateTime(mixed $value, string|array $target): DateResult
    {
        return Internal\TypedConverter::fromDateTime($value, TemporalOptions::normalize($target));
    }

    public static function fromTimestamp(mixed $value, string|array $target): DateResult
    {
        return Internal\TypedConverter::fromTimestamp($value, TemporalOptions::normalize($target));
    }

}