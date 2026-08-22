<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Patient;

use App\Tests\Helper\ApiTestCase;

final class CurrentPatientControllerTest extends ApiTestCase
{
    public function testMeAuthenticatedReturns200(): void
    {
        $token = $this->createPatientAndGetToken();

        $this->jsonRequest('GET', '/api/patient/me', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('ROLE_PATIENT', $data['data']['role']);
    }

    public function testMeUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/patient/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMeTherapistTokenReturns403(): void
    {
        $therapistToken = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/patient/me', [], $therapistToken);

        $this->assertResponseStatusCodeSame(403);
    }
}
