<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Therapist;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\WireShapes;

final class CurrentTherapistControllerTest extends ApiTestCase
{
    public function testMeAuthenticatedReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/me', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(WireShapes::USER, array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertSame('ROLE_THERAPIST', Json::at($data, 'data', 'role'));
    }

    public function testMeUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/me');

        $this->assertResponseStatusCodeSame(401);
    }
}
