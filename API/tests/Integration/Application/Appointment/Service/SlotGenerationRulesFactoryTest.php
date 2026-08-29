<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Appointment\Service;

use App\Application\Appointment\Service\SlotGenerationRulesFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

// Not IntegrationTestCase: this reads container parameters and touches no
// database, so transaction wrapping would buy nothing.
final class SlotGenerationRulesFactoryTest extends KernelTestCase
{
    private ContainerInterface $container;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->container = self::getContainer();
    }

    public function testTheConfiguredDurationAndStartIncrementReachTheRules(): void
    {
        $slotGenerationRules = $this->container->get(SlotGenerationRulesFactory::class)->create();

        // Read, never repeated: a literal here would pass while services.yaml
        // bound each parameter to the wrong argument name.
        $this->assertSame(
            $this->container->getParameter('app.appointment_duration_minutes'),
            $slotGenerationRules->durationMinutes,
        );
        $this->assertSame(
            $this->container->getParameter('app.slot_start_increment_minutes'),
            $slotGenerationRules->startIncrementMinutes,
        );
    }
}
