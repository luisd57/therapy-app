<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Shared by the create and update Schedule Block actions, which take the same body.
 */
trait ValidatesScheduleBlockRequestTrait
{
    /**
     * @return array<string, string>
     */
    private function validateScheduleBlockRequest(ValidatorInterface $validator, JsonBody $body): array
    {
        $errors = [];

        $dayOfWeek = $body->int('day_of_week');

        if (!$body->has('day_of_week')) {
            $errors['day_of_week'] = 'Day of week is required';
        } elseif ($dayOfWeek === null || $dayOfWeek < 1 || $dayOfWeek > 7) {
            $errors['day_of_week'] = 'Day of week must be between 1 (Monday) and 7 (Sunday)';
        }

        $startViolations = $validator->validate($body->string('start_time'), [
            new Assert\NotBlank(message: 'Start time is required'),
            new Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'Start time must be in HH:MM format'),
        ]);

        if (count($startViolations) > 0) {
            $errors['start_time'] = (string) $startViolations->get(0)->getMessage();
        }

        $endViolations = $validator->validate($body->string('end_time'), [
            new Assert\NotBlank(message: 'End time is required'),
            new Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'End time must be in HH:MM format'),
        ]);

        if (count($endViolations) > 0) {
            $errors['end_time'] = (string) $endViolations->get(0)->getMessage();
        }

        if (empty($errors['start_time']) && empty($errors['end_time']) && $body->string('start_time') >= $body->string('end_time')) {
            $errors['end_time'] = 'End time must be after start time';
        }

        foreach (['supports_online' => 'Supports online', 'supports_in_person' => 'Supports in person'] as $key => $label) {
            if ($body->hasNonBool($key)) {
                $errors[$key] = $label . ' must be true or false';
            }
        }

        return $errors;
    }
}
