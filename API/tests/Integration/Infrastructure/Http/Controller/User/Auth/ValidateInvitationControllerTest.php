<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAuthFixtures;

final class ValidateInvitationControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;

    public function testValidateInvitationValidTokenReturns200(): void
    {
        $invitation = $this->seedInvitation();

        $this->client->request('GET', '/api/auth/invitation/validate/' . $invitation->getToken());

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
    }

    public function testValidateInvitationInvalidTokenReturns400(): void
    {
        $this->client->request('GET', '/api/auth/invitation/validate/nonexistent-token');

        $this->assertResponseStatusCodeSame(400);
    }
}
