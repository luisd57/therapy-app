<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Output;

use App\Domain\User\ValueObject\Address;

final readonly class AddressDTO
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country,
        public ?string $postalCode,
        public ?string $state,
    ) {
    }

    public static function fromValueObject(Address $address): self
    {
        return new self(
            street: $address->getStreet(),
            city: $address->getCity(),
            country: $address->getCountry(),
            postalCode: $address->getPostalCode(),
            state: $address->getState(),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
            'state' => $this->state,
        ];
    }
}
