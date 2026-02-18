<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\User;

use App\Application\User\DTO\Input\CreateTherapistInputDTO;
use App\Application\User\Handler\CreateTherapistHandler;
use App\Domain\User\Exception\UserAlreadyExistsException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-therapist',
    description: 'Create the initial therapist account',
)]
final class CreateTherapistConsoleCommand extends Command
{
    public function __construct(
        private readonly CreateTherapistHandler $handler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Therapist email')
            ->addArgument('name', InputArgument::REQUIRED, 'Therapist full name')
            ->addArgument('password', InputArgument::REQUIRED, 'Therapist password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $name = $input->getArgument('name');
        $password = $input->getArgument('password');

        try {
            $user = $this->handler->__invoke(new CreateTherapistInputDTO(
                email: $email,
                fullName: $name,
                password: $password,
            ));

            $io->success(sprintf(
                'Therapist created successfully! ID: %s',
                $user->id
            ));

            return Command::SUCCESS;
        } catch (UserAlreadyExistsException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }
    }
}
