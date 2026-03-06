<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\UpdatePatientProfileInputDTO;
use App\Application\User\DTO\Output\UserOutputDTO;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Address;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\Id\UserId;
use Symfony\Component\Clock\ClockInterface;

final readonly class UpdatePatientProfileHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdatePatientProfileInputDTO $dto): UserOutputDTO
    {
        $userId = UserId::fromString($dto->userId);
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new UserNotFoundException($dto->userId);
        }

        // Update phone if provided
        $phone = null;
        if ($dto->phone !== null) {
            $phone = Phone::fromString($dto->phone);
        }

        // Update address if any address field is provided
        $address = null;
        if ($dto->street !== null && $dto->city !== null && $dto->country !== null) {
            $address = Address::create(
                street: $dto->street,
                city: $dto->city,
                country: $dto->country,
                postalCode: $dto->postalCode,
                state: $dto->state,
            );
        }

        $user->updateProfile($phone, $address, $this->clock->now());
        $this->userRepository->save($user);

        return UserOutputDTO::fromEntity($user);
    }
}
