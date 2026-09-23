<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

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
        $data = $this->getResponseData();
        $this->assertEqualsCanonicalizing(['success', 'data'], array_keys($data), 'envelope keys');
        // The token travels in the httpOnly cookie only, so no key here may carry it.
        $this->assertEqualsCanonicalizing(['user'], array_keys($data['data']), 'data keys');
        $this->assertEqualsCanonicalizing([
            'id', 'email', 'full_name', 'role', 'is_active', 'phone',
            'address', 'created_at', 'activated_at', 'timezone',
        ], array_keys($data['data']['user']), 'data.user keys');
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
