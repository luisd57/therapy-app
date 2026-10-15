<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Console\User;

use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Domain\User\ValueObject\Email;
use App\Tests\Helper\IntegrationTestCase;
use App\Tests\Helper\PasswordRuleBreaches;
use App\Tests\Helper\SeedsAuthFixtures;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateTherapistCommandTest extends IntegrationTestCase
{
    use SeedsAuthFixtures;

    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = self::getContainer()->get(UserRepositoryInterface::class);
    }

    private function commandTester(): CommandTester
    {
        $application = self::consoleApplication();

        return new CommandTester($application->find('app:create-therapist'));
    }

    public function testCreatesTheTherapist(): void
    {
        $tester = $this->commandTester();
        $tester->execute([
            'email' => 'new-therapist@test.com',
            'name' => 'Dr. New Therapist',
            'password' => 'Therapist1!',
        ]);

        $tester->assertCommandIsSuccessful();
        $therapist = $this->userRepository->findSingleTherapist();
        $this->assertSame('new-therapist@test.com', $therapist->getEmail()->getValue());
        $this->assertSame('Dr. New Therapist', $therapist->getFullName());
        $this->assertTrue(self::getContainer()->get(PasswordHasherInterface::class)->verify(
            'Therapist1!',
            (string) $therapist->getPassword(),
        ));
    }

    public function testRefusesASecondTherapist(): void
    {
        $this->seedTherapist();

        $tester = $this->commandTester();
        $tester->execute([
            'email' => 'second-therapist@test.com',
            'name' => 'Dr. Second',
            'password' => 'Therapist1!',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('A therapist account already exists', $tester->getDisplay());
        $this->assertCount(1, $this->userRepository->findByRole(UserRole::THERAPIST));
        $this->assertNull($this->userRepository->findByEmail(Email::fromString('second-therapist@test.com')));
    }

    public function testRefusesAnEmailAPatientAlreadyUses(): void
    {
        $this->seedActivatedPatient();

        $tester = $this->commandTester();
        $tester->execute([
            'email' => self::PATIENT_EMAIL,
            'name' => 'Dr. Taken Email',
            'password' => 'Therapist1!',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('A user with this email already exists', $tester->getDisplay());
        $this->assertCount(0, $this->userRepository->findByRole(UserRole::THERAPIST));
    }

    #[DataProviderExternal(PasswordRuleBreaches::class, 'cases')]
    public function testRefusesAPasswordBreakingARule(string $password, string $message): void
    {
        $tester = $this->commandTester();
        $tester->execute([
            'email' => 'weak-password@test.com',
            'name' => 'Dr. Weak Password',
            'password' => $password,
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString($message, (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
        $this->assertCount(0, $this->userRepository->findByRole(UserRole::THERAPIST));
    }

    /** The constraint leaves an empty value to NotBlank, so the command has to put one in front. */
    public function testRefusesAnEmptyPassword(): void
    {
        $tester = $this->commandTester();
        $tester->execute([
            'email' => 'empty-password@test.com',
            'name' => 'Dr. Empty Password',
            'password' => '',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Password is required', $tester->getDisplay());
        $this->assertCount(0, $this->userRepository->findByRole(UserRole::THERAPIST));
    }

    public function testRefusesAMalformedEmail(): void
    {
        $tester = $this->commandTester();
        $tester->execute([
            'email' => 'not-an-email',
            'name' => 'Dr. Bad Email',
            'password' => 'Therapist1!',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid email format', $tester->getDisplay());
        $this->assertCount(0, $this->userRepository->findByRole(UserRole::THERAPIST));
    }
}
