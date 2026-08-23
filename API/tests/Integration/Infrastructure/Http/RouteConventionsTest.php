<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Guards the one-action-per-controller convention in `.claude/rules/api-architecture.md`.
 *
 * Splitting a grouped controller drops its class-level #[IsGranted], so the role check becomes
 * something a new action can silently forget. These tests are what notices.
 */
final class RouteConventionsTest extends KernelTestCase
{
    // Not IntegrationTestCase: this reads the router and reflection, never the database, so the
    // transaction wrapping that base class provides would buy nothing.

    /**
     * Controllers still holding more than one action, shrinking as each conversion ticket lands.
     * Never add to this list: a new endpoint gets its own controller class.
     */
    private const PENDING_CONVERSION = [
        'App\Infrastructure\Http\Controller\Appointment\TherapistAppointmentController',
        'App\Infrastructure\Http\Controller\Appointment\TherapistScheduleController',
    ];

    /**
     * @var array<string, string> path prefix => role the routes under it must require
     */
    private const PROTECTED_PREFIXES = [
        '/api/therapist' => 'ROLE_THERAPIST',
        '/api/patient' => 'ROLE_PATIENT',
    ];

    public function testProtectedRoutesRequireTheirRole(): void
    {
        $missing = [];

        foreach ($this->apiControllers() as $routeName => ['class' => $class, 'method' => $method, 'path' => $path]) {
            foreach (self::PROTECTED_PREFIXES as $prefix => $role) {
                if (!str_starts_with($path, $prefix)) {
                    continue;
                }

                if (!$this->grantsRole($class, $method, $role)) {
                    $missing[] = sprintf('%s (%s) needs #[IsGranted(\'%s\')]', $routeName, $path, $role);
                }
            }
        }

        self::assertSame([], $missing, "Routes reachable without their role check:\n" . implode("\n", $missing));
    }

    public function testProtectedPrefixesActuallyMatchRoutes(): void
    {
        // Without this the test above passes by matching nothing, which is how a guard test rots.
        foreach (self::PROTECTED_PREFIXES as $prefix => $role) {
            $matched = array_filter(
                $this->apiControllers(),
                static fn (array $route): bool => str_starts_with($route['path'], $prefix),
            );

            self::assertNotEmpty($matched, sprintf('No route found under %s, so %s is never checked', $prefix, $role));
        }
    }

    public function testEachControllerClassServesExactlyOneAction(): void
    {
        $actionsPerClass = [];

        foreach ($this->apiControllers() as ['class' => $class, 'method' => $method]) {
            $actionsPerClass[$class][$method] = true;
        }

        $offenders = [];
        foreach ($actionsPerClass as $class => $methods) {
            if (count($methods) > 1 && !in_array($class, self::PENDING_CONVERSION, true)) {
                $offenders[] = sprintf('%s has %d actions: %s', $class, count($methods), implode(', ', array_keys($methods)));
            }
        }

        self::assertSame([], $offenders, "Controllers holding more than one action:\n" . implode("\n", $offenders));
    }

    public function testPendingConversionListHasNoStaleEntries(): void
    {
        $actionsPerClass = [];

        foreach ($this->apiControllers() as ['class' => $class, 'method' => $method]) {
            $actionsPerClass[$class][$method] = true;
        }

        foreach (self::PENDING_CONVERSION as $class) {
            self::assertArrayHasKey($class, $actionsPerClass, sprintf('%s no longer exists, drop it from PENDING_CONVERSION', $class));
            self::assertGreaterThan(
                1,
                count($actionsPerClass[$class]),
                sprintf('%s is already down to one action, drop it from PENDING_CONVERSION', $class),
            );
        }
    }

    /**
     * @return array<string, array{class: class-string, method: string, path: string}> keyed by route name
     */
    private function apiControllers(): array
    {
        $router = self::getContainer()->get(RouterInterface::class);
        $controllers = [];

        foreach ($router->getRouteCollection() as $routeName => $route) {
            $controller = $route->getDefault('_controller');

            if (!is_string($controller) || !str_starts_with($controller, 'App\\')) {
                continue;
            }

            // Symfony registers an invokable controller as the bare class name, no "::method"
            $parts = explode('::', $controller);
            $controllers[$routeName] = [
                'class' => $parts[0],
                'method' => $parts[1] ?? '__invoke',
                'path' => $route->getPath(),
            ];
        }

        return $controllers;
    }

    private function grantsRole(string $class, string $method, string $role): bool
    {
        $reflectionClass = new \ReflectionClass($class);
        $attributes = [
            ...$reflectionClass->getAttributes(IsGranted::class),
            ...$reflectionClass->getMethod($method)->getAttributes(IsGranted::class),
        ];

        foreach ($attributes as $attribute) {
            if ($attribute->newInstance()->attribute === $role) {
                return true;
            }
        }

        return false;
    }
}
