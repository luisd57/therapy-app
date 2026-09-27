<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Therapist;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;

final class ResendInvitationControllerTest extends ApiTestCase
{
    public function testResendInvitationSuccessReturns201(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => 'resend-target@test.com',
            'patient_name' => 'Resend Target',
        ], $token);
        $createData = $this->getResponseData();
        $invitationId = Json::stringAt($createData, 'data', 'invitation', 'id');

        $this->jsonRequest('POST', "/api/therapist/invitations/{$invitationId}/resend", [], $token);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('pending', Json::at($data, 'data', 'invitation', 'status'));
        $this->assertNotSame($invitationId, Json::at($data, 'data', 'invitation', 'id'));
    }

    public function testResendInvitationNotFoundReturns404(): void
    {
        $token = $this->createTherapistAndGetToken();
        $randomId = '01900000-0000-7000-8000-000000000000';

        $this->jsonRequest('POST', "/api/therapist/invitations/{$randomId}/resend", [], $token);

        $this->assertResponseStatusCodeSame(404);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertSame('INVITATION_NOT_FOUND', Json::at($data, 'error', 'code'));
    }

    public function testResendInvitationAlreadyRevokedReturns409(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => 'resend-twice@test.com',
            'patient_name' => 'Resend Twice',
        ], $token);
        $invitationId = Json::stringAt($this->getResponseData(), 'data', 'invitation', 'id');

        // First resend revokes the original
        $this->jsonRequest('POST', "/api/therapist/invitations/{$invitationId}/resend", [], $token);
        $this->assertResponseStatusCodeSame(201);

        // Second resend of the now-revoked original should 409
        $this->jsonRequest('POST', "/api/therapist/invitations/{$invitationId}/resend", [], $token);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertSame('INVALID_INVITATION_STATE', Json::at($data, 'error', 'code'));
    }

    public function testResendInvitationUnauthenticatedReturns401(): void
    {
        $randomId = '01900000-0000-7000-8000-000000000000';
        $this->jsonRequest('POST', "/api/therapist/invitations/{$randomId}/resend");

        $this->assertResponseStatusCodeSame(401);
    }
}
