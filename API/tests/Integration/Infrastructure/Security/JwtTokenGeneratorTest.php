<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Security;

use App\Domain\User\Service\JwtTokenGeneratorInterface;
use App\Tests\Helper\DomainTestHelper;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Not IntegrationTestCase: minting a token touches no database, so transaction wrapping would buy
// nothing. Not a unit test either - a mocked JWTTokenManagerInterface would assert only that the
// wrapper calls the wrapper, and the claims come from lexik's config plus JwtCreatedListener.
final class JwtTokenGeneratorTest extends KernelTestCase
{
    /** @var array<string, mixed> */
    private array $payload;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $token = $container->get(JwtTokenGeneratorInterface::class)
            ->generate(DomainTestHelper::createTherapist(email: 'therapist@example.com'));

        $this->payload = $container->get(JWTEncoderInterface::class)->decode($token);
    }

    /**
     * LogoutController revokes this jti, so a token without one can never be logged out.
     */
    public function testTheTokenCarriesAJti(): void
    {
        $this->assertArrayHasKey('jti', $this->payload);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $this->payload['jti']);
    }

    /**
     * lexik's user_id_claim fills this by reading User::getEmail(), which returns a value object.
     * JwtCreatedListener replaces it with the identifier string, and JwtDecodedListener then feeds
     * it to the blocklist cutoff, which needs a string.
     */
    public function testTheTokenCarriesTheEmailAsAPlainString(): void
    {
        $this->assertIsString($this->payload['email']);
        $this->assertSame('therapist@example.com', $this->payload['email']);
    }

    public function testTheTokenCarriesTheUsersRoles(): void
    {
        $this->assertContains('ROLE_THERAPIST', $this->payload['roles']);
    }

    /**
     * JwtDecodedListener compares iat against the password reset cutoff, and LogoutController
     * derives the blocklist TTL from exp.
     */
    public function testTheTokenCarriesIssuedAtAndExpiry(): void
    {
        $this->assertArrayHasKey('iat', $this->payload);
        $this->assertArrayHasKey('exp', $this->payload);
        $this->assertGreaterThan(time(), $this->payload['exp']);
    }

    public function testTheTokenLivesForTheConfiguredTtl(): void
    {
        // Read from the same env var lexik_jwt_authentication.yaml binds, never a literal.
        $this->assertSame(
            (int) $_ENV['JWT_TOKEN_TTL'],
            $this->payload['exp'] - $this->payload['iat'],
        );
    }
}
