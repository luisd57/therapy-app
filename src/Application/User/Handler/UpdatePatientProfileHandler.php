<?php

declare(strict_types=1);

namespace App\Application\User\Handler;

use App\Application\User\DTO\Input\UpdatePatientProfileInputDTO;
use App\Application\User\DTO\Output\UserDTO;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Address;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\UserId;

final readonly class UpdatePatientProfileHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function handle(UpdatePatientProfileInputDTO $input): UserDTO
    {
        $userId = UserId::fromString($input->userId);
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new UserNotFoundException($input->userId);
        }

        // Update phone if provided
        $phone = null;
        if ($input->phone !== null) {
            $phone = Phone::fromString($input->phone);
        }

        // Update address if any address field is provided
        $address = null;
        if ($input->street !== null && $input->city !== null && $input->country !== null) {
            $address = Address::create(
                street: $input->street,
                city: $input->city,
                country: $input->country,
                postalCode: $input->postalCode,
                state: $input->state,
            );
        }

        $user->updateProfile($phone, $address);
        $this->userRepository->save($user);

        return UserDTO::fromEntity($user);
    }
}
