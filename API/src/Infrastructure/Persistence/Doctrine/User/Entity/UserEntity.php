<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User\Entity;

use App\Domain\User\ValueObject\UserRole;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['email'], name: 'idx_users_email')]
#[ORM\Index(columns: ['role'], name: 'idx_users_role')]
class UserEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $fullName;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $role;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $addressStreet = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $addressCity = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $addressState = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $addressPostalCode = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $addressCountry = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $activatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    // Symfony Security Interface Methods
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return UserRole::from($this->role)->getSecurityRoles();
    }

    public function eraseCredentials(): void
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAddressStreet(): ?string
    {
        return $this->addressStreet;
    }

    public function getAddressCity(): ?string
    {
        return $this->addressCity;
    }

    public function getAddressState(): ?string
    {
        return $this->addressState;
    }

    public function getAddressPostalCode(): ?string
    {
        return $this->addressPostalCode;
    }

    public function getAddressCountry(): ?string
    {
        return $this->addressCountry;
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

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setFullName(string $fullName): void
    {
        $this->fullName = $fullName;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function setAddressStreet(?string $addressStreet): void
    {
        $this->addressStreet = $addressStreet;
    }

    public function setAddressCity(?string $addressCity): void
    {
        $this->addressCity = $addressCity;
    }

    public function setAddressState(?string $addressState): void
    {
        $this->addressState = $addressState;
    }

    public function setAddressPostalCode(?string $addressPostalCode): void
    {
        $this->addressPostalCode = $addressPostalCode;
    }

    public function setAddressCountry(?string $addressCountry): void
    {
        $this->addressCountry = $addressCountry;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setActivatedAt(?DateTimeImmutable $activatedAt): void
    {
        $this->activatedAt = $activatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}