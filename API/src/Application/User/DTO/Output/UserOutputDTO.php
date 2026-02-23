<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Output;

use App\Domain\User\Entity\User;

final readonly class UserOutputDTO
{
    public function __construct(
        public string $id,
        public string $email,
        public string $fullName,
        public string $role,
        public bool $isActive,
        public ?string $phone,
        public ?AddressOutputDTO $address,
        public string $createdAt,
        public ?string $activatedAt,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId()->getValue(),
            email: $user->getEmail()->getValue(),
            fullName: $user->getFullName(),
            role: $user->getRole()->value,
            isActive: $user->isActive(),
            phone: $user->getPhone()?->getValue(),
            address: $user->getAddress()
                ? AddressOutputDTO::fromValueObject($user->getAddress())
                : null,
            createdAt: $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            activatedAt: $user->getActivatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'full_name' => $this->fullName,
            'role' => $this->role,
            'is_active' => $this->isActive,
            'phone' => $this->phone,
            'address' => $this->address?->toArray(),
            'created_at' => $this->createdAt,
            'activated_at' => $this->activatedAt,
        ];
    }
}
