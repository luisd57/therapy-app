<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\PasswordRuleBreaches;
use App\Tests\Helper\SeedsAuthFixtures;
use App\Tests\Helper\UsesUtcInstants;
use App\Tests\Helper\WireShapes;
use PHPUnit\Framework\Attributes\DataProviderExternal;

final class RegisterControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;
    use UsesUtcInstants;

    protected function setUp(): void
    {
        parent::setUp();
        // The handler checks the invitation's expiry against this instant.
        $this->freezeClock('2026-05-30 09:00:00');
    }

    public function testRegisterValidTokenReturns201(): void
    {
        $invitation = $this->seedInvitation(now: self::utc('2026-05-30 08:00:00'));

        $this->jsonRequest('POST', '/api/auth/register', [
            'token' => $invitation->getToken(),
            'password' => 'Secure1!pass',
            'password_confirmation' => 'Secure1!pass',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['user', 'message'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertEqualsCanonicalizing(WireShapes::USER, array_keys(Json::arrayAt($data, 'data', 'user')), 'data.user keys');
        $this->assertSame('ROLE_PATIENT', Json::at($data, 'data', 'user', 'role'));
    }

    public function testRegisterMissingTokenReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'password' => 'Secure1!pass',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testRegisterShortPasswordReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'token' => 'some-token',
            'password' => 'short',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Everything else about this request is valid, so the response can only be about the password
     * rule it breaks. The status code alone would still pass with PasswordStrength unwired.
     */
    #[DataProviderExternal(PasswordRuleBreaches::class, 'cases')]
    public function testRegisterReportsWhichPasswordRuleFailed(string $password, string $message): void
    {
        $invitation = $this->seedInvitation(now: self::utc('2026-05-30 08:00:00'));

        $this->jsonRequest('POST', '/api/auth/register', [
            'token' => $invitation->getToken(),
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertSame('VALIDATION_ERROR', Json::at($data, 'error', 'code'));
        $this->assertSame($message, Json::at($data, 'error', 'details', 'password'));
        $this->assertArrayNotHasKey('token', Json::arrayAt($data, 'error', 'details'));
        $this->assertArrayNotHasKey('password_confirmation', Json::arrayAt($data, 'error', 'details'));
    }
}
