# GalaxyDate

[English](README.md) · [ADR-001 — Архитектура](docs/adr/ADR-001-GalaxyDate.ru.md)

Календарные даты, дата/время, смена формата, зоны IANA и Unix timestamp.
Только PHP >=8.4. Пространство имён: `Galaxy\Date`. Пакет: `denetorus/galaxy-date`.
Runtime-зависимостей от других компонентов Galaxy нет.

## Установка

Установите версию 0.1.0 из [Packagist](https://packagist.org/packages/denetorus/galaxy-date):

```sh
composer require denetorus/galaxy-date:0.1.0
```

Выполните команду из каталога приложения. Дополнительные репозитории Composer и другие пакеты Galaxy не требуются.

## Начало работы

Сохраните как `example.php` в каталоге приложения:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Galaxy\Date\GDate;

$result = GDate::formatDate('2026-11-22', 'Y-m-d', ['format' => 'd.m.Y']);
if (!$result->isValid()) {
    throw new RuntimeException($result->reason);
}
echo $result->value(), PHP_EOL; // 22.11.2026

// Дата без времени означает полночь в явно заданной зоне.
$options = ['format' => 'Y-m-d', 'timezone' => 'Europe/Berlin'];
$object = GDate::toDateTime('2026-11-22', $options)->value();
assert($object instanceof DateTimeImmutable);
assert($object->format('Y-m-d H:i:sP') === '2026-11-22 00:00:00+01:00');
assert(GDate::fromDateTime($object, 'd.m.Y')->value() === '22.11.2026');

// Unix-секунды; при обратном преобразовании целевая зона указана явно.
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

Запуск: `php -d zend.assertions=1 -d assert.exception=1 example.php`.
Успешное значение — строка, `int` либо `DateTimeImmutable` в зависимости от метода.
Для внешних данных сначала проверьте `isValid()`, затем читайте `value()`; ошибка предоставляет `reason`.
Вызов `value()` при ошибке приводит к `LogicException`.
Неверные формат/настройки — ошибки конфигурации, вызывающие `InvalidArgumentException`.

## Правила и границы

Строка формата — сокращение `['format' => ...]`, без неявной зоны.
Валидация сохраняет исходную строку. Форматирование и пересчёт возвращают новое представление.
Объекты DateTime, переданные в `fromDateTime()`, не изменяются.

Поддерживается описанное подмножество обозначений PHP: дата, 24-часовое время, секунды, доли и смещение `P`.
Несуществующее местное время запрещено; повторное требует явного смещения, согласованного с зоной.
Преобразование в timestamp отклоняет ненулевые микросекунды с причиной `precision_loss`.
Сокращённый выходной формат может намеренно терять точность. Полный API и ограничения — в принятом ADR.

## Диагностика базы зон

`Galaxy\Date\Diagnostics\TimezoneDatabase::info()` читает версию локальной базы.
Явный `checkForUpdates()` проверяет последний стабильный PHP timezonedb по HTTPS.
Статусы: `up_to_date`, `update_available`, `unknown`.
Валидация и преобразования не обращаются к сети; автоматической установки обновлений нет.
Таймаут, системная база и ограничения источника обновлений описаны в ADR.

## Проверки разработки

Из каталога исходников GalaxyDate:

```sh
composer install
composer validate --strict
php vendor/bin/phpunit
composer dump-autoload --no-dev --optimize --strict-psr
```

CI настроен на PHP 8.4: PHPUnit, Composer, PSR-4 и независимый потребитель без dev-зависимостей.

## Лицензия

[MIT](LICENSE). Copyright (c) 2026 Mylnikov Denis.