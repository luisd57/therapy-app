<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http\Validation;

use App\Infrastructure\Http\Validation\EmailAddress;
use App\Tests\Helper\RefusedEmails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class EmailAddressTest extends TestCase
{
    /** @return iterable<string, array{?string, list<string>}> */
    public static function cases(): iterable
    {
        yield 'null' => [null, ['Email is required']];
        yield 'empty' => ['', ['Email is required']];
        yield 'malformed' => ['not-an-email', ['Invalid email format']];
        yield 'well-formed' => ['user@example.com', []];
        // Email::fromString trims, so padding that Symfony's Email rule refused now passes.
        yield 'padded with spaces' => ['  user@example.com  ', []];
    }

    /** @param list<string> $messages */
    #[DataProvider('cases')]
    public function testReportsTheBrokenRule(?string $email, array $messages): void
    {
        $this->assertSame($messages, $this->messagesFor($email));
    }

    #[DataProviderExternal(RefusedEmails::class, 'cases')]
    public function testRejectsWhatTheValueObjectRefuses(string $email): void
    {
        $this->assertSame(['Invalid email format'], $this->messagesFor($email));
    }

    /** The validator throws UnexpectedValueException, which Symfony turns into a type violation. */
    public function testRejectsANonString(): void
    {
        $this->assertSame(['This value should be of type string.'], $this->messagesFor(42));
    }

    /** @return list<string> */
    private function messagesFor(mixed $email): array
    {
        $messages = [];
        foreach (Validation::createValidator()->validate($email, new EmailAddress()) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }
}
