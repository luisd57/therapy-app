<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Security;

use App\Domain\User\Service\JwtTokenGeneratorInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\Json;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Not IntegrationTestCase: minting a token touches no database. Not a unit test either, since a
// mocked JWTTokenManagerInterface would assert only that the wrapper calls the wrapper.
final class JwtTokenGeneratorTest extends KernelTestCase
{
    /** @var array<mixed> */
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
        $jti = Json::at($this->payload, 'jti');
        $this->assertIsString($jti);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $jti);
    }

    /**
     * lexik fills this by reading User::getEmail(), a value object. JwtCreatedListener puts the
     * string back, which is what JwtDecodedListener's blocklist cutoff needs.
     */
    public function testTheTokenCarriesTheEmailAsAPlainString(): void
    {
        $this->assertIsString($this->payload['email']);
        $this->assertSame('therapist@example.com', $this->payload['email']);
    }

    public function testTheTokenCarriesTheUsersRoles(): void
    {
        $this->assertContains('ROLE_THERAPIST', Json::arrayAt($this->payload, 'roles'));
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

    public function testTheTokenLivesForEightHours(): void
    {
        // Absolute, not read back from JWT_TOKEN_TTL: an expectation taken from the same env var
        // the code reads agrees with any value. Change this literal when .env.test changes.
        $exp = Json::at($this->payload, 'exp');
        $iat = Json::at($this->payload, 'iat');
        // Integers, not numeric strings: JwtDecodedListener rejects a token whose iat is not one.
        $this->assertIsInt($exp);
        $this->assertIsInt($iat);
        $this->assertSame(28800, $exp - $iat);
    }
}
