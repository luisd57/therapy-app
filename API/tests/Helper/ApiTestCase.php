<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            $this->entityManager->close();
        } finally {
            parent::tearDown();
        }
    }

    protected function jsonRequest(string $method, string $uri, array $data = [], ?string $token = null): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $this->client->request($method, $uri, [], [], $headers, json_encode($data));
    }

    protected function getResponseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
    }

    protected function createTherapistAndGetToken(
        string $email = 'therapist@test.com',
        string $password = 'password123',
    ): string {
        $hasher = self::getContainer()->get(PasswordHasherInterface::class);
        $repo = self::getContainer()->get(UserRepositoryInterface::class);

        $therapist = User::createTherapist(
            id: UserId::generate(),
            email: Email::fromString($email),
            fullName: 'Test Therapist',
            hashedPassword: $hasher->hash($password),
        );
        $repo->save($therapist);

        $this->jsonRequest('POST', '/api/auth/therapist/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $data = $this->getResponseData();

        return $data['data']['token'];
    }

    protected function createPatientAndGetToken(
        string $email = 'patient@test.com',
        string $password = 'patient123password',
    ): string {
        $hasher = self::getContainer()->get(PasswordHasherInterface::class);
        $repo = self::getContainer()->get(UserRepositoryInterface::class);

        $patient = User::createPatient(
            id: UserId::generate(),
            email: Email::fromString($email),
            fullName: 'Test Patient',
        );
        $patient->activate($hasher->hash($password));
        $repo->save($patient);

        $this->jsonRequest('POST', '/api/auth/patient/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $data = $this->getResponseData();

        return $data['data']['token'];
    }
}
