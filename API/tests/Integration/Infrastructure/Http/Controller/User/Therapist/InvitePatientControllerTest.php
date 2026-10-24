<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Therapist;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\RefusedEmails;
use App\Tests\Helper\WireShapes;
use PHPUnit\Framework\Attributes\DataProviderExternal;

final class InvitePatientControllerTest extends ApiTestCase
{
    public function testInvitePatientSuccessReturns201(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => 'invited@test.com',
            'patient_name' => 'Invited Patient',
        ], $token);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['invitation', 'message'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertEqualsCanonicalizing(
            WireShapes::INVITATION,
            array_keys(Json::arrayAt($data, 'data', 'invitation')),
            'data.invitation keys',
        );
        $this->assertSame('invited@test.com', Json::at($data, 'data', 'invitation', 'email'));
    }

    public function testInvitePatientMissingFieldsReturns422(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => 'invited@test.com',
        ], $token);

        $this->assertResponseStatusCodeSame(422);
    }

    #[DataProviderExternal(RefusedEmails::class, 'cases')]
    public function testInvitePatientRejectsAnEmailTheValueObjectRefuses(string $email): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => $email,
            'patient_name' => 'Invited Patient',
        ], $token);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('Invalid email format', Json::at($this->getResponseData(), 'error', 'details', 'email'));
    }

    public function testInvitePatientUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('POST', '/api/therapist/patients/invite', [
            'email' => 'invited@test.com',
            'patient_name' => 'Invited Patient',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
