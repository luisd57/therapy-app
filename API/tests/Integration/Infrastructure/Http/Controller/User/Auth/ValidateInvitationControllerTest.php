<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\SeedsAuthFixtures;
use App\Tests\Helper\UsesUtcInstants;

final class ValidateInvitationControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;
    use UsesUtcInstants;

    protected function setUp(): void
    {
        parent::setUp();
        // The handler checks the invitation's expiry against this instant.
        $this->freezeClock('2026-05-30 09:00:00');
    }

    public function testValidateInvitationValidTokenReturns200(): void
    {
        $invitation = $this->seedInvitation(now: self::utc('2026-05-30 08:00:00'));

        $this->client->request('GET', '/api/auth/invitation/validate/' . $invitation->getToken());

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertEqualsCanonicalizing(['success', 'data'], array_keys($data), 'envelope keys');
        // No email: anyone holding the link could otherwise read who it was sent to.
        $this->assertEqualsCanonicalizing(
            ['id', 'patient_name', 'status', 'created_at', 'expires_at'],
            array_keys(Json::arrayAt($data, 'data')),
            'data keys',
        );
    }

    public function testValidateInvitationInvalidTokenReturns400(): void
    {
        $this->client->request('GET', '/api/auth/invitation/validate/nonexistent-token');

        $this->assertResponseStatusCodeSame(400);
    }
}
