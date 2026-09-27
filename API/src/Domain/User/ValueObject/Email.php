<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

final readonly class Email
{
    /**
     * @param non-empty-string $value
     */
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = filter_var(strtolower(trim($value)), FILTER_VALIDATE_EMAIL);

        if ($normalized === false) {
            throw new \InvalidArgumentException('Invalid email format.');
        }

        return new self($normalized);
    }

    /**
     * @return non-empty-string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
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
