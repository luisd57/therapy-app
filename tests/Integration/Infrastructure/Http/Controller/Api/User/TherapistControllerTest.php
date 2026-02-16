<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\User;

use App\Tests\Helper\ApiTestCase;

final class TherapistControllerTest extends ApiTestCase
{
    public function testSetupSuccessReturns201(): void
    {
        $this->jsonRequest('POST', '/api/therapist/setup', [
            'email' => 'newtherapist@test.com',
            'full_name' => 'Dr. New',
            'password' => 'securepass123',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('newtherapist@test.com', $data['data']['user']['email']);
    }

    public function testSetupDuplicateEmailReturns409(): void
    {
        $this->jsonRequest('POST', '/api/therapist/setup', [
            'email' => 'dup@test.com',
            'full_name' => 'Dr. First',
            'password' => 'securepass123',
        ]);

        $this->jsonRequest('POST', '/api/therapist/setup', [
            'email' => 'dup@test.com',
            'full_name' => 'Dr. Second',
            'password' => 'securepass123',
        ]);

        $this->assertResponseStatusCodeSame(409);
    }

    public function testSetupMissingFieldsReturns422(): void
    {
        $this->jsonRequest('POST', '/api/therapist/setup', [
            'email' => 'missing@test.com',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testSetupShortPasswordReturns422(): void
    {
        $this->jsonRequest('POST', '/api/therapist/setup', [
            'email' => 'short@test.com',
            'full_name' => 'Dr. Short',
            'password' => 'short',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testMeAuthenticatedReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/me', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('ROLE_THERAPIST', $data['data']['role']);
    }

    public function testMeUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testListPatientsAuthenticatedReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/patients', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('patients', $data['data']);
        $this->assertArrayHasKey('count', $data['data']);
    }

    public function testListPatientsUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/patients');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testInvitePatientSuccessReturns201(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => 'invited@test.com',
            'patient_name' => 'Invited Patient',
        ], $token);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('invited@test.com', $data['data']['invitation']['email']);
    }

    public function testInvitePatientMissingFieldsReturns422(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => 'invited@test.com',
        ], $token);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testInvitePatientUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => 'invited@test.com',
            'patient_name' => 'Invited Patient',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testListInvitationsAuthenticatedReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/invitations', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('invitations', $data['data']);
    }

    public function testListInvitationsUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/invitations');

        $this->assertResponseStatusCodeSame(401);
    }
}
