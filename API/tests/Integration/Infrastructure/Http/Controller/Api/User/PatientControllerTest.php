<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\User;

use App\Tests\Helper\ApiTestCase;

final class PatientControllerTest extends ApiTestCase
{
    public function testMeAuthenticatedReturns200(): void
    {
        $token = $this->createPatientAndGetToken();

        $this->jsonRequest('GET', '/api/patient/me', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('ROLE_PATIENT', $data['data']['role']);
    }

    public function testMeUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/patient/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMeTherapistTokenReturns403(): void
    {
        $therapistToken = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/patient/me', [], $therapistToken);

        $this->assertResponseStatusCodeSame(403);
    }

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

    public function testUpdateProfileUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('PUT', '/api/patient/profile', [
            'phone' => '+1234567890',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
