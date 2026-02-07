<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-tokens',
    description: 'Remove expired invitation and password reset tokens',
)]
final class CleanupExpiredTokensCommand extends Command
{
    public function __construct(
        private readonly InvitationTokenRepositoryInterface $invitationRepository,
        private readonly PasswordResetTokenRepositoryInterface $passwordResetRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $invitationsDeleted = $this->invitationRepository->deleteExpired();
        $passwordResetsDeleted = $this->passwordResetRepository->deleteExpired();

        $io->success(sprintf(
            'Cleanup complete! Removed %d expired invitation tokens and %d expired password reset tokens.',
            $invitationsDeleted,
            $passwordResetsDeleted
        ));

        return Command::SUCCESS;
    }
}
