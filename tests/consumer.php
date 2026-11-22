<?php
declare(strict_types=1);

use Galaxy\Date\{DateResult, GDate};
use Galaxy\Date\Diagnostics\TimezoneDatabase;

$root = realpath($argv[1] ?? '');
$source = realpath($argv[2] ?? dirname(__DIR__));
if ($root === false || $source === false || !is_file($root . '/vendor/autoload.php')) {
    throw new RuntimeException('Supply the installed consumer directory and optional source directory.');
}
require $root . '/vendor/autoload.php';
$checks = 0;

function ensure(bool $condition, string $message): void
{

    global $checks;
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }

}

$lock = json_decode(file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
ensure(array_column($lock['packages'], 'name') === ['denetorus/galaxy-date'], 'Unexpected runtime dependencies.');
ensure(($lock['packages-dev'] ?? []) === [], 'Unexpected dev dependencies.');
$installed = json_decode(
    file_get_contents($root . '/vendor/composer/installed.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
ensure($installed['dev'] === false, 'Expected a runtime-only installation.');
ensure(in_array('MIT', $installed['packages'][0]['license'], true), 'Missing MIT metadata.');
ensure(!class_exists('Galaxy\\Validation\\GValidator'), 'GalaxyValidation must not be installed.');
ensure(!class_exists('PHPUnit\\Framework\\TestCase'), 'PHPUnit must not be installed.');

$vendor = realpath($root . '/vendor') . DIRECTORY_SEPARATOR;
foreach ([GDate::class, DateResult::class, TimezoneDatabase::class] as $class) {

    $file = (new ReflectionClass($class))->getFileName();
    ensure(str_starts_with(realpath($file), $vendor), 'Class loaded outside the consumer vendor directory.');

}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . '/src'));
foreach ($iterator as $file) {

    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($source . '/src') + 1);
    $copy = $root . '/vendor/denetorus/galaxy-date/src/' . $relative;
    ensure(
        is_file($copy) && hash_file('sha256', $file->getPathname()) === hash_file('sha256', $copy),
        'Installed source mismatch.',
    );

}

ensure(GDate::validateDate('2024-02-29', 'Y-m-d')->value() === '2024-02-29', 'Leap date failed.');
ensure(GDate::validateDate('2026-02-30', 'Y-m-d')->reason === 'invalid_date', 'Invalid date accepted.');
ensure(GDate::formatDate('2026-11-22', 'Y-m-d', ['format' => 'd.m.Y'])->value() === '22.11.2026', 'Format failed.');
$zone = ['format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Berlin'];
ensure(
    GDate::validateDateTime('2024-03-31 02:30:00', $zone)->reason === 'nonexistent_local_time',
    'Gap accepted.',
);
ensure(
    GDate::validateDateTime('2024-10-27 02:30:00', $zone)->reason === 'ambiguous_local_time',
    'Fold accepted.',
);
ensure(GDate::validateDateTime('2024-10-27 02:30:00', $zone + ['offset' => '+02:00'])->isValid(), 'Fold not resolved.');
ensure(
    GDate::convertTimezone('2024-07-01 12:00:00', $zone, [
        'format' => 'Y-m-d H:i:sP', 'timezone' => 'UTC',
    ])->value() === '2024-07-01 10:00:00+00:00',
    'Timezone conversion failed.',
);
$options = ['format' => 'Y-m-d', 'timezone' => 'UTC'];
$object = GDate::toDateTime('1970-01-01', $options)->value();
ensure($object instanceof DateTimeImmutable, 'Expected immutable object.');
ensure($object->format('H:i:s.u') === '00:00:00.000000', 'Date must become midnight.');
ensure(GDate::toTimestamp('1970-01-01', $options)->value() === 0, 'Epoch zero failed.');
ensure(GDate::toTimestamp('1969-12-31 23:59:59+00:00', 'Y-m-d H:i:sP')->value() === -1, 'Negative epoch failed.');
ensure(GDate::fromTimestamp(0, $options)->value() === '1970-01-01', 'Timestamp formatting failed.');
ensure(GDate::fromDateTime($object, 'd.m.Y')->value() === '01.01.1970', 'Object formatting failed.');
ensure(
    GDate::toTimestamp('1970-01-01 00:00:00.000001+00:00', 'Y-m-d H:i:s.uP')->reason === 'precision_loss',
    'Fractional timestamp silently rounded.',
);
$mutable = new DateTime('2024-01-01 12:00:00.123456+03:00');
$before = $mutable->format('Y-m-d H:i:s.uP');
ensure(
    GDate::fromDateTime($mutable, ['format' => 'Y-m-d H:i:s.uP', 'timezone' => 'UTC'])->value()
        === '2024-01-01 09:00:00.123456+00:00',
    'Mutable object formatting failed.',
);
ensure($mutable->format('Y-m-d H:i:s.uP') === $before, 'Input object was mutated.');
ensure(TimezoneDatabase::info()['installedVersion'] === timezone_version_get(), 'Local diagnostics failed.');
echo "PASS consumer: $checks checks; standalone PHP-only install, PSR-4, dates, zones and typed conversions.\n";