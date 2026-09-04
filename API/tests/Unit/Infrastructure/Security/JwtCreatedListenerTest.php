<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Domain\User\Entity\User;
use App\Infrastructure\Security\JwtCreatedListener;
use App\Tests\Helper\DomainTestHelper;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\TestCase;

final class JwtCreatedListenerTest extends TestCase
{
    private JwtCreatedListener $listener;
    private User $user;

    protected function setUp(): void
    {
        $this->listener = new JwtCreatedListener();
        $this->user = DomainTestHelper::createTherapist(email: 'therapist@example.com');
    }

    /**
     * The jti is what RedisJwtBlocklist revokes, so a token minted without one can never be
     * logged out.
     */
    public function testAddsAJtiClaim(): void
    {
        $payload = $this->payloadAfterListener();

        $this->assertArrayHasKey('jti', $payload);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $payload['jti']);
    }

    public function testGivesEveryTokenItsOwnJti(): void
    {
        $this->assertNotSame($this->payloadAfterListener()['jti'], $this->payloadAfterListener()['jti']);
    }

    /**
     * Looks redundant, is not: lexik fills the email claim by reading User::getEmail(), which
     * returns a value object. JwtDecodedListener's blocklist cutoff needs the string this puts back.
     */
    public function testReplacesTheEmailClaimWithThePlainIdentifier(): void
    {
        $payload = $this->payloadAfterListener(['email' => $this->user->getEmail()]);

        $this->assertIsString($payload['email']);
        $this->assertSame('therapist@example.com', $payload['email']);
    }

    public function testLeavesTheClaimsItDoesNotOwnAlone(): void
    {
        $payload = $this->payloadAfterListener(['roles' => ['ROLE_THERAPIST'], 'iat' => 1787306400]);

        $this->assertSame(['ROLE_THERAPIST'], $payload['roles']);
        $this->assertSame(1787306400, $payload['iat']);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function payloadAfterListener(array $data = []): array
    {
        $event = new JWTCreatedEvent($data, $this->user);

        $this->listener->onJWTCreated($event);

        return $event->getData();
    }
}
