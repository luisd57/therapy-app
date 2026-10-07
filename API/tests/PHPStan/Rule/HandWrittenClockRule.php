<?php

declare(strict_types=1);

namespace App\Tests\PHPStan\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use Symfony\Component\Clock\ClockInterface;

/**
 * Reports a test-suite class that implements ClockInterface itself.
 *
 * @implements Rule<InClassNode>
 */
final class HandWrittenClockRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!TestScope::covers($scope) || !$node->getClassReflection()->implementsInterface(ClockInterface::class)) {
            return [];
        }

        return [ClockDoubleShape::error('a hand-written clock class')];
    }
}
