<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\User\ValueObject\Address;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use PHPUnit\Framework\TestCase;

final class ValueObjectTest extends TestCase
{
    // --- Email ---

    public function testEmailFromStringWithValidEmail(): void
    {
        $email = Email::fromString('user@example.com');
        $this->assertSame('user@example.com', $email->getValue());
    }

    public function testEmailNormalizesToLowercase(): void
    {
        $email = Email::fromString('USER@EXAMPLE.COM');
        $this->assertSame('user@example.com', $email->getValue());
    }

    public function testEmailTrimsWhitespace(): void
    {
        $email = Email::fromString('  user@example.com  ');
        $this->assertSame('user@example.com', $email->getValue());
    }

    public function testEmailInvalidFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Email::fromString('not-an-email');
    }

    public function testEmailEmptyStringThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Email::fromString('');
    }

    public function testEmailGetDomain(): void
    {
        $email = Email::fromString('user@example.com');
        $this->assertSame('example.com', $email->getDomain());
    }

    public function testEmailEqualsWithSameEmail(): void
    {
        $email1 = Email::fromString('user@example.com');
        $email2 = Email::fromString('user@example.com');
        $this->assertTrue($email1->equals($email2));
    }

    public function testEmailEqualsWithDifferentCase(): void
    {
        $email1 = Email::fromString('user@example.com');
        $email2 = Email::fromString('USER@EXAMPLE.COM');
        $this->assertTrue($email1->equals($email2));
    }

    public function testEmailEqualsWithDifferentEmail(): void
    {
        $email1 = Email::fromString('user1@example.com');
        $email2 = Email::fromString('user2@example.com');
        $this->assertFalse($email1->equals($email2));
    }

    public function testEmailToString(): void
    {
        $email = Email::fromString('User@Example.com');
        $this->assertSame('user@example.com', (string) $email);
    }

    // --- Phone ---

    public function testPhoneFromStringWithValidPhone(): void
    {
        $phone = Phone::fromString('+1234567890');
        $this->assertSame('+1234567890', $phone->getValue());
    }

    public function testPhoneStripsNonNumericExceptPlus(): void
    {
        $phone = Phone::fromString('+1 (234) 567-8901');
        $this->assertSame('+12345678901', $phone->getValue());
    }

    public function testPhoneMinimumLengthValid(): void
    {
        $phone = Phone::fromString('1234567');
        $this->assertSame('1234567', $phone->getValue());
    }

    public function testPhoneMaximumLengthValid(): void
    {
        $phone = Phone::fromString('12345678901234567890');
        $this->assertSame('12345678901234567890', $phone->getValue());
    }

    public function testPhoneTooShortThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Phone::fromString('123456');
    }

    public function testPhoneTooLongThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Phone::fromString('123456789012345678901');
    }

    public function testPhoneEquals(): void
    {
        $phone1 = Phone::fromString('+1-234-567-8901');
        $phone2 = Phone::fromString('+1 (234) 567.8901');
        $this->assertTrue($phone1->equals($phone2));
    }

    // --- Address ---

    public function testAddressCreateWithAllFields(): void
    {
        $address = Address::create('123 Main St', 'Springfield', 'USA', '62701', 'IL');
        $this->assertSame('123 Main St', $address->getStreet());
        $this->assertSame('Springfield', $address->getCity());
        $this->assertSame('USA', $address->getCountry());
        $this->assertSame('62701', $address->getPostalCode());
        $this->assertSame('IL', $address->getState());
    }

    public function testAddressCreateWithRequiredFieldsOnly(): void
    {
        $address = Address::create('123 Main St', 'Springfield', 'USA');
        $this->assertNull($address->getPostalCode());
        $this->assertNull($address->getState());
    }

    public function testAddressEmptyStreetThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Address::create('', 'Springfield', 'USA');
    }

    public function testAddressEmptyCityThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Address::create('123 Main St', '', 'USA');
    }

    public function testAddressEmptyCountryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Address::create('123 Main St', 'Springfield', '');
    }

    public function testAddressTrimsWhitespace(): void
    {
        $address = Address::create('  123 Main St  ', '  Springfield  ', '  USA  ');
        $this->assertSame('123 Main St', $address->getStreet());
        $this->assertSame('Springfield', $address->getCity());
        $this->assertSame('USA', $address->getCountry());
    }

    public function testAddressGetFullAddressWithState(): void
    {
        $address = Address::create('123 Main St', 'Springfield', 'USA', '62701', 'IL');
        $this->assertSame('123 Main St, Springfield, IL, 62701, USA', $address->getFullAddress());
    }

    public function testAddressGetFullAddressWithoutState(): void
    {
        $address = Address::create('123 Main St', 'Springfield', 'USA', '62701');
        $this->assertSame('123 Main St, Springfield, 62701, USA', $address->getFullAddress());
    }

    public function testAddressGetFullAddressWithoutPostalCode(): void
    {
        $address = Address::create('123 Main St', 'Springfield', 'USA');
        $this->assertSame('123 Main St, Springfield, USA', $address->getFullAddress());
    }

    public function testAddressToArrayReturnsCorrectStructure(): void
    {
        $address = Address::create('123 Main St', 'Springfield', 'USA', '62701', 'IL');
        $expected = [
            'street' => '123 Main St',
            'city' => 'Springfield',
            'state' => 'IL',
            'postal_code' => '62701',
            'country' => 'USA',
        ];
        $this->assertSame($expected, $address->toArray());
    }

    public function testAddressEqualsWithSameAddress(): void
    {
        $a1 = Address::create('123 Main St', 'Springfield', 'USA', '62701', 'IL');
        $a2 = Address::create('123 Main St', 'Springfield', 'USA', '62701', 'IL');
        $this->assertTrue($a1->equals($a2));
    }

    public function testAddressEqualsWithDifferentAddress(): void
    {
        $a1 = Address::create('123 Main St', 'Springfield', 'USA');
        $a2 = Address::create('456 Oak Ave', 'Springfield', 'USA');
        $this->assertFalse($a1->equals($a2));
    }

    public function testAddressToStringReturnsFullAddress(): void
    {
        $address = Address::create('123 Main St', 'Springfield', 'USA');
        $this->assertSame($address->getFullAddress(), (string) $address);
    }

    // --- UserId ---

    public function testUserIdGenerateCreatesValidUuid(): void
    {
        $id = UserId::generate();
        $this->assertNotEmpty($id->getValue());
    }

    public function testUserIdGenerateCreatesDifferentIds(): void
    {
        $id1 = UserId::generate();
        $id2 = UserId::generate();
        $this->assertFalse($id1->equals($id2));
    }

    public function testUserIdFromStringWithValidUuid(): void
    {
        $uuid = UserId::generate()->getValue();
        $id = UserId::fromString($uuid);
        $this->assertSame($uuid, $id->getValue());
    }

    public function testUserIdFromStringWithInvalidUuidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UserId::fromString('not-a-uuid');
    }

    public function testUserIdEquals(): void
    {
        $uuid = UserId::generate()->getValue();
        $id1 = UserId::fromString($uuid);
        $id2 = UserId::fromString($uuid);
        $this->assertTrue($id1->equals($id2));
    }

    public function testUserIdToString(): void
    {
        $id = UserId::generate();
        $this->assertSame($id->getValue(), (string) $id);
    }

    // --- TokenId ---

    public function testTokenIdGenerateCreatesValidUuid(): void
    {
        $id = TokenId::generate();
        $this->assertNotEmpty($id->getValue());
    }

    public function testTokenIdFromStringWithInvalidUuidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TokenId::fromString('invalid');
    }

    public function testTokenIdEquals(): void
    {
        $uuid = TokenId::generate()->getValue();
        $id1 = TokenId::fromString($uuid);
        $id2 = TokenId::fromString($uuid);
        $this->assertTrue($id1->equals($id2));
    }

    // --- UserRole ---

    public function testUserRoleTherapistValue(): void
    {
        $this->assertSame('ROLE_THERAPIST', UserRole::THERAPIST->value);
    }

    public function testUserRolePatientValue(): void
    {
        $this->assertSame('ROLE_PATIENT', UserRole::PATIENT->value);
    }

    public function testUserRoleIsTherapist(): void
    {
        $this->assertTrue(UserRole::THERAPIST->isTherapist());
        $this->assertFalse(UserRole::PATIENT->isTherapist());
    }

    public function testUserRoleIsPatient(): void
    {
        $this->assertTrue(UserRole::PATIENT->isPatient());
        $this->assertFalse(UserRole::THERAPIST->isPatient());
    }

    public function testUserRoleGetDisplayName(): void
    {
        $this->assertSame('Therapist', UserRole::THERAPIST->getDisplayName());
        $this->assertSame('Patient', UserRole::PATIENT->getDisplayName());
    }

    public function testUserRoleGetSecurityRoles(): void
    {
        $this->assertContains('ROLE_THERAPIST', UserRole::THERAPIST->getSecurityRoles());
        $this->assertContains('ROLE_USER', UserRole::THERAPIST->getSecurityRoles());
        $this->assertContains('ROLE_PATIENT', UserRole::PATIENT->getSecurityRoles());
        $this->assertContains('ROLE_USER', UserRole::PATIENT->getSecurityRoles());
    }
}
