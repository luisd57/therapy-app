<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\UserId;
use App\Domain\User\Enum\UserRole;
use Doctrine\Common\Collections\ArrayCollection;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    /** @throws UserNotFoundException */
    public function getByIdOrFail(UserId $id): User;

    public function findByEmail(Email $email): ?User;

    public function existsByEmail(Email $email): bool;

    /**
     * @return ArrayCollection<int, User>
     */
    public function findByRole(UserRole $role): ArrayCollection;

    /**
     * @return ArrayCollection<int, User>
     */
    public function findActivePatients(): ArrayCollection;

    /**
     * @return ArrayCollection<int, User>
     */
    public function findActivePatientsPaginated(int $offset, int $limit): ArrayCollection;

    public function countActivePatients(): int;

    /**
     * The ON DELETE rules in the migrations do the cascading, not Doctrine.
     * Flush anything else you are holding first: an unflushed entity pointing at this user
     * makes the flush throw rather than delete.
     */
    public function delete(User $user): void;

    /**
     * Returns the single therapist user in the system.
     * Throws rather than returning null, because exactly one therapist is a system invariant.
     *
     * @throws \RuntimeException if zero or more than one therapist exists
     */
    public function findSingleTherapist(): User;
}
