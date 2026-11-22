<?php
declare(strict_types=1);

namespace Galaxy\Date\Internal;

final class DateTimeValidator
{

    public static function checkOptions(array $options): void
    {

        if (!isset($options['format']) || !is_string($options['format']) || $options['format'] === '') {
            throw new \InvalidArgumentException('Datetime requires a nonempty string format.');
        }
        if (array_diff(array_keys($options), ['format', 'offset', 'timezone']) !== []) {
            throw new \InvalidArgumentException('Unknown datetime option.');
        }
        self::compile($options['format']);
        if (array_key_exists('offset', $options)) {

            if (!is_string($options['offset']) || self::offsetSeconds($options['offset']) === null) {
                throw new \InvalidArgumentException('Invalid datetime offset.');
            }
            if (self::hasOffset($options['format'])) {
                throw new \InvalidArgumentException('Offset must be specified either in input or in options.');
            }

        }
        if (array_key_exists('timezone', $options)) {

            $zone = $options['timezone'];
            if (!is_string($zone) || ($zone !== 'UTC' && (
                !str_contains($zone, '/') || !in_array($zone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)
            ))) {
                throw new \InvalidArgumentException('Expected an IANA timezone identifier or UTC.');
            }

        }

    }

    public static function validate(mixed $value, array $options): array
    {
        [$reason] = self::resolve($value, $options);
        return $reason === null ? [null, $value] : [$reason, null];
    }

    public static function resolve(mixed $value, array $options): array
    {

        if (!is_string($value)) {
            return ['type_mismatch', null];
        }
        [$pattern] = self::compile($options['format']);
        if (preg_match($pattern, $value, $matches) !== 1) {
            return ['invalid_datetime', null];
        }
        $offset = $matches['offset'] ?? $options['offset'] ?? null;
        $seconds = $offset === null ? null : self::offsetSeconds($offset);
        if ($offset !== null && $seconds === null) {
            return ['invalid_offset', null];
        }
        [, $parseFormat] = self::compile($options['format'], $matches['offset'] ?? null);
        $wall = \DateTimeImmutable::createFromFormat('!' . $parseFormat, $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($wall === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return ['invalid_datetime', null];
        }
        if ($wall->format($parseFormat) !== $value || !checkdate(
            (int) $wall->format('n'),
            (int) $wall->format('j'),
            (int) $wall->format('Y'),
        )) {
            return ['invalid_datetime', null];
        }
        if (isset($options['timezone'])) {

            $zone = new \DateTimeZone($options['timezone']);
            [$reason, $seconds] = self::resolveZone($wall, $zone, $seconds);
            if ($reason !== null) {
                return [$reason, null];
            }

        }
        $hasZone = isset($options['timezone']) || $seconds !== null;
        if ($hasZone) {
            $zone = $zone ?? new \DateTimeZone($offset);
            $wall = $wall->modify(sprintf('%+d seconds', -$seconds))->setTimezone($zone);
        }
        return [null, $wall, $hasZone];

    }

    private static function resolveZone(\DateTimeImmutable $wall, \DateTimeZone $zone, ?int $offset): array
    {

        $timestamp = $wall->getTimestamp();
        // Covers offsets and date-line transitions without assuming a one-hour DST change.
        $transitions = $zone->getTransitions($timestamp - 172800, $timestamp + 172800);
        $candidates = [];
        foreach (array_unique(array_column($transitions, 'offset')) as $candidateOffset) {

            $instant = (new \DateTimeImmutable('@' . ($timestamp - $candidateOffset)))->setTimezone($zone);
            if ($instant->format('Y-m-d H:i:s') === $wall->format('Y-m-d H:i:s')) {
                $candidates[] = $candidateOffset;
            }

        }
        if ($candidates === []) {
            return ['nonexistent_local_time', null];
        }
        if ($offset !== null) {
            return in_array($offset, $candidates, true) ? [null, $offset] : ['timezone_offset_mismatch', null];
        }
        return count($candidates) > 1 ? ['ambiguous_local_time', null] : [null, $candidates[0]];

    }

    private static function offsetSeconds(string $offset): ?int
    {

        if (!preg_match('/\\A([+-])([0-9]{2}):([0-9]{2})\\z/D', $offset, $matches)) {
            return null;
        }
        $hours = (int) $matches[2];
        $minutes = (int) $matches[3];
        if ($hours > 14 || $minutes > 59 || ($hours === 14 && $minutes !== 0)) {
            return null;
        }
        return ($matches[1] === '-' ? -1 : 1) * ($hours * 3600 + $minutes * 60);

    }

    public static function hasOffset(string $format): bool
    {

        for ($index = 0, $length = strlen($format); $index < $length; ++$index) {

            if ($format[$index] === '\\') {
                ++$index;
            }
            elseif ($format[$index] === 'P') {
                return true;
            }

        }
        return false;

    }

    private static function compile(string $format, ?string $offset = null): array
    {

        $tokens = [
            'Y' => ['year', '[0-9]{4}'], 'y' => ['year', '[0-9]{2}'],
            'm' => ['month', '[0-9]{2}'], 'n' => ['month', '[0-9]{1,2}'],
            'M' => ['month', '[A-Za-z]{3}'], 'F' => ['month', '[A-Za-z]+'],
            'd' => ['day', '[0-9]{2}'], 'j' => ['day', '[0-9]{1,2}'],
            'H' => ['hour', '[0-9]{2}'], 'G' => ['hour', '[0-9]{1,2}'],
            'i' => ['minute', '[0-9]{2}'], 's' => ['second', '[0-9]{2}'],
            'u' => ['fraction', '[0-9]{6}'], 'v' => ['fraction', '[0-9]{3}'],
            'P' => ['offset', '(?<offset>[+-][0-9]{2}:[0-9]{2})'],
        ];
        $fields = [];
        $pattern = '';
        $parseFormat = '';
        for ($index = 0, $length = strlen($format); $index < $length; ++$index) {

            $char = $format[$index];
            if ($char === '\\') {

                if (++$index >= $length || ord($format[$index]) < 32 || ord($format[$index]) === 127) {
                    throw new \InvalidArgumentException('Invalid datetime format escape.');
                }
                $pattern .= preg_quote($format[$index], '~');
                $parseFormat .= '\\' . $format[$index];
                continue;

            }
            if (isset($tokens[$char])) {

                [$field, $expression] = $tokens[$char];
                $fields[$field] = ($fields[$field] ?? 0) + 1;
                $pattern .= $expression;
                if ($char === 'P' && $offset !== null) {
                    $parseFormat .= '\\' . implode('\\', str_split($offset));
                }
                else {
                    $parseFormat .= $char;
                }

            }
            else {

                if (
                    ($char >= 'A' && $char <= 'Z') || ($char >= 'a' && $char <= 'z')
                    || str_contains('!|+#?*', $char) || ord($char) < 32 || ord($char) === 127
                ) {
                    throw new \InvalidArgumentException('Unsupported datetime format token.');
                }
                $pattern .= preg_quote($char, '~');
                $parseFormat .= $char;

            }

        }
        foreach (['year', 'month', 'day', 'hour', 'minute'] as $required) {
            if (($fields[$required] ?? 0) !== 1) {
                throw new \InvalidArgumentException('Datetime requires a date, hour and minute.');
            }
        }
        if (max($fields) > 1 || (isset($fields['fraction']) && !isset($fields['second']))) {
            throw new \InvalidArgumentException('Duplicate fields or fraction without seconds.');
        }
        return ['~\\A' . $pattern . '\\z~D', $parseFormat];

    }

}