<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Therapist;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;

final class RevokeInvitationControllerTest extends ApiTestCase
{
    public function testRevokeInvitationSuccessReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => 'revoke-target@test.com',
            'patient_name' => 'Revoke Target',
        ], $token);
        $invitationId = Json::stringAt($this->getResponseData(), 'data', 'invitation', 'id');

        $this->jsonRequest('POST', "/api/therapist/invitations/{$invitationId}/revoke", [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('revoked', Json::at($data, 'data', 'invitation', 'status'));
        $this->assertSame($invitationId, Json::at($data, 'data', 'invitation', 'id'));
    }

    public function testRevokeInvitationNotFoundReturns404(): void
    {
        $token = $this->createTherapistAndGetToken();
        $randomId = '01900000-0000-7000-8000-000000000000';

        $this->jsonRequest('POST', "/api/therapist/invitations/{$randomId}/revoke", [], $token);

        $this->assertResponseStatusCodeSame(404);
        $data = $this->getResponseData();
        $this->assertSame('INVITATION_NOT_FOUND', Json::at($data, 'error', 'code'));
    }

    public function testRevokeInvitationAlreadyRevokedReturns409(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => 'revoke-twice@test.com',
            'patient_name' => 'Revoke Twice',
        ], $token);
        $invitationId = Json::stringAt($this->getResponseData(), 'data', 'invitation', 'id');

        $this->jsonRequest('POST', "/api/therapist/invitations/{$invitationId}/revoke", [], $token);
        $this->assertResponseIsSuccessful();

        $this->jsonRequest('POST', "/api/therapist/invitations/{$invitationId}/revoke", [], $token);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertSame('INVALID_INVITATION_STATE', Json::at($data, 'error', 'code'));
    }

    public function testRevokeInvitationUnauthenticatedReturns401(): void
    {
        $randomId = '01900000-0000-7000-8000-000000000000';
        $this->jsonRequest('POST', "/api/therapist/invitations/{$randomId}/revoke");

        $this->assertResponseStatusCodeSame(401);
    }
}
