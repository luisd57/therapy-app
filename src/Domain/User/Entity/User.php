<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\Address;
use DateTimeImmutable;

class User
{
    private ?string $password = null;
    private ?Phone $phone = null;
    private ?Address $address = null;
    private bool $isActive = false;
    private ?DateTimeImmutable $activatedAt = null;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly UserId $id,
        private readonly Email $email,
        private readonly string $fullName,
        private readonly UserRole $role,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public static function createTherapist(
        UserId $id,
        Email $email,
        string $fullName,
        string $hashedPassword,
    ): self {
        $user = new self(
            id: $id,
            email: $email,
            fullName: $fullName,
            role: UserRole::THERAPIST,
            createdAt: new DateTimeImmutable(),
        );
        
        $user->password = $hashedPassword;
        $user->isActive = true;
        $user->activatedAt = new DateTimeImmutable();
        
        return $user;
    }

    public static function createPatient(
        UserId $id,
        Email $email,
        string $fullName,
    ): self {
        return new self(
            id: $id,
            email: $email,
            fullName: $fullName,
            role: UserRole::PATIENT,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function activate(string $hashedPassword): void
    {
        if ($this->isActive) {
            throw new \DomainException('User is already active.');
        }

        $this->password = $hashedPassword;
        $this->isActive = true;
        $this->activatedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updatePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateProfile(?Phone $phone, ?Address $address): void
    {
        if ($phone !== null) {
            $this->phone = $phone;
        }
        if ($address !== null) {
            $this->address = $address;
        }
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updatePhone(Phone $phone): void
    {
        $this->phone = $phone;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateAddress(Address $address): void
    {
        $this->address = $address;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    // Getters
    public function getId(): UserId
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPhone(): ?Phone
    {
        return $this->phone;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getActivatedAt(): ?DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isTherapist(): bool
    {
        return $this->role === UserRole::THERAPIST;
    }

    public function isPatient(): bool
    {
        return $this->role === UserRole::PATIENT;
    }

    public static function reconstitute(
        UserId $id,
        Email $email,
        string $fullName,
        UserRole $role,
        ?string $password,
        ?Phone $phone,
        ?Address $address,
        bool $isActive,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $activatedAt,
        DateTimeImmutable $updatedAt,
    ): self {
        $user = new self(
            id: $id,
            email: $email,
            fullName: $fullName,
            role: $role,
            createdAt: $createdAt,
        );

        $user->password = $password;
        $user->phone = $phone;
        $user->address = $address;
        $user->isActive = $isActive;
        $user->activatedAt = $activatedAt;
        $user->updatedAt = $updatedAt;

        return $user;
    }
}
