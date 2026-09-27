<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Patient;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use PHPUnit\Framework\Attributes\DataProvider;

final class UpdatePatientProfileControllerTest extends ApiTestCase
{
    public function testUpdateProfilePhoneOnlyReturns200(): void
    {
        $token = $this->createPatientAndGetToken();

        $this->jsonRequest('PUT', '/api/patient/profile', [
            'phone' => '+1234567890',
        ], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
    }

    // Pins PATCH; PUT is covered above.
    public function testUpdateProfileAcceptsPatch(): void
    {
        $token = $this->createPatientAndGetToken();

        $this->jsonRequest('PATCH', '/api/patient/profile', [
            'phone' => '+1234567890',
        ], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
    }

    public function testUpdateProfileAddressReturns200(): void
    {
        $token = $this->createPatientAndGetToken();

        $this->jsonRequest('PUT', '/api/patient/profile', [
            'address' => [
                'street' => '123 Main St',
                'city' => 'Springfield',
                'country' => 'USA',
                'postal_code' => '62701',
                'state' => 'IL',
            ],
        ], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertEqualsCanonicalizing(['success', 'data'], array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['user', 'message'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertEqualsCanonicalizing([
            'id', 'email', 'full_name', 'role', 'is_active', 'phone',
            'address', 'created_at', 'activated_at', 'timezone',
        ], array_keys(Json::arrayAt($data, 'data', 'user')), 'data.user keys');
        $this->assertEqualsCanonicalizing(
            ['street', 'city', 'country', 'postal_code', 'state'],
            array_keys(Json::arrayAt($data, 'data', 'user', 'address')),
            'data.user.address keys',
        );
    }

    public function testUpdateProfileInvalidPhoneReturns422(): void
    {
        $token = $this->createPatientAndGetToken();

        $this->jsonRequest('PUT', '/api/patient/profile', [
            'phone' => '123',
        ], $token);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateProfilePartialAddressReturns422(): void
    {
        $token = $this->createPatientAndGetToken();

        $this->jsonRequest('PUT', '/api/patient/profile', [
            'address' => [
                'street' => '123 Main St',
            ],
        ], $token);

        $this->assertResponseStatusCodeSame(422);
    }

    // Behaviour only. This still passes with #[IsGranted] removed, since security.yaml guards
    // ^/api/patient too - ProtectedRouteRolesTest is what catches a missing attribute.
    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('wrongTypedFields')]
    public function testUpdateProfileRejectsAWrongTypedFieldRatherThanDroppingIt(array $body, string $field, string $message): void
    {
        $token = $this->createPatientAndGetToken();

        $this->jsonRequest('PUT', '/api/patient/profile', $body, $token);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame([$field => $message], Json::at($this->getResponseData(), 'error', 'details'));
    }

    /** @return iterable<string, array{array<string, mixed>, string, string}> */
    public static function wrongTypedFields(): iterable
    {
        $address = ['street' => '123 Main St', 'city' => 'Springfield', 'country' => 'USA'];

        yield 'numeric phone' => [['phone' => 5551234567], 'phone', 'Phone number must be a string'];
        yield 'address as a string' => [['address' => '123 Main St'], 'address', 'Address must be an object'];
        yield 'numeric street' => [['address' => ['street' => 123] + $address], 'address.street', 'Street must be a string'];
        yield 'numeric postal code' => [['address' => $address + ['postal_code' => 62701]], 'address.postal_code', 'Postal code must be a string'];
        yield 'numeric state' => [['address' => $address + ['state' => 17]], 'address.state', 'State must be a string'];
    }

    public function testUpdateProfileTherapistTokenReturns403(): void
    {
        $therapistToken = $this->createTherapistAndGetToken();

        $this->jsonRequest('PUT', '/api/patient/profile', [
            'phone' => '+1234567890',
        ], $therapistToken);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUpdateProfileUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('PUT', '/api/patient/profile', [
            'phone' => '+1234567890',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
