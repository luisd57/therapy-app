<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Appointment\Service;

use App\Application\Appointment\Service\SlotGenerationRulesFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Not IntegrationTestCase: this reads container parameters and touches no
// database, so transaction wrapping would buy nothing.
final class SlotGenerationRulesFactoryTest extends KernelTestCase
{
    public function testTheConfiguredDurationAndStartIncrementReachTheRules(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $slotGenerationRules = $container->get(SlotGenerationRulesFactory::class)->create();

        // Read, never repeated: a literal here would pass while services.yaml
        // passed the parameters in the wrong order.
        $this->assertSame(
            $container->getParameter('app.appointment_duration_minutes'),
            $slotGenerationRules->durationMinutes,
        );
        $this->assertSame(
            $container->getParameter('app.slot_start_increment_minutes'),
            $slotGenerationRules->startIncrementMinutes,
        );
    }

    public function testTheConfiguredValuesDifferSoASwapWouldBeVisible(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertNotSame(
            $container->getParameter('app.appointment_duration_minutes'),
            $container->getParameter('app.slot_start_increment_minutes'),
            'Equal values make the assertions above pass under a swapped wiring.',
        );
    }
}
