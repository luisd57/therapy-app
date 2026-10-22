<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Therapist;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\SeedsAuthFixtures;
use App\Tests\Helper\WireShapes;

final class ListInvitationsControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;

    public function testListInvitationsAuthenticatedReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();
        $this->seedInvitation();

        $this->jsonRequest('GET', '/api/therapist/invitations', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['invitations', 'count'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertTrue(array_is_list(Json::arrayAt($data, 'data', 'invitations')), 'data.invitations is a list');
        $this->assertEqualsCanonicalizing(
            WireShapes::INVITATION,
            array_keys(Json::arrayAt($data, 'data', 'invitations', 0)),
            'data.invitations[0] keys',
        );
    }

    public function testListInvitationsUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/invitations');

        $this->assertResponseStatusCodeSame(401);
    }
}
