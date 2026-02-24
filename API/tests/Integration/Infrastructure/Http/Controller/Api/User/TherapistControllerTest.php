<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\User;

use App\Tests\Helper\ApiTestCase;

final class TherapistControllerTest extends ApiTestCase
{
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
        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertSame(1, $data['data']['pagination']['page']);
        $this->assertSame(20, $data['data']['pagination']['limit']);
        $this->assertArrayHasKey('total', $data['data']['pagination']);
        $this->assertArrayHasKey('total_pages', $data['data']['pagination']);
    }

    public function testListPatientsWithPaginationParams(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/patients?page=1&limit=5', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertSame(1, $data['data']['pagination']['page']);
        $this->assertSame(5, $data['data']['pagination']['limit']);
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
