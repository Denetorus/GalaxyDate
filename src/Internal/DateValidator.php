<?php
declare(strict_types=1);

namespace Galaxy\Date\Internal;

final class DateValidator
{

    public static function checkOptions(array $options): void
    {

        if (array_keys($options) !== ['format'] || !is_string($options['format']) || $options['format'] === '') {
            throw new \InvalidArgumentException('Date requires only a nonempty string format option.');
        }
        self::checkDateFormat($options['format']);

    }
    public static function validate(mixed $value, string $format): array
    {

        if (!is_string($value)) {
            return ['type_mismatch', null];
        }
        if (str_contains($value, "\0")) {
            return ['invalid_date', null];
        }
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return ['invalid_date', null];
        }
        if ($date->format($format) !== $value || !checkdate(
            (int) $date->format('n'),
            (int) $date->format('j'),
            (int) $date->format('Y'),
        )) {
            return ['invalid_date', null];
        }
        return [null, $value];

    }

    private static function checkDateFormat(string $format): void
    {

        $fields = ['year' => 0, 'month' => 0, 'day' => 0];
        $tokens = ['Y' => 'year', 'y' => 'year', 'm' => 'month', 'n' => 'month',
            'M' => 'month', 'F' => 'month', 'd' => 'day', 'j' => 'day'];
        for ($index = 0, $length = strlen($format); $index < $length; ++$index) {

            $char = $format[$index];
            if ($char === '\\') {

                if (++$index >= $length || ord($format[$index]) < 32 || ord($format[$index]) === 127) {
                    throw new \InvalidArgumentException('Invalid date format escape.');
                }
                continue;

            }
            if (isset($tokens[$char])) {
                ++$fields[$tokens[$char]];
            }
            elseif (
                ($char >= 'A' && $char <= 'Z') || ($char >= 'a' && $char <= 'z')
                || str_contains('!|+#?*', $char) || ord($char) < 32 || ord($char) === 127
            ) {
                throw new \InvalidArgumentException('Unsupported date format token.');
            }

        }
        if ($fields !== ['year' => 1, 'month' => 1, 'day' => 1]) {
            throw new \InvalidArgumentException('Date format must contain one year, month and day.');
        }

    }

}
