<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Therapist;

use App\Tests\Helper\ApiTestCase;

final class ListPatientsControllerTest extends ApiTestCase
{
    public function testListPatientsAuthenticatedReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/patients', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('patients', $data['data']);
        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertSame(1, $data['data']['pagination']['page']);
        $this->assertSame(20, $data['data']['pagination']['limit']);
        $this->assertArrayHasKey('total', $data['data']['pagination']);
        $this->assertArrayHasKey('total_pages', $data['data']['pagination']);
    }

    public function testListPatientsWithPaginationParams(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/patients?page=1&limit=5', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertSame(1, $data['data']['pagination']['page']);
        $this->assertSame(5, $data['data']['pagination']['limit']);
    }

    public function testListPatientsUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/patients');

        $this->assertResponseStatusCodeSame(401);
    }
}
