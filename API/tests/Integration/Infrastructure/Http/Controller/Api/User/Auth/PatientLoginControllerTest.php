<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAuthFixtures;

final class PatientLoginControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;

    public function testPatientLoginSuccess(): void
    {
        $token = $this->createPatientAndGetToken();

        $this->assertNotEmpty($token);
        $this->assertResponseIsSuccessful();
    }

    public function testPatientLoginWrongRoleReturns401(): void
    {
        $this->seedTherapist();

        $this->jsonRequest('POST', '/api/auth/patient/login', [
            'email' => self::THERAPIST_EMAIL,
            'password' => self::THERAPIST_PASSWORD,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
