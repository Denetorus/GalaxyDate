# ADR-001 — GalaxyDate

**Status: Accepted**

[Русский](ADR-001-GalaxyDate.ru.md)

## Purpose and API

An independent component for calendar dates, local date/time, offsets and zones.
PHP >=8.4 only. Namespace: Galaxy\Date. Package: denetorus/galaxy-date.
License: [MIT](../../LICENSE), Copyright (c) 2026 Mylnikov Denis. No dependency on GalaxyValidation or GalaxyResult.

- GDate::validateDate(mixed $value, string|array $options): DateResult.
- GDate::validateDateTime(mixed $value, string|array $options): DateResult.
- GDate::checkDateOptions(string|array $options): void.
- GDate::checkDateTimeOptions(string|array $options): void.

check*Options validate configuration and throw InvalidArgumentException on invalid options.
validate* also always check configuration; non-string values produce type_mismatch.
DateResult is readonly: isValid(): bool, public ?string reason, value(): string|int|DateTimeImmutable.
Success contains a string, PHP int or DateTimeImmutable, depending on the operation.
Failure contains a reason only; value() throws LogicException on failure.
Static success(string|int|DateTimeImmutable)/failure(string) construct results; empty reasons are rejected.
Debug information contains only reason.
Lists, required parameters, business codes and ValidationViolation belong to consumers.
Calendar validation never assigns a time or zone; explicit conversions may interpret a date as midnight. Validation and conversion are local and offline.

## Calendar contract

### Calendar dates

validateDate requires exactly one option: ['format' => 'Y-m-d'].
A missing, empty, non-string or unsupported format is a configuration error (InvalidArgumentException).

