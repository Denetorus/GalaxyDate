<?php
declare(strict_types=1);

namespace Galaxy\Date;

final readonly class DateResult
{

    private function __construct(public ?string $reason, private string|int|\DateTimeImmutable|null $value)
    {
    }

    public static function success(string|int|\DateTimeImmutable $value): self
    {
        return new self(null, $value);
    }

    public static function failure(string $reason): self
    {
        if ($reason === '') {
            throw new \InvalidArgumentException('Failure requires a reason.');
        }
        return new self($reason, null);
    }

    public function isValid(): bool
    {
        return $this->reason === null;
    }

    public function value(): string|int|\DateTimeImmutable
    {
        if (!$this->isValid()) {
            throw new \LogicException('Invalid date result has no value.');
        }
        return $this->value;
    }

    public function __debugInfo(): array
    {
        return ['reason' => $this->reason];
    }

}