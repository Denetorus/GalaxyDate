<?php
declare(strict_types=1);

namespace Galaxy\Date\Internal;

final class TemporalInput
{

    public static function resolve(mixed $value, array $source): array
    {

        $dateOnly = TemporalOptions::checkSource($source);
        if (!$dateOnly) {
            return DateTimeValidator::resolve($value, $source);
        }
        [$reason] = DateValidator::validate($value, $source['format']);
        if ($reason !== null) {
            return [$reason, null];
        }
        $date = \DateTimeImmutable::createFromFormat('!' . $source['format'], $value, new \DateTimeZone('UTC'));
        $source['format'] = 'Y-m-d H:i:s';
        return DateTimeValidator::resolve($date->format('Y-m-d') . ' 00:00:00', $source);

    }

}