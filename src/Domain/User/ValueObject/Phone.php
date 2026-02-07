<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

final readonly class Phone
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        // Remove all non-numeric characters except +
        $normalized = preg_replace('/[^0-9+]/', '', $value);
        
        if ($normalized === null || strlen($normalized) < 7 || strlen($normalized) > 20) {
            throw new \InvalidArgumentException("Invalid phone number format: {$value}");
        }

        return new self($normalized);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
