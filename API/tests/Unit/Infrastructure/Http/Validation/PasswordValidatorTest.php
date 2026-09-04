<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http\Validation;

use App\Infrastructure\Http\Validation\PasswordValidator;
use PHPUnit\Framework\TestCase;

/**
 * app:create-therapist uses this rather than the PasswordStrength constraint, so the two
 * hand-maintained copies of the same rules are pinned separately. Ticket 21 collapses them.
 */
final class PasswordValidatorTest extends TestCase
{
    public function testAcceptsAPasswordMeetingEveryRule(): void
    {
        $this->assertNull(PasswordValidator::validate('Secure1!pass'));
    }

    public function testRejectsAPasswordOneCharacterUnderTheMinimum(): void
    {
        $this->assertSame(
            'Password must be between 8 and 72 characters',
            PasswordValidator::validate('Ab1!efg'),
        );
    }

    public function testAcceptsAPasswordExactlyAtTheMinimum(): void
    {
        $this->assertNull(PasswordValidator::validate('Ab1!efgh'));
    }

    public function testAcceptsAPasswordExactlyAtTheMaximum(): void
    {
        $password = 'Ab1!' . str_repeat('x', 68);
        $this->assertSame(72, strlen($password));

        $this->assertNull(PasswordValidator::validate($password));
    }

    /**
     * 72 bytes is bcrypt's input limit, so the upper bound is a real rule and not decoration.
     */
    public function testRejectsAPasswordOneCharacterOverTheMaximum(): void
    {
        $password = 'Ab1!' . str_repeat('x', 69);
        $this->assertSame(73, strlen($password));

        $this->assertSame('Password must be between 8 and 72 characters', PasswordValidator::validate($password));
    }

    public function testRejectsAPasswordWithoutAnUppercaseLetter(): void
    {
        $this->assertSame(
            'Password must contain at least one uppercase letter',
            PasswordValidator::validate('ab1!efgh'),
        );
    }

    public function testRejectsAPasswordWithoutALowercaseLetter(): void
    {
        $this->assertSame(
            'Password must contain at least one lowercase letter',
            PasswordValidator::validate('AB1!EFGH'),
        );
    }

    public function testRejectsAPasswordWithoutADigit(): void
    {
        $this->assertSame(
            'Password must contain at least one number',
            PasswordValidator::validate('Abc!efgh'),
        );
    }

    public function testRejectsAPasswordWithoutASpecialCharacter(): void
    {
        $this->assertSame(
            'Password must contain at least one special character',
            PasswordValidator::validate('Abc1efgh'),
        );
    }

    /**
     * Unlike the constraint, this one has no NotBlank in front of it - the command calls it on
     * whatever was typed, so an empty password must be rejected here.
     */
    public function testRejectsAnEmptyPassword(): void
    {
        $this->assertSame('Password must be between 8 and 72 characters', PasswordValidator::validate(''));
    }
}
