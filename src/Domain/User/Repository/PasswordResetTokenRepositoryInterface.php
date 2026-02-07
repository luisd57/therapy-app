<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\PasswordResetToken;
use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;

interface PasswordResetTokenRepositoryInterface
{
    public function save(PasswordResetToken $token): void;

    public function findById(TokenId $id): ?PasswordResetToken;

    public function findByToken(string $token): ?PasswordResetToken;

    public function findValidByUserId(UserId $userId): ?PasswordResetToken;

    public function delete(PasswordResetToken $token): void;

    public function deleteExpired(): int;

    public function invalidateAllForUser(UserId $userId): void;
}
