<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Console\Appointment;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedScheduleCommandTest extends IntegrationTestCase
{
    /** The week the e2e suites pick their Slots from. */
    private const SEEDED_BLOCKS = [
        'FRIDAY 08:00-12:00 in-person',
        'MONDAY 08:00-12:00 online in-person',
        'MONDAY 14:00-18:00 online in-person',
        'THURSDAY 08:00-12:00 online in-person',
        'THURSDAY 14:00-18:00 online in-person',
        'TUESDAY 08:00-12:00 online in-person',
        'TUESDAY 14:00-18:00 online in-person',
        'WEDNESDAY 08:00-12:00 online',
    ];

    private TherapistScheduleRepositoryInterface $scheduleRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduleRepository = self::getContainer()->get(TherapistScheduleRepositoryInterface::class);
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:seed-schedule'));
    }

    private function saveTherapist(): User
    {
        $therapist = DomainTestHelper::createTherapist();
        self::getContainer()->get(UserRepositoryInterface::class)->save($therapist);

        return $therapist;
    }

    private function saveBlock(User $therapist, WeekDay $weekDay = WeekDay::MONDAY): TherapistSchedule
    {
        $block = DomainTestHelper::createScheduleBlock($therapist, $weekDay);
        $this->scheduleRepository->save($block);

        return $block;
    }

    /** @return list<string> */
    private function activeBlockSummaries(User $therapist): array
    {
        $blocks = $this->scheduleRepository->findActiveByTherapist($therapist->getId())->map(
            fn(TherapistSchedule $block) => trim(sprintf(
                '%s %s-%s%s%s',
                $block->getDayOfWeek()->name,
                $block->getStartTime(),
                $block->getEndTime(),
                $block->isSupportsOnline() ? ' online' : '',
                $block->isSupportsInPerson() ? ' in-person' : '',
            )),
        )->toArray();
        sort($blocks);

        return $blocks;
    }

    public function testFailsWithoutATherapist(): void
    {
        $tester = $this->commandTester();
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No therapist found', $tester->getDisplay());
    }

    public function testSeedsTheWeek(): void
    {
        $therapist = $this->saveTherapist();

        $tester = $this->commandTester();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame(self::SEEDED_BLOCKS, $this->activeBlockSummaries($therapist));
    }

    public function testRefusesToSeedOverExistingBlocks(): void
    {
        $therapist = $this->saveTherapist();
        $existing = $this->saveBlock($therapist);

        $tester = $this->commandTester();
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Use --force', $tester->getDisplay());
        $active = $this->scheduleRepository->findActiveByTherapist($therapist->getId());
        $this->assertCount(1, $active);
        $this->assertTrue($existing->getId()->equals($active->first()->getId()));
    }

    public function testForceDeactivatesExistingBlocksAndReseeds(): void
    {
        $therapist = $this->saveTherapist();
        // Two, so deactivating only the first would leave one active beside the new week
        $monday = $this->saveBlock($therapist, WeekDay::MONDAY);
        $saturday = $this->saveBlock($therapist, WeekDay::SATURDAY);

        $tester = $this->commandTester();
        $tester->execute(['--force' => true]);

        $tester->assertCommandIsSuccessful();
        $this->entityManager->clear();
        $this->assertFalse($this->scheduleRepository->findById($monday->getId())->isActive());
        $this->assertFalse($this->scheduleRepository->findById($saturday->getId())->isActive());
        $this->assertSame(self::SEEDED_BLOCKS, $this->activeBlockSummaries($therapist));
    }
}
