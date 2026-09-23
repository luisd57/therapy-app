<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Therapist;

use App\Tests\Helper\ApiTestCase;

final class InvitePatientControllerTest extends ApiTestCase
{
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
        $this->assertEqualsCanonicalizing(['success', 'data'], array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['invitation', 'message'], array_keys($data['data']), 'data keys');
        $this->assertEqualsCanonicalizing(
            ['id', 'email', 'patient_name', 'status', 'created_at', 'expires_at'],
            array_keys($data['data']['invitation']),
            'data.invitation keys',
        );
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
}
