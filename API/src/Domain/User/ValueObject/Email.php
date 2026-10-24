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

        $email = new self($normalized);

        // An IP-literal domain would let a public form point outgoing mail at any host.
        if (str_starts_with($email->getDomain(), '[')) {
            throw new \InvalidArgumentException('Invalid email format.');
        }

        return $email;
    }

    /**
     * @return non-empty-string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /** Splits on the last "@", because a quoted local part may contain one. */
    public function getDomain(): string
    {
        return substr($this->value, strrpos($this->value, '@') + 1);
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
