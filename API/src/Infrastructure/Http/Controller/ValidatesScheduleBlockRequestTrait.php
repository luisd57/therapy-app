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
    private function validateScheduleRequest(ValidatorInterface $validator, array $data): array
    {
        $errors = [];

        if (!isset($data['day_of_week'])) {
            $errors['day_of_week'] = 'Day of week is required';
        } else {
            $dayViolations = $validator->validate($data['day_of_week'], [
                new Assert\Range(min: 1, max: 7, notInRangeMessage: 'Day of week must be between 1 (Monday) and 7 (Sunday)'),
            ]);

            if (!is_numeric($data['day_of_week']) || count($dayViolations) > 0) {
                $errors['day_of_week'] = 'Day of week must be between 1 (Monday) and 7 (Sunday)';
            }
        }

        $startViolations = $validator->validate($data['start_time'] ?? '', [
            new Assert\NotBlank(message: 'Start time is required'),
            new Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'Start time must be in HH:MM format'),
        ]);

        if (count($startViolations) > 0) {
            $errors['start_time'] = $startViolations[0]->getMessage();
        }

        $endViolations = $validator->validate($data['end_time'] ?? '', [
            new Assert\NotBlank(message: 'End time is required'),
            new Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'End time must be in HH:MM format'),
        ]);

        if (count($endViolations) > 0) {
            $errors['end_time'] = $endViolations[0]->getMessage();
        }

        if (empty($errors['start_time']) && empty($errors['end_time']) && ($data['start_time'] ?? '') >= ($data['end_time'] ?? '')) {
            $errors['end_time'] = 'End time must be after start time';
        }

        return $errors;
    }
}
