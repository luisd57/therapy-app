<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\LongEmail;
use App\Tests\Helper\SeedsAuthFixtures;
use App\Tests\Helper\WireShapes;

final class PatientLoginControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;

    public function testPatientLoginSuccess(): void
    {
        $token = $this->createPatientAndGetToken();

        $this->assertNotEmpty($token);
        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        // The token travels in the httpOnly cookie only, so no key here may carry it.
        $this->assertEqualsCanonicalizing(['user'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertEqualsCanonicalizing(
            WireShapes::USER,
            array_keys(Json::arrayAt($data, 'data', 'user')),
            'data.user keys',
        );
    }

    public function testPatientLoginRejectsAnEmailOverTheLengthCap(): void
    {
        $this->jsonRequest('POST', '/api/auth/patient/login', [
            'email' => LongEmail::ofLength(255),
            'password' => self::PATIENT_PASSWORD,
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('Email must not exceed 254 characters', Json::at($this->getResponseData(), 'error', 'details', 'email'));
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
