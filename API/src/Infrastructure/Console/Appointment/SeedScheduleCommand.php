<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Appointment;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\ValueObject\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-schedule',
    description: 'Create sample schedule blocks for the therapist (dev/testing)',
)]
final class SeedScheduleCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TherapistScheduleRepositoryInterface $scheduleRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Deactivate existing schedules before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $therapist = $this->userRepository->findSingleTherapist();
        } catch (\RuntimeException $e) {
            $io->error('No therapist found. Run app:create-therapist first.');
            return Command::FAILURE;
        }

        $therapistId = $therapist->getId();
        $existing = $this->scheduleRepository->findActiveByTherapist($therapistId);

        if (!$existing->isEmpty()) {
            if (!$input->getOption('force')) {
                $io->warning(sprintf(
                    'Therapist already has %d active schedule block(s). Use --force to deactivate them and reseed.',
                    $existing->count()
                ));
                return Command::FAILURE;
            }

            foreach ($existing as $schedule) {
                $schedule->deactivate();
                $this->scheduleRepository->save($schedule);
            }
            $io->note(sprintf('Deactivated %d existing schedule block(s).', $existing->count()));
        }

        $blocks = [
            [WeekDay::MONDAY,    '08:00', '12:00', true, true],
            [WeekDay::MONDAY,    '14:00', '18:00', true, true],
            [WeekDay::TUESDAY,   '08:00', '12:00', true, true],
            [WeekDay::TUESDAY,   '14:00', '18:00', true, true],
            [WeekDay::WEDNESDAY, '08:00', '12:00', true, false],
            [WeekDay::THURSDAY,  '08:00', '12:00', true, true],
            [WeekDay::THURSDAY,  '14:00', '18:00', true, true],
            [WeekDay::FRIDAY,    '08:00', '12:00', false, true],
        ];

        $created = 0;
        foreach ($blocks as [$day, $start, $end, $online, $inPerson]) {
            $schedule = TherapistSchedule::create(
                id: ScheduleId::generate(),
                therapistId: $therapistId,
                dayOfWeek: $day,
                startTime: $start,
                endTime: $end,
                supportsOnline: $online,
                supportsInPerson: $inPerson,
            );
            $this->scheduleRepository->save($schedule);
            $created++;
        }

        $io->success(sprintf('Created %d schedule blocks for the therapist.', $created));

        $headers = ['Day', 'Start', 'End', 'Online', 'In-Person'];
        $rows = array_map(fn($b) => [
            $b[0]->name,
            $b[1],
            $b[2],
            $b[3] ? 'Yes' : 'No',
            $b[4] ? 'Yes' : 'No',
        ], $blocks);
        $io->table($headers, $rows);

        return Command::SUCCESS;
    }
}
