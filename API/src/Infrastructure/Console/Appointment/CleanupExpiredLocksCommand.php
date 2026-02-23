<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Appointment;

use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-slot-locks',
    description: 'Remove expired slot locks',
)]
final class CleanupExpiredLocksCommand extends Command
{
    public function __construct(
        private readonly SlotLockRepositoryInterface $slotLockRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deletedCount = $this->slotLockRepository->deleteExpired();

        $io->success(sprintf(
            'Cleanup complete! Removed %d expired slot locks.',
            $deletedCount,
        ));

        return Command::SUCCESS;
    }
}
