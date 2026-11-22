<?php
declare(strict_types=1);

namespace Galaxy\Date\Tests;

use Galaxy\Date\{DateResult, GDate};
use PHPUnit\Framework\TestCase;

final class DateResultTest extends TestCase
{

    public function testResultDoesNotExposeFailedOrDebugValues(): void
    {

        $success = DateResult::success('2026-11-22');
        self::assertTrue($success->isValid());
        self::assertNull($success->reason);
        self::assertSame('2026-11-22', $success->value());
        self::assertSame(['reason' => null], $success->__debugInfo());
        $failed = GDate::validateDate('2026-02-30', ['format' => 'Y-m-d']);
        self::assertFalse($failed->isValid());
        self::assertSame('invalid_date', $failed->reason);
        self::assertSame(['reason' => 'invalid_date'], $failed->__debugInfo());
        $this->expectException(\LogicException::class);
        $failed->value();

    }

    public function testEmptyFailureIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DateResult::failure('');
    }

    public function testDirectCallChecksConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GDate::validateDateTime('2026-11-22', ['format' => 'Y-m-d']);
    }

}