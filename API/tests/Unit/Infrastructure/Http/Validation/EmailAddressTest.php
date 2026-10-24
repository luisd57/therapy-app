<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http\Validation;

use App\Infrastructure\Http\Validation\EmailAddress;
use App\Tests\Helper\LongEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class EmailAddressTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>}> */
    public static function cases(): iterable
    {
        yield 'empty' => ['', ['Email is required']];
        yield 'malformed' => ['not-an-email', ['Invalid email format']];
        yield 'one over the cap' => [LongEmail::ofLength(255), ['Email must not exceed 254 characters']];
        yield 'at the cap' => [LongEmail::ofLength(254), []];
        // Callers report the first message, so format must come before length.
        yield 'malformed and too long' => [str_repeat('x', 255), ['Invalid email format', 'Email must not exceed 254 characters']];
    }

    /** @param list<string> $messages */
    #[DataProvider('cases')]
    public function testReportsTheBrokenRulesInOrder(string $email, array $messages): void
    {
        $violations = Validation::createValidator()->validate($email, new EmailAddress());

        $actual = [];
        foreach ($violations as $violation) {
            $actual[] = (string) $violation->getMessage();
        }

        $this->assertSame($messages, $actual);
    }
}
