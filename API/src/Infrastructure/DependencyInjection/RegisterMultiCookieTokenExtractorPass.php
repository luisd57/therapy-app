<?php

declare(strict_types=1);

namespace App\Infrastructure\DependencyInjection;

use App\Infrastructure\Security\MultiCookieTokenExtractor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Attaches MultiCookieTokenExtractor to lexik's ChainTokenExtractor via its
 * runtime addExtractor() method. Lexik's bundle does not auto-collect tagged
 * services into its chain, so we register the extractor through a method
 * call on the existing private chain service.
 */
final class RegisterMultiCookieTokenExtractorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $chainExtractorId = 'lexik_jwt_authentication.extractor.chain_extractor';
        if (!$container->hasDefinition($chainExtractorId)) {
            return;
        }

        $container->getDefinition($chainExtractorId)
            ->addMethodCall('addExtractor', [new Reference(MultiCookieTokenExtractor::class)]);
    }
}
