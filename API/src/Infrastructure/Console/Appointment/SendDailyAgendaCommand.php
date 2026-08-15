<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Appointment;

use App\Application\Appointment\DTO\Input\SendDailyAgendaInputDTO;
use App\Application\Appointment\Handler\SendDailyAgendaHandler;
use App\Domain\Appointment\Service\PracticeTimezoneProviderInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-daily-agenda',
    description: 'Send daily agenda email with confirmed appointments to the therapist',
)]
final class SendDailyAgendaCommand extends Command
{
    public function __construct(
        private readonly SendDailyAgendaHandler $sendDailyAgendaHandler,
        private readonly ClockInterface $clock,
        private readonly PracticeTimezoneProviderInterface $practiceTimezoneProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'date',
            InputArgument::OPTIONAL,
            'The date to send the agenda for (Y-m-d format). Defaults to today.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // "Today" is the therapist's day, not the container's. The process zone is
        // UTC, where anything after 20:00 in Caracas already counts as tomorrow.
        $dateString = $input->getArgument('date') ?? $this->clock->now()
            ->setTimezone($this->practiceTimezoneProvider->getTimeZone())
            ->format('Y-m-d');

        $appointmentCount = $this->sendDailyAgendaHandler->__invoke(
            new SendDailyAgendaInputDTO(date: $dateString),
        );

        $io->success(sprintf(
            'Daily agenda sent! %d confirmed appointment%s for %s.',
            $appointmentCount,
            $appointmentCount === 1 ? '' : 's',
            $dateString,
        ));

        return Command::SUCCESS;
    }
}
