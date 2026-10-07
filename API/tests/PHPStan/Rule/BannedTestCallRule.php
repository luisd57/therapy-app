<?php

declare(strict_types=1);

namespace App\Tests\PHPStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPUnit\Framework\Assert;

/**
 * Reports the calls the test suite bans outright.
 *
 * @implements Rule<CallLike>
 */
final class BannedTestCallRule implements Rule
{
    private const array WAITS = [
        'waiting on the wall clock is slow and flaky.',
        'Pin time with freezeClock() or a stubbed ClockInterface.',
    ];

    private const array LOOSE_EQUALITY = [
        'it compares dates by instant and hides a wrong offset.',
        'Use assertSame(), or assertInstantIs() for an instant. See docs/adr/0003.',
    ];

    private const array SKIPS = [
        'a skipped test lets the suite shrink without failing.',
        'Fix the test or delete it.',
    ];

    /** Keyed by lower-case name, since PHP resolves calls case-insensitively. */
    private const array FUNCTIONS = [
        'sleep' => ['sleep', self::WAITS],
        'usleep' => ['usleep', self::WAITS],
    ];

    private const array ASSERT_METHODS = [
        'assertequals' => ['assertEquals', self::LOOSE_EQUALITY],
        'assertnotequals' => ['assertNotEquals', self::LOOSE_EQUALITY],
        'marktestskipped' => ['markTestSkipped', self::SKIPS],
        'marktestincomplete' => ['markTestIncomplete', self::SKIPS],
    ];

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!TestScope::covers($scope)) {
            return [];
        }

        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $banned = self::FUNCTIONS[$node->name->toLowerString()] ?? null;

            return $banned === null ? [] : [self::error(...$banned)];
        }

        if (($node instanceof MethodCall || $node instanceof StaticCall) && $node->name instanceof Identifier) {
            $banned = self::ASSERT_METHODS[$node->name->toLowerString()] ?? null;
            if ($banned !== null && self::isAssert(self::receiverType($node, $scope))) {
                return [self::error(...$banned)];
            }
        }

        return [];
    }

    private static function receiverType(MethodCall|StaticCall $call, Scope $scope): Type
    {
        if ($call instanceof MethodCall) {
            return $scope->getType($call->var);
        }

        return $call->class instanceof Name
            ? new ObjectType($scope->resolveName($call->class))
            : $scope->getType($call->class);
    }

    // The receiver check keeps an unrelated class with a method of the same name legal.
    private static function isAssert(Type $receiver): bool
    {
        return (new ObjectType(Assert::class))->isSuperTypeOf($receiver)->yes();
    }

    /** @param array{string, string} $why the reason, then what to do instead */
    private static function error(string $name, array $why): IdentifierRuleError
    {
        return RuleErrorBuilder::message(sprintf('%s() is banned in tests: %s', $name, $why[0]))
            ->identifier('testConvention.bannedCall')
            ->tip($why[1] . ' ' . TestScope::DOC)
            ->build();
    }
}
