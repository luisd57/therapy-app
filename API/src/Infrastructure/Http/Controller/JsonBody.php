<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * A JSON request body read by type. A value of the wrong type reads as absent, so it fails
 * validation with a 422 instead of reaching a typed constructor and throwing a 500.
 */
final class JsonBody
{
    /**
     * @param array<mixed> $data
     */
    private function __construct(private readonly array $data) {}

    public static function fromRequest(Request $request): self
    {
        $decoded = json_decode($request->getContent(), true);

        return new self(is_array($decoded) ? $decoded : []);
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /** Present with a value that is not a string. Optional fields check this so a bad value is not dropped. */
    public function hasNonString(string $key): bool
    {
        return $this->has($key) && $this->optionalString($key) === null;
    }

    /** Present with a value that is not a boolean. */
    public function hasNonBool(string $key): bool
    {
        return $this->has($key) && $this->bool($key) === null;
    }

    /** Empty string when absent or not a string, which is what NotBlank reports as missing. */
    public function string(string $key): string
    {
        return $this->optionalString($key) ?? '';
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function bool(string $key): ?bool
    {
        $value = $this->data[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /** Accepts a JSON integer or a string of digits. */
    public function int(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    /** A nested JSON object, or null when absent or not an object. */
    public function object(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        return is_array($value) ? new self($value) : null;
    }
}
