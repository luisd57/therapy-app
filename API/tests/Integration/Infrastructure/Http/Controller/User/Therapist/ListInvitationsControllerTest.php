<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Therapist;

use App\Tests\Helper\ApiTestCase;

final class ListInvitationsControllerTest extends ApiTestCase
{
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
