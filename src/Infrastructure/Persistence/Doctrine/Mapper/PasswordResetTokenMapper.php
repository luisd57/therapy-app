<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\User\Entity\PasswordResetToken;
use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Doctrine\Entity\PasswordResetTokenEntity;

final class PasswordResetTokenMapper
{
    public static function toDomain(PasswordResetTokenEntity $entity): PasswordResetToken
    {
        return PasswordResetToken::reconstitute(
            id: TokenId::fromString($entity->getId()),
            token: $entity->getToken(),
            userId: UserId::fromString($entity->getUserId()),
            isUsed: $entity->isUsed(),
            createdAt: $entity->getCreatedAt(),
            expiresAt: $entity->getExpiresAt(),
            usedAt: $entity->getUsedAt(),
        );
    }

    public static function toEntity(PasswordResetToken $token, ?PasswordResetTokenEntity $entity = null): PasswordResetTokenEntity
    {
        if ($entity === null) {
            $entity = new PasswordResetTokenEntity();
        }

        $entity->setId($token->getId()->getValue());
        $entity->setToken($token->getToken());
        $entity->setUserId($token->getUserId()->getValue());
        $entity->setIsUsed($token->isUsed());
        $entity->setCreatedAt($token->getCreatedAt());
        $entity->setExpiresAt($token->getExpiresAt());
        $entity->setUsedAt($token->getUsedAt());

        return $entity;
    }
}