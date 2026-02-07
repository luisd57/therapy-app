<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Address;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use App\Infrastructure\Persistence\Doctrine\Entity\UserEntity;

final class UserMapper
{
    public static function toDomain(UserEntity $entity): User
    {
        $phone = $entity->getPhone() !== null
            ? Phone::fromString($entity->getPhone())
            : null;

        $address = null;
        if (
            $entity->getAddressStreet() !== null
            && $entity->getAddressCity() !== null
            && $entity->getAddressCountry() !== null
        ) {
            $address = Address::create(
                street: $entity->getAddressStreet(),
                city: $entity->getAddressCity(),
                country: $entity->getAddressCountry(),
                postalCode: $entity->getAddressPostalCode(),
                state: $entity->getAddressState(),
            );
        }

        return User::reconstitute(
            id: UserId::fromString($entity->getId()),
            email: Email::fromString($entity->getEmail()),
            fullName: $entity->getFullName(),
            role: UserRole::from($entity->getRole()),
            password: $entity->getPassword(),
            phone: $phone,
            address: $address,
            isActive: $entity->isActive(),
            createdAt: $entity->getCreatedAt(),
            activatedAt: $entity->getActivatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public static function toEntity(User $user, ?UserEntity $entity = null): UserEntity
    {
        if ($entity === null) {
            $entity = new UserEntity();
        }

        $entity->setId($user->getId()->getValue());
        $entity->setEmail($user->getEmail()->getValue());
        $entity->setFullName($user->getFullName());
        $entity->setRole($user->getRole()->value);
        $entity->setPassword($user->getPassword());
        $entity->setPhone($user->getPhone()?->getValue());

        if ($user->getAddress() !== null) {
            $entity->setAddressStreet($user->getAddress()->getStreet());
            $entity->setAddressCity($user->getAddress()->getCity());
            $entity->setAddressState($user->getAddress()->getState());
            $entity->setAddressPostalCode($user->getAddress()->getPostalCode());
            $entity->setAddressCountry($user->getAddress()->getCountry());
        }

        $entity->setIsActive($user->isActive());
        $entity->setCreatedAt($user->getCreatedAt());
        $entity->setActivatedAt($user->getActivatedAt());
        $entity->setUpdatedAt($user->getUpdatedAt());

        return $entity;
    }
}
