<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Address;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use App\Tests\Helper\DomainTestHelper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testCreateTherapistSetsCorrectProperties(): void
    {
        $user = DomainTestHelper::createTherapist();

        $this->assertSame(UserRole::THERAPIST, $user->getRole());
        $this->assertTrue($user->isActive());
        $this->assertNotNull($user->getPassword());
        $this->assertNotNull($user->getActivatedAt());
        $this->assertTrue($user->isTherapist());
        $this->assertFalse($user->isPatient());
    }

    public function testCreatePatientSetsCorrectProperties(): void
    {
        $user = DomainTestHelper::createPatient();

        $this->assertSame(UserRole::PATIENT, $user->getRole());
        $this->assertFalse($user->isActive());
        $this->assertNull($user->getPassword());
        $this->assertNull($user->getActivatedAt());
        $this->assertNull($user->getPhone());
        $this->assertNull($user->getAddress());
        $this->assertTrue($user->isPatient());
        $this->assertFalse($user->isTherapist());
    }

    public function testActivateInactivePatient(): void
    {
        $user = DomainTestHelper::createPatient();

        $user->activate('hashed_password');

        $this->assertTrue($user->isActive());
        $this->assertSame('hashed_password', $user->getPassword());
        $this->assertNotNull($user->getActivatedAt());
    }

    public function testActivateAlreadyActiveUserThrowsDomainException(): void
    {
        $user = DomainTestHelper::createTherapist();

        $this->expectException(\DomainException::class);
        $user->activate('another_password');
    }

    public function testUpdatePassword(): void
    {
        $user = DomainTestHelper::createTherapist();
        $oldUpdatedAt = $user->getUpdatedAt();

        usleep(1000);
        $user->updatePassword('new_hashed_password');

        $this->assertSame('new_hashed_password', $user->getPassword());
        $this->assertGreaterThanOrEqual($oldUpdatedAt, $user->getUpdatedAt());
    }

    public function testUpdateProfileWithPhoneOnly(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $phone = Phone::fromString('+1234567890');

        $user->updateProfile($phone, null);

        $this->assertNotNull($user->getPhone());
        $this->assertSame('+1234567890', $user->getPhone()->getValue());
        $this->assertNull($user->getAddress());
    }

    public function testUpdateProfileWithAddressOnly(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $address = Address::create('123 Main St', 'Springfield', 'USA');

        $user->updateProfile(null, $address);

        $this->assertNull($user->getPhone());
        $this->assertNotNull($user->getAddress());
        $this->assertSame('123 Main St', $user->getAddress()->getStreet());
    }

    public function testUpdateProfileWithNullDoesNotOverwriteExistingPhone(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $phone = Phone::fromString('+1234567890');
        $user->updateProfile($phone, null);

        $user->updateProfile(null, null);

        $this->assertNotNull($user->getPhone());
        $this->assertSame('+1234567890', $user->getPhone()->getValue());
    }

    public function testUpdatePhone(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $phone = Phone::fromString('+9876543210');

        $user->updatePhone($phone);

        $this->assertSame('+9876543210', $user->getPhone()->getValue());
    }

    public function testUpdateAddress(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $address = Address::create('456 Oak Ave', 'Portland', 'USA', '97201', 'OR');

        $user->updateAddress($address);

        $this->assertTrue($address->equals($user->getAddress()));
    }

    public function testDeactivate(): void
    {
        $user = DomainTestHelper::createTherapist();
        $this->assertTrue($user->isActive());

        $user->deactivate();

        $this->assertFalse($user->isActive());
    }

    public function testReconstituteRestoresAllProperties(): void
    {
        $id = UserId::generate();
        $email = Email::fromString('test@example.com');
        $phone = Phone::fromString('+1234567890');
        $address = Address::create('123 Main', 'City', 'Country');
        $createdAt = new DateTimeImmutable('-1 day');
        $activatedAt = new DateTimeImmutable('-12 hours');
        $updatedAt = new DateTimeImmutable();

        $user = User::reconstitute(
            id: $id,
            email: $email,
            fullName: 'Test User',
            role: UserRole::PATIENT,
            password: 'hashed_pw',
            phone: $phone,
            address: $address,
            isActive: true,
            createdAt: $createdAt,
            activatedAt: $activatedAt,
            updatedAt: $updatedAt,
        );

        $this->assertTrue($id->equals($user->getId()));
        $this->assertTrue($email->equals($user->getEmail()));
        $this->assertSame('Test User', $user->getFullName());
        $this->assertSame(UserRole::PATIENT, $user->getRole());
        $this->assertSame('hashed_pw', $user->getPassword());
        $this->assertTrue($phone->equals($user->getPhone()));
        $this->assertTrue($address->equals($user->getAddress()));
        $this->assertTrue($user->isActive());
        $this->assertSame($createdAt, $user->getCreatedAt());
        $this->assertSame($activatedAt, $user->getActivatedAt());
        $this->assertSame($updatedAt, $user->getUpdatedAt());
    }
}
