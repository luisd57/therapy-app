<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\User\Auth;

use App\Tests\Helper\ApiTestCase;

final class ResetPasswordControllerTest extends ApiTestCase
{
    public function testResetPasswordMissingTokenReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/reset', [
            'password' => 'NewPass1!',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testResetPasswordInvalidTokenReturns400(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/reset', [
            'token' => 'bad-token',
            'password' => 'NewPass1!',
        ]);

        $this->assertResponseStatusCodeSame(400);
    }
}
