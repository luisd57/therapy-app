<?php

declare(strict_types=1);

namespace App\Tests\PHPStan\Rule;

use DateTimeImmutable;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports a single-argument DateTimeImmutable built from a literal that names no zone.
 *
 * @implements Rule<New_>
 */
final class NaiveDateTimeLiteralRule implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (
            !TestScope::covers($scope)
            || !$node->class instanceof Name
            || strcasecmp($scope->resolveName($node->class), DateTimeImmutable::class) !== 0
        ) {
            return [];
        }

        // A second argument is the zone, so only the one-argument form can be naive.
        $arguments = $node->getArgs();
        if (count($arguments) !== 1) {
            return [];
        }

        $errors = [];
        foreach ($scope->getType($arguments[0]->value)->getConstantStrings() as $literal) {
            if (!self::isNaive($literal->getValue())) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                "DateTimeImmutable('%s') has no zone, so it is read in the process timezone.",
                $literal->getValue(),
            ))
                ->identifier('testConvention.naiveDateTimeLiteral')
                ->tip('Write the offset into the literal, or use self::utc(). See docs/adr/0003. ' . TestScope::DOC)
                ->build();
        }

        return $errors;
    }

    /**
     * True when the text sets a calendar date or a time of day and names no zone.
     * A pure relative duration like '-1 hour' is the same instant in any zone, so it passes.
     */
    private static function isNaive(string $text): bool
    {
        $parsed = date_parse($text);

        if ($parsed['error_count'] > 0 || $parsed['is_localtime']) {
            return false;
        }

        return $parsed['year'] !== false || $parsed['hour'] !== false;
    }
}
