<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Therapist;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\SeedsAuthFixtures;
use App\Tests\Helper\WireShapes;

final class ListPatientsControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;

    public function testListPatientsAuthenticatedReturns200(): void
    {
        $this->seedActivatedPatient();
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/patients', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['patients', 'pagination'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertEqualsCanonicalizing(
            WireShapes::PAGINATION,
            array_keys(Json::arrayAt($data, 'data', 'pagination')),
            'data.pagination keys',
        );
        $this->assertTrue(array_is_list(Json::arrayAt($data, 'data', 'patients')), 'data.patients is a list');
        $this->assertEqualsCanonicalizing(
            WireShapes::USER,
            array_keys(Json::arrayAt($data, 'data', 'patients', 0)),
            'data.patients[0] keys',
        );
        $this->assertSame(1, Json::at($data, 'data', 'pagination', 'page'));
        $this->assertSame(20, Json::at($data, 'data', 'pagination', 'limit'));
    }

    public function testListPatientsWithPaginationParams(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/patients?page=1&limit=5', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertSame(1, Json::at($data, 'data', 'pagination', 'page'));
        $this->assertSame(5, Json::at($data, 'data', 'pagination', 'limit'));
    }

    public function testListPatientsUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/patients');

        $this->assertResponseStatusCodeSame(401);
    }
}
