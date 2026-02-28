<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Address
{
    private function __construct(
        #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
        private string $street,
        #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
        private string $city,
        #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
        private string $country,
        #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
        private ?string $postalCode = null,
        #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
        private ?string $state = null,
    ) {
    }

    public static function create(
        string $street,
        string $city,
        string $country,
        ?string $postalCode = null,
        ?string $state = null,
    ): self {
        $street = trim($street);
        $city = trim($city);
        $country = trim($country);
        
        if (empty($street)) {
            throw new \InvalidArgumentException('Street cannot be empty.');
        }

        if (empty($city)) {
            throw new \InvalidArgumentException('City cannot be empty.');
        }

        if (empty($country)) {
            throw new \InvalidArgumentException('Country cannot be empty.');
        }

        return new self(
            street: $street,
            city: $city,
            country: $country,
            postalCode: $postalCode ? trim($postalCode) : null,
            state: $state ? trim($state) : null,
        );
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getFullAddress(): string
    {
        $parts = [$this->street];
        
        if ($this->state) {
            $parts[] = "{$this->city}, {$this->state}";
        } else {
            $parts[] = $this->city;
        }
        
        if ($this->postalCode) {
            $parts[] = $this->postalCode;
        }
        
        $parts[] = $this->country;
        
        return implode(', ', $parts);
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->street === $other->street
            && $this->city === $other->city
            && $this->country === $other->country
            && $this->postalCode === $other->postalCode
            && $this->state === $other->state;
    }

    public function __toString(): string
    {
        return $this->getFullAddress();
    }
}
