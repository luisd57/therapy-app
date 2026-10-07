<?php

declare(strict_types=1);

namespace App\Tests\PHPStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Symfony\Component\Clock\ClockInterface;

/**
 * Reports a ClockInterface double that hands back the real current instant.
 *
 * @implements Rule<MethodCall>
 */
final class UnpinnedClockStubRule implements Rule
{
    private const array RETURNS = ['willreturn', 'willreturnonconsecutivecalls'];

    /** Ways to double the clock that would hide the returned instant from this rule. */
    private const array HIDING_SHAPES = [
        'willreturncallback' => 'willReturnCallback',
        'createconfiguredmock' => 'createConfiguredMock',
        'createconfiguredstub' => 'createConfiguredStub',
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!TestScope::covers($scope) || !$node->name instanceof Identifier) {
            return [];
        }

        $name = $node->name->toLowerString();

        if (in_array($name, self::RETURNS, true)) {
            return self::stubsClockNow($node, $scope) && self::returnsRealInstant($node, $scope)
                ? [self::unpinned()]
                : [];
        }

        $shape = self::HIDING_SHAPES[$name] ?? null;
        if ($shape === null) {
            return [];
        }

        $doublesClock = $name === 'willreturncallback'
            ? self::stubsClockNow($node, $scope)
            : self::namesClock($node, $scope);

        return $doublesClock ? [ClockDoubleShape::error($shape . '()')] : [];
    }

    /** True for a chain like $clock->expects(...)->method('now')->willReturn(...). */
    private static function stubsClockNow(MethodCall $call, Scope $scope): bool
    {
        $sawNow = false;

        for ($link = $call->var; $link instanceof MethodCall; $link = $link->var) {
            $sawNow = $sawNow || self::isMethodNow($link, $scope);

            if (self::isClock($link->var, $scope)) {
                return $sawNow;
            }
        }

        return false;
    }

    private static function isMethodNow(MethodCall $call, Scope $scope): bool
    {
        if (!$call->name instanceof Identifier || $call->name->toLowerString() !== 'method') {
            return false;
        }

        $argument = $call->getArgs()[0] ?? null;
        if ($argument === null) {
            return false;
        }

        foreach ($scope->getType($argument->value)->getConstantStrings() as $method) {
            if (strtolower($method->getValue()) === 'now') {
                return true;
            }
        }

        return false;
    }

    private static function isClock(Expr $expr, Scope $scope): bool
    {
        return (new ObjectType(ClockInterface::class))->isSuperTypeOf($scope->getType($expr))->yes();
    }

    private static function namesClock(MethodCall $call, Scope $scope): bool
    {
        $argument = $call->getArgs()[0] ?? null;
        if ($argument === null) {
            return false;
        }

        foreach ($scope->getType($argument->value)->getConstantStrings() as $class) {
            if ((new ObjectType(ClockInterface::class))->isSuperTypeOf(new ObjectType($class->getValue()))->yes()) {
                return true;
            }
        }

        return false;
    }

    private static function returnsRealInstant(MethodCall $willReturn, Scope $scope): bool
    {
        foreach ($willReturn->getArgs() as $argument) {
            if (self::isRealInstant($argument, $scope)) {
                return true;
            }
        }

        return false;
    }

    /** True for a DateTimeImmutable with no arguments, or one whose text sets no calendar date. */
    private static function isRealInstant(Arg $argument, Scope $scope): bool
    {
        $new = $argument->value;
        if (!$new instanceof New_ || !TestScope::buildsDateTimeImmutable($new, $scope)) {
            return false;
        }

        $text = $new->getArgs()[0] ?? null;
        if ($text === null) {
            return true;
        }

        // 'now', '' and '-1 hour' all move with the wall clock, whatever zone a second argument names.
        foreach ($scope->getType($text->value)->getConstantStrings() as $literal) {
            if (date_parse($literal->getValue())['year'] === false) {
                return true;
            }
        }

        return false;
    }

    private static function unpinned(): IdentifierRuleError
    {
        return RuleErrorBuilder::message('A ClockInterface double must return a pinned instant, not the real one.')
            ->identifier('testConvention.unpinnedClockStub')
            ->tip("Return a literal, for example self::utc('2026-06-15 12:00:00'). See docs/adr/0003. " . TestScope::DOC)
            ->build();
    }
}
