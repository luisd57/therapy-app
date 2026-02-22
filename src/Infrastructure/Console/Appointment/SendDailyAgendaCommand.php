<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Appointment;

use App\Application\Appointment\DTO\Input\SendDailyAgendaInputDTO;
use App\Application\Appointment\Handler\SendDailyAgendaHandler;
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

        $dateString = $input->getArgument('date') ?? date('Y-m-d');

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
