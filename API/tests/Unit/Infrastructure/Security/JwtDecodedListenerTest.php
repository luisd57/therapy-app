<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Domain\User\Service\JwtBlocklistInterface;
use App\Infrastructure\Security\JwtDecodedListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class JwtDecodedListenerTest extends TestCase
{
    private JwtBlocklistInterface&MockObject $jwtBlocklist;
    private JwtDecodedListener $listener;

    protected function setUp(): void
    {
        $this->jwtBlocklist = $this->createMock(JwtBlocklistInterface::class);
        $this->listener = new JwtDecodedListener($this->jwtBlocklist);
    }

    public function testRejectsARevokedJti(): void
    {
        $this->jwtBlocklist->method('isRevoked')->willReturn(true);
        $event = new JWTDecodedEvent(['jti' => 'abc', 'iat' => 1787306400, 'email' => 'user@example.com']);

        $this->listener->onJWTDecoded($event);

        $this->assertFalse($event->isValid());
    }

    public function testRejectsATokenIssuedBeforeTheUsersCutoff(): void
    {
        $this->jwtBlocklist->method('isRevoked')->willReturn(false);
        $this->jwtBlocklist->method('isRevokedByCutoff')->willReturn(true);
        $event = new JWTDecodedEvent(['jti' => 'abc', 'iat' => 1787306400, 'email' => 'user@example.com']);

        $this->listener->onJWTDecoded($event);

        $this->assertFalse($event->isValid());
    }

    public function testAcceptsATokenIssuedAfterTheCutoff(): void
    {
        $this->jwtBlocklist->method('isRevoked')->willReturn(false);
        $this->jwtBlocklist->method('isRevokedByCutoff')->willReturn(false);
        $event = new JWTDecodedEvent(['jti' => 'abc', 'iat' => 1787306400, 'email' => 'user@example.com']);

        $this->listener->onJWTDecoded($event);

        $this->assertTrue($event->isValid());
    }

    /**
     * A payload without both claims cannot be judged against a cutoff, and must not
     * blow up the decode either.
     */
    public function testLeavesAPayloadWithoutIatOrEmailAlone(): void
    {
        $this->jwtBlocklist->method('isRevoked')->willReturn(false);
        $this->jwtBlocklist->expects($this->never())->method('isRevokedByCutoff');
        $event = new JWTDecodedEvent(['jti' => 'abc']);

        $this->listener->onJWTDecoded($event);

        $this->assertTrue($event->isValid());
    }

    public function testDoesNotConsultTheCutoffWhenTheJtiIsAlreadyRevoked(): void
    {
        $this->jwtBlocklist->method('isRevoked')->willReturn(true);
        $this->jwtBlocklist->expects($this->never())->method('isRevokedByCutoff');
        $event = new JWTDecodedEvent(['jti' => 'abc', 'iat' => 1787306400, 'email' => 'user@example.com']);

        $this->listener->onJWTDecoded($event);

        $this->assertFalse($event->isValid());
    }

    public function testLeavesAPayloadWithoutEmailAlone(): void
    {
        $this->jwtBlocklist->method('isRevoked')->willReturn(false);
        $this->jwtBlocklist->expects($this->never())->method('isRevokedByCutoff');
        $event = new JWTDecodedEvent(['jti' => 'abc', 'iat' => 1787306400]);

        $this->listener->onJWTDecoded($event);

        $this->assertTrue($event->isValid());
    }

    public function testLeavesAPayloadWithoutIatAlone(): void
    {
        $this->jwtBlocklist->method('isRevoked')->willReturn(false);
        $this->jwtBlocklist->expects($this->never())->method('isRevokedByCutoff');
        $event = new JWTDecodedEvent(['jti' => 'abc', 'email' => 'user@example.com']);

        $this->listener->onJWTDecoded($event);

        $this->assertTrue($event->isValid());
    }
}
