<?php
declare(strict_types=1);

namespace Galaxy\Date\Internal;

final class TemporalOptions
{

    public static function normalize(string|array $format): array
    {
        return is_string($format) ? ['format' => $format] : $format;
    }

    public static function checkSource(array $source): bool
    {

        $dateOnly = self::checkFormat($source);
        if ($dateOnly) {
            $source['format'] = 'Y-m-d H:i:s';
        }
        DateTimeValidator::checkOptions($source);
        return $dateOnly;

    }

    public static function checkFormat(array $options): bool
    {

        if (!isset($options['format']) || !is_string($options['format']) || $options['format'] === '') {
            throw new \InvalidArgumentException('A nonempty format is required.');
        }
        $format = $options['format'];
        for ($index = 0, $length = strlen($format); $index < $length; ++$index) {

            if ($format[$index] === '\\') {
                ++$index;
            }
            elseif (str_contains('HGisuv', $format[$index])) {
                DateTimeValidator::checkOptions(['format' => $format]);
                return false;
            }

        }
        DateValidator::checkOptions(['format' => $format]);
        return true;

    }

    public static function targetZone(array $target, bool $required = false): ?\DateTimeZone
    {

        self::checkFormat($target);
        if (array_key_exists('timezone', $target) && array_key_exists('offset', $target)) {
            throw new \InvalidArgumentException('Target allows only one timezone or offset.');
        }
        $options = $target;
        $options['format'] = 'Y-m-d H:i:s';
        DateTimeValidator::checkOptions($options);
        if (isset($options['timezone'])) {
            return new \DateTimeZone($options['timezone']);
        }
        if (isset($options['offset'])) {
            return new \DateTimeZone($options['offset']);
        }
        if ($required) {
            throw new \InvalidArgumentException('Target requires an explicit timezone or offset.');
        }
        return null;

    }

}