<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Patient;

use App\Tests\Helper\ApiTestCase;

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
    // ^/api/patient too - RouteConventionsTest is what catches a missing attribute.
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