Formats use PHP calendar tokens: Y/y (year), m/n/M/F (month), d/j (day).
Exactly one token from each group is required; order is arbitrary.
Examples: Y-m-d, d.m.Y, Y/m/d, Y-n-j, Ymd, d M Y.
Y is a four-digit year; y uses PHP's two-digit-year interpretation (00–69: 2000–2069; 70–99: 1970–1999).
Y is recommended for storage and interchange to avoid two-digit-year ambiguity.
M/F use PHP's English month names; localized month names are not supported. Separators are literal and must match exactly.
A backslash escapes the following literal character. Trailing escapes and control characters are invalid.
Time/timezone tokens, ISO week dates, day-of-year formats, parser controls (!, |, +, #, ?, *) and duplicate date fields are not supported.
Escape reserved characters to use them as literals.

Parsing is independent of the current date and default timezone (reset fields and UTC internally).
Warnings/errors, impossible Gregorian dates (including year zero) and formatting back to a different
string are rejected with invalid_date. No trimming, rollover or format guessing occurs.
Leading zeros and letter case must match the selected format exactly.
Success returns the original string, never a DateTime object.

### Datetime, offsets and named zones

validateDateTime requires a nonempty format option.
Calendar tokens are the same as date, followed or preceded as needed by H/G (24-hour clock)
and i (minutes). Exactly one date, hour and minute are required.
Seconds s are optional; fractions u (six digits) or v (three digits) require seconds.
Duplicate fields, 12-hour clock and unspecified timezone tokens are unsupported.
P optionally represents one explicit offset in the input, exactly ±HH:MM.
Literal T must be escaped: Y-m-d\TH:i:sP.
The value must be a string; success preserves it without conversion.
Invalid dates, 24:00:00, minute/second 60, wrong widths and trailing data fail with invalid_datetime.

Optional offset supplies a fixed offset when P is absent.
It must be ±HH:MM within -14:00..+14:00, with minutes 00..59; at either limit minutes must be 00.
+00:00 and -00:00 both mean zero here. Z is not accepted as a substitute for P.
P and options.offset together are a configuration error.
An out-of-range input offset produces invalid_offset; an invalid option throws InvalidArgumentException.
Fixed offsets do not have seasonal transitions.

Optional timezone is an exact IANA identifier from the installed PHP database (a name containing "/"),
or UTC. Bare abbreviations such as CET and numeric offsets are not accepted as timezone.
For example: Europe/Berlin, America/New_York, Australia/Lord_Howe.
Without offset or timezone, only calendar date/time is checked; the server default timezone is never used.

With timezone, all possible instants for the local calendar value are checked against that zone:
- No possible instant: nonexistent_local_time; no automatic forward adjustment.
- Multiple instants without an explicit offset: ambiguous_local_time.
- An explicit offset from P or options.offset must select a matching instant, otherwise timezone_offset_mismatch.
- One instant without offset, or a matching explicit offset: success.

timezone may be combined with options.offset or an input P offset, but not both sources of offset.
No timezone conversion or DateTime object is returned. Zone/offset options are not inserted into the result.
Timezone rules come from PHP's installed timezone database; keep it updated.
Historical and future results depend on that database. Transitions are not assumed to be one hour.

The implementation helper Internal\DateTimeValidator is internal; the supported entry point is GDate.


## Formatting and timezone conversion

- GDate::formatDate(mixed $value, string|array $source, string|array $target): DateResult.
- GDate::formatDateTime(mixed $value, string|array $source, string|array $target): DateResult.
- GDate::convertTimezone(mixed $value, string|array $source, string|array $target): DateResult.

source follows the calendar and zone rules below; conversion also supports a date-only source as midnight.
All configuration is checked before processing the value; invalid source or output configuration throws
InvalidArgumentException. Invalid input returns the existing reason in DateResult.

formatDate changes only the calendar representation; timezone settings are not supported.
Without a target zone, formatDateTime retains the source local time and zone; a target zone requests conversion.
A local value with no zone/offset may be reformatted without a zone, but cannot emit P: timezone_required.
Unspecified seconds and fractions are zero. Omitting fields in the output can lose precision
(including century information with y); no round-trip guarantee is made for reduced formats.

convertTimezone requires an unambiguous source moment: timezone and/or explicit offset under the existing rules.
A source without either returns timezone_required; the default PHP timezone is never substituted.
target requires format and optionally one timezone (IANA/UTC) or offset (fixed ±HH:MM); absence preserves the source zone.
The target format may contain P even when target.offset is set: P emits that offset rather than supplying input.
Named target zones apply the rules at the source instant, including DST.
Nonexistent/ambiguous source values and mismatched source offsets keep their existing failure reasons.
Microseconds are preserved internally; u emits six digits and v emits three.

Conversion changes the presentation zone, not the moment. It may cross calendar days/months/years.
Target Gregorian years outside 0001..9999 return datetime_out_of_range.
If P cannot represent an offset exactly (seconds or outside ±14:00), unrepresentable_offset is returned.
An output without P may be ambiguous when read again during a repeated hour; include P to retain the offset.
No adjustment of a nonexistent source time, implicit source zone or network request is performed.

DateResult.value() contains the operation result: unchanged string for validation, formatted/converted string
for these methods. The typed methods may return DateTimeImmutable; string methods do not.


```php
$formatted = GDate::formatDate('2024-02-29', ['format' => 'Y-m-d'], 'd.m.Y');
assert($formatted->value() === '29.02.2024');

$local = GDate::formatDateTime(
    '2026-11-22 14:30:01',
    ['format' => 'Y-m-d H:i:s'],
    'd.m.Y H:i',
);
assert($local->value() === '22.11.2026 14:30');

$converted = GDate::convertTimezone(
    '2024-07-01 12:00:00',
    ['format' => 'Y-m-d H:i:s', 'timezone' => 'Europe/Berlin'],
    ['format' => 'Y-m-d H:i:sP', 'timezone' => 'UTC'],
);
assert($converted->value() === '2024-07-01 10:00:00+00:00');
```


## Objects, timestamps and format shorthand

All public format/options arguments accept string|array. A string is exactly shorthand for
['format' => $string], without a default zone or offset. Both source and target can use either form.
validateDateTime still requires time in its format; only conversion/formatting operations support a date-only source.

- GDate::toDateTime(mixed $value, string|array $source): DateResult — DateTimeImmutable.
- GDate::toTimestamp(mixed $value, string|array $source): DateResult — PHP int, Unix seconds.
- GDate::fromDateTime(mixed $value, string|array $target): DateResult — formatted string.
- GDate::fromTimestamp(mixed $value, string|array $target): DateResult — formatted string.

toDateTime/toTimestamp require an unambiguous source zone or offset (including input P).
A date-only format means 00:00:00.000000 in the explicitly specified zone.
The original date format still must match the string exactly: no missing time is accepted when the format includes time.
Midnight is checked for gaps, repeated intervals and offset consistency exactly like any other local time.
A date-only source specifies its timezone/offset in options, not in the date format.
The default server timezone is never used.

toDateTime preserves the zone and microseconds.
toTimestamp returns integral seconds only: nonzero microseconds produce precision_loss, never implicit rounding.
Epoch zero and negative timestamps are supported; PHP integer limits apply (timestamp_out_of_range).
fromTimestamp accepts only PHP int (not numeric strings, floats or bool), and requires a target timezone or offset.
fromDateTime accepts DateTimeInterface (DateTime or DateTimeImmutable), uses an immutable copy,
and never changes the caller's object. Without a target zone it retains the object's zone.

Formatting datetime with a target zone now also performs zone conversion.
A target without a zone, including a string format, retains the source zone; local source values remain local
unless the operation requires a known instant (convertTimezone/toDateTime/toTimestamp or emitting P).
convertTimezone without a target zone retains a known source zone, but still requires a known source instant.
Output may use a calendar-only format. Pure formatDate supports format only, not timezone options.

DateResult::success and value() support string|int|DateTimeImmutable.
The successful value type is determined by the method; validation still returns the original string.
Failures and debug information do not expose a value.
No new runtime dependency or network call is introduced.


```php
$midnight = GDate::toDateTime(
    '2026-11-22',
    ['format' => 'Y-m-d', 'timezone' => 'Europe/Berlin'],
);
assert($midnight->value() instanceof DateTimeImmutable);
assert($midnight->value()->format('Y-m-d H:i:sP') === '2026-11-22 00:00:00+01:00');

$timestamp = GDate::toTimestamp(
    '2026-11-22',
    ['format' => 'Y-m-d', 'timezone' => 'Europe/Berlin'],
);
assert(is_int($timestamp->value()));

$display = GDate::fromTimestamp(
    $timestamp->value(),
    ['format' => 'd.m.Y H:i:sP', 'timezone' => 'Europe/Berlin'],
);
assert($display->value() === '22.11.2026 00:00:00+01:00');
assert(GDate::fromDateTime($midnight->value(), 'd.m.Y')->value() === '22.11.2026');

assert(GDate::formatDate('2026-11-22', 'Y-m-d', ['format' => 'd.m.Y'])->value() === '22.11.2026');
```

## Timezone database diagnostics

Galaxy\Date\Diagnostics\TimezoneDatabase is an optional administrative tool, separate from validation.
info(): array reads only the current PHP process: installedVersion (timezone_version_get),
databaseSource (bundled, extension or system), phpVersion and defaultTimezone.
checkForUpdates(float $timeoutSeconds = 5.0): array explicitly requests the latest stable PHP timezonedb
release from https://pecl.php.net/rest/r/timezonedb/stable.txt.
The timeout must be finite, greater than zero and at most 30 seconds; it is a stream I/O timeout,
not a hard limit for the entire operation including DNS.

The report includes local info plus availableVersion (string or null), status, reason, checkedAt (UTC)
and source. Status is update_available when the stable version is newer; otherwise up_to_date.
up_to_date means no newer version was reported by this source, not proof of all future timezone rules.
unknown is returned for failed network/HTTP/TLS access, invalid responses or uncomparable installed versions.
Reasons are network_unavailable, invalid_response or installed_version_unknown.
A system database reported as 0.system cannot be compared to PHP release numbers automatically.
PHP-style YYYY.increment[.patch] versions are compared numerically, not lexicographically.

Only this method accesses the network. It uses HTTPS verification, no redirects, an at-most-128-byte
release body and no persistent cache. Missing HTTPS support or disabled allow_url_fopen yields unknown.
No installation, file downloads to disk, PHP configuration changes or timezone changes are performed.
GDate never invokes diagnostics. Applications choose when and whether to perform the check.
Update PHP or the timezone database separately using the deployment process for the host environment.

## Verification and boundaries

Tests independently check dates, datetime, zones and diagnostics without GalaxyValidation.
GalaxyValidation retains integration tests for arrays, types and mapping reasons to violations.
The API and the first-version limits in this ADR are accepted. License is MIT. Publication requires a separate decision.