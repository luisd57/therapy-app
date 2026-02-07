<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Doctrine\Entity\InvitationTokenEntity;

final class InvitationTokenMapper
{
    public static function toDomain(InvitationTokenEntity $entity): InvitationToken
    {
        return InvitationToken::reconstitute(
            id: TokenId::fromString($entity->getId()),
            token: $entity->getToken(),
            email: Email::fromString($entity->getEmail()),
            patientName: $entity->getPatientName(),
            invitedBy: UserId::fromString($entity->getInvitedBy()),
            isUsed: $entity->isUsed(),
            createdAt: $entity->getCreatedAt(),
            expiresAt: $entity->getExpiresAt(),
            usedAt: $entity->getUsedAt(),
        );
    }

    public static function toEntity(InvitationToken $token, ?InvitationTokenEntity $entity = null): InvitationTokenEntity
    {
        if ($entity === null) {
            $entity = new InvitationTokenEntity();
        }

        $entity->setId($token->getId()->getValue());
        $entity->setToken($token->getToken());
        $entity->setEmail($token->getEmail()->getValue());
        $entity->setPatientName($token->getPatientName());
        $entity->setInvitedBy($token->getInvitedBy()->getValue());
        $entity->setIsUsed($token->isUsed());
        $entity->setCreatedAt($token->getCreatedAt());
        $entity->setExpiresAt($token->getExpiresAt());
        $entity->setUsedAt($token->getUsedAt());

        return $entity;
    }
}