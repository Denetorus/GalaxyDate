# GalaxyDate

[Русский](README.ru.md) · [ADR-001 — Architecture](docs/adr/ADR-001-GalaxyDate.md)

Calendar dates, date/time, format conversion, IANA timezones and Unix timestamps.
PHP >=8.4 only. Namespace: `Galaxy\Date`. Package: `denetorus/galaxy-date`.
No runtime dependencies on other Galaxy components.

## Installation from local source

For an independent application, place its directory beside the GalaxyDate source directory.
Use this application `composer.json`:

```json
{
    "name": "example/date-consumer",
    "require": {
        "php": ">=8.4",
        "denetorus/galaxy-date": "dev-master"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../GalaxyDate",
            "options": {
                "symlink": false,
                "versions": {"denetorus/galaxy-date": "dev-master"}
            }
        }
    ]
}
```

Run from the application directory:

```sh
composer install --no-dev
```

This is a local source installation, not a Packagist release.
The explicit development version identifies the local source and does not designate a released version.
Composer repositories must be configured in the consuming application's root manifest.

## Getting started

Save as `example.php` in the application directory:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Galaxy\Date\GDate;

$result = GDate::formatDate('2026-11-22', 'Y-m-d', ['format' => 'd.m.Y']);
if (!$result->isValid()) {
    throw new RuntimeException($result->reason);
}
echo $result->value(), PHP_EOL; // 22.11.2026

// A date without time means midnight in the explicitly specified zone.
$options = ['format' => 'Y-m-d', 'timezone' => 'Europe/Berlin'];
$object = GDate::toDateTime('2026-11-22', $options)->value();
assert($object instanceof DateTimeImmutable);
assert($object->format('Y-m-d H:i:sP') === '2026-11-22 00:00:00+01:00');
assert(GDate::fromDateTime($object, 'd.m.Y')->value() === '22.11.2026');

// Unix seconds; the target zone is explicit on the way back.
$timestamp = GDate::toTimestamp('1970-01-01', ['format' => 'Y-m-d', 'timezone' => 'UTC'])->value();
assert($timestamp === 0);
assert(GDate::fromTimestamp($timestamp, ['format' => 'd.m.Y', 'timezone' => 'UTC'])->value() === '01.01.1970');

$converted = GDate::convertTimezone(
    '2024-07-01 12:00:00',
    ['format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Berlin'],
    ['format' => 'Y-m-d H:i:sP', 'timezone' => 'UTC'],
);
assert($converted->value() === '2024-07-01 10:00:00+00:00');
```

Run `php -d zend.assertions=1 -d assert.exception=1 example.php`.
The successful value is a string, `int` or `DateTimeImmutable`, depending on the method.
For external data, check `isValid()` before reading `value()`; failures expose `reason`.
Calling `value()` on a failure throws `LogicException`.
Invalid format/options are configuration errors and throw `InvalidArgumentException`.

## Rules and boundaries

A format string is shorthand for `['format' => ...]`, without an implicit timezone.
Validation preserves the input string. Formatting and conversion return a new representation.
DateTime objects supplied to `fromDateTime()` are not modified.

Formats use a documented subset of PHP tokens: date, 24-hour time, seconds, fractions and offset `P`.
Nonexistent local times are rejected; repeated times require an explicit offset consistent with the zone.
Timestamp conversion rejects nonzero microseconds with `precision_loss`.
Shorter output formats can intentionally lose precision. See the Accepted ADR for the complete API and limits.

## Timezone database diagnostics

`Galaxy\Date\Diagnostics\TimezoneDatabase::info()` reads the local database version.
An explicit `checkForUpdates()` checks the latest stable PHP timezonedb release over HTTPS.
Statuses are `up_to_date`, `update_available` or `unknown`.
Validation and conversion never contact the network; no updates are installed automatically.
See the ADR for timeout, system-database and update-source limitations.

## Development checks

From the GalaxyDate source directory:

```sh
composer install
composer validate --strict
php vendor/bin/phpunit
composer dump-autoload --no-dev --optimize --strict-psr
```

The CI workflow targets PHP 8.4 and checks PHPUnit, Composer, PSR-4 and an independent runtime-only consumer.

## License

[MIT](LICENSE). Copyright (c) 2026 Mylnikov Denis.