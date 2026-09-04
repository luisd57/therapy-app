<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http\Validation;

use App\Infrastructure\Http\Validation\PasswordStrength;
use App\Infrastructure\Http\Validation\PasswordStrengthValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Each input below violates exactly one rule, so a test going red names the rule that broke.
 */
final class PasswordStrengthValidatorTest extends ConstraintValidatorTestCase
{
    private PasswordStrength $passwordStrength;

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new PasswordStrengthValidator();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // parent::setUp() seeds the context with a NotNull, and buildViolation() compares against
        // whatever the context holds. Both have to move, or every assertion reports NotNull.
        $this->passwordStrength = new PasswordStrength();
        $this->constraint = $this->passwordStrength;
        $this->context->setConstraint($this->passwordStrength);
    }

    public function testAcceptsAPasswordMeetingEveryRule(): void
    {
        $this->validator->validate('Secure1!pass', $this->passwordStrength);

        $this->assertNoViolation();
    }

    public function testRejectsAPasswordOneCharacterUnderTheMinimum(): void
    {
        // Seven characters, every character class present.
        $this->validator->validate('Ab1!efg', $this->passwordStrength);

        $this->buildViolation($this->passwordStrength->minLengthMessage)->assertRaised();
    }

    public function testAcceptsAPasswordExactlyAtTheMinimum(): void
    {
        $this->validator->validate('Ab1!efgh', $this->passwordStrength);

        $this->assertNoViolation();
    }

    public function testAcceptsAPasswordExactlyAtTheMaximum(): void
    {
        $password = 'Ab1!' . str_repeat('x', 68);
        $this->assertSame(72, strlen($password));

        $this->validator->validate($password, $this->passwordStrength);

        $this->assertNoViolation();
    }

    /**
     * 72 bytes is bcrypt's input limit, so the upper bound is a real rule and not decoration.
     */
    public function testRejectsAPasswordOneCharacterOverTheMaximum(): void
    {
        $password = 'Ab1!' . str_repeat('x', 69);
        $this->assertSame(73, strlen($password));

        $this->validator->validate($password, $this->passwordStrength);

        $this->buildViolation($this->passwordStrength->minLengthMessage)->assertRaised();
    }

    public function testRejectsAPasswordWithoutAnUppercaseLetter(): void
    {
        $this->validator->validate('ab1!efgh', $this->passwordStrength);

        $this->buildViolation($this->passwordStrength->uppercaseMessage)->assertRaised();
    }

    public function testRejectsAPasswordWithoutALowercaseLetter(): void
    {
        $this->validator->validate('AB1!EFGH', $this->passwordStrength);

        $this->buildViolation($this->passwordStrength->lowercaseMessage)->assertRaised();
    }

    public function testRejectsAPasswordWithoutADigit(): void
    {
        $this->validator->validate('Abc!efgh', $this->passwordStrength);

        $this->buildViolation($this->passwordStrength->digitMessage)->assertRaised();
    }

    public function testRejectsAPasswordWithoutASpecialCharacter(): void
    {
        $this->validator->validate('Abc1efgh', $this->passwordStrength);

        $this->buildViolation($this->passwordStrength->specialCharMessage)->assertRaised();
    }

    /**
     * Presence is NotBlank's job. Reporting "must be between 8 and 72 characters" for a missing
     * password would hide the real problem behind a strength message.
     */
    public function testLeavesAnEmptyStringToNotBlank(): void
    {
        $this->validator->validate('', $this->passwordStrength);

        $this->assertNoViolation();
    }

    public function testLeavesNullToNotBlank(): void
    {
        $this->validator->validate(null, $this->passwordStrength);

        $this->assertNoViolation();
    }

    public function testRefusesAConstraintItDoesNotOwn(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('Secure1!pass', new NotBlank());
    }
}
