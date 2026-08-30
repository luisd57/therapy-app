<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\User\ValueObject\Address;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\Timezone;
use App\Domain\User\Id\UserId;
use App\Domain\User\Enum\UserRole;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['email'], name: 'idx_users_email')]
#[ORM\Index(columns: ['role'], name: 'idx_users_role')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'phone', length: 50, nullable: true)]
    private ?Phone $phone = null;

    #[ORM\Embedded(class: Address::class)]
    private ?Address $address = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isActive = false;

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $activatedAt = null;

    #[ORM\Column(type: 'utc_datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * The patient's usual zone, so the therapist sees what time an appointment is for them.
     * Each appointment records its own zone too, since people travel.
     */
    #[ORM\Column(type: 'timezone', length: 64, nullable: true)]
    private ?Timezone $timezone = null;

    /**
     * Bounded: only a patient is ever linked to an Appointment, never the therapist.
     *
     * @var Collection<int, Appointment>
     */
    #[ORM\OneToMany(targetEntity: Appointment::class, mappedBy: 'patient')]
    private Collection $appointments;

    /** @var Collection<int, PasswordResetToken> */
    #[ORM\OneToMany(targetEntity: PasswordResetToken::class, mappedBy: 'user')]
    private Collection $passwordResetTokens;

    /**
     * Unbounded: the practice has one therapist, so this is every invitation ever sent.
     * EXTRA_LAZY keeps count/contains/slice in SQL. Iterating it still reads the table.
     *
     * @var Collection<int, InvitationToken>
     */
    #[ORM\OneToMany(targetEntity: InvitationToken::class, mappedBy: 'invitedBy', fetch: 'EXTRA_LAZY')]
    private Collection $sentInvitations;

    /** @var Collection<int, TherapistSchedule> */
    #[ORM\OneToMany(targetEntity: TherapistSchedule::class, mappedBy: 'therapist')]
    private Collection $scheduleBlocks;

    /**
     * Unbounded, same reasoning as sentInvitations.
     *
     * @var Collection<int, ScheduleException>
     */
    #[ORM\OneToMany(targetEntity: ScheduleException::class, mappedBy: 'therapist', fetch: 'EXTRA_LAZY')]
    private Collection $scheduleExceptions;

    public function __construct(
        // Not readonly: a readonly VO identifier breaks proxy initialization. See ADR-0007.
        #[ORM\Id]
        #[ORM\Column(type: 'user_id')]
        private UserId $id,
        #[ORM\Column(type: 'email', length: 255, unique: true)]
        private readonly Email $email,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private readonly string $fullName,
        #[ORM\Column(type: Types::STRING, length: 50, enumType: UserRole::class)]
        private readonly UserRole $role,
        #[ORM\Column(type: 'utc_datetime_immutable')]
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
        $this->appointments = new ArrayCollection();
        $this->passwordResetTokens = new ArrayCollection();
        $this->sentInvitations = new ArrayCollection();
        $this->scheduleBlocks = new ArrayCollection();
        $this->scheduleExceptions = new ArrayCollection();
    }

    // Symfony Security Interface Methods

    public function getUserIdentifier(): string
    {
        return $this->email->getValue();
    }

    public function getRoles(): array
    {
        return $this->role->getSecurityRoles();
    }

    public function eraseCredentials(): void
    {
    }

    #[ORM\PostLoad]
    public function nullifyEmptyEmbeddables(): void
    {
        if ($this->address !== null) {
            $ref = new \ReflectionProperty(Address::class, 'street');
            if (!$ref->isInitialized($this->address)) {
                $this->address = null;
            }
        }
    }

    public static function createTherapist(
        UserId $id,
        Email $email,
        string $fullName,
        string $hashedPassword,
        DateTimeImmutable $now,
    ): self {
        $user = new self(
            id: $id,
            email: $email,
            fullName: $fullName,
            role: UserRole::THERAPIST,
            createdAt: $now,
        );

        $user->password = $hashedPassword;
        $user->isActive = true;
        $user->activatedAt = $now;

        return $user;
    }

    public static function createPatient(
        UserId $id,
        Email $email,
        string $fullName,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            email: $email,
            fullName: $fullName,
            role: UserRole::PATIENT,
            createdAt: $now,
        );
    }

    public function activate(string $hashedPassword, DateTimeImmutable $now): void
    {
        if ($this->isActive) {
            throw new \DomainException('User is already active.');
        }

        $this->password = $hashedPassword;
        $this->isActive = true;
        $this->activatedAt = $now;
        $this->updatedAt = $now;
    }

    public function updatePassword(string $hashedPassword, DateTimeImmutable $now): void
    {
        $this->password = $hashedPassword;
        $this->updatedAt = $now;
    }

    public function updateProfile(
        ?Phone $phone,
        ?Address $address,
        DateTimeImmutable $now,
        ?Timezone $timezone = null,
    ): void {
        if ($phone !== null) {
            $this->phone = $phone;
        }
        if ($address !== null) {
            $this->address = $address;
        }
        if ($timezone !== null) {
            $this->timezone = $timezone;
        }
        $this->updatedAt = $now;
    }

    public function getTimezone(): ?Timezone
    {
        return $this->timezone;
    }

    public function updatePhone(Phone $phone, DateTimeImmutable $now): void
    {
        $this->phone = $phone;
        $this->updatedAt = $now;
    }

    public function updateAddress(Address $address, DateTimeImmutable $now): void
    {
        $this->address = $address;
        $this->updatedAt = $now;
    }

    public function deactivate(DateTimeImmutable $now): void
    {
        $this->isActive = false;
        $this->updatedAt = $now;
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

    /** @return Collection<int, Appointment> */
    public function getAppointments(): Collection
    {
        return $this->appointments;
    }

    /**
     * Read path for user-scoped lists is the repository, not this collection.
     * See "ORM Relations" in .claude/rules/api-architecture.md.
     *
     * @return Collection<int, PasswordResetToken>
     */
    public function getPasswordResetTokens(): Collection
    {
        return $this->passwordResetTokens;
    }

    /** @return Collection<int, InvitationToken> */
    public function getSentInvitations(): Collection
    {
        return $this->sentInvitations;
    }

    /** @return Collection<int, TherapistSchedule> */
    public function getScheduleBlocks(): Collection
    {
        return $this->scheduleBlocks;
    }

    /** @return Collection<int, ScheduleException> */
    public function getScheduleExceptions(): Collection
    {
        return $this->scheduleExceptions;
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
        ?Timezone $timezone = null,
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
        $user->timezone = $timezone;

        return $user;
    }
}
