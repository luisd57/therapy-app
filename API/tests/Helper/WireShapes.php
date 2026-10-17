<?php

declare(strict_types=1);

namespace App\Tests\Helper;

/**
 * The key set of each shape the API sends, read by every test that pins it. Hand-written on purpose:
 * a list built from the DTO would agree with any implementation (ADR-0003).
 */
final class WireShapes
{
    public const SUCCESS_ENVELOPE = ['success', 'data'];
    public const ERROR_ENVELOPE = ['success', 'error'];
    public const ERROR = ['code', 'message'];
    public const PAGINATION = ['page', 'limit', 'total', 'total_pages'];

    public const APPOINTMENT = [
        'id', 'start_time', 'end_time', 'modality', 'status',
        'full_name', 'email', 'phone', 'city', 'country',
        'patient_id', 'payment_verified', 'created_at', 'updated_at', 'requester_timezone',
    ];

    /** Trimmed by PatientAppointmentController. */
    public const PATIENT_APPOINTMENT = ['id', 'start_time', 'end_time', 'modality', 'status', 'patient_id', 'created_at'];

    /** Trimmed by RequestAppointmentController. */
    public const PUBLIC_APPOINTMENT = ['id', 'start_time', 'end_time', 'modality', 'status', 'created_at'];

    public const USER = [
        'id', 'email', 'full_name', 'role', 'is_active', 'phone',
        'address', 'created_at', 'activated_at', 'timezone',
    ];

    public const ADDRESS = ['street', 'city', 'country', 'postal_code', 'state'];

    public const AVAILABLE_SLOTS = ['from', 'to', 'modality', 'practice_timezone', 'slots', 'total_slots'];
    public const NEXT_AVAILABLE_WEEK = ['found', 'week_start', 'week_end', 'modality', 'practice_timezone', 'slots', 'total_slots'];
    public const SLOT = ['start_time', 'end_time', 'duration_minutes'];
    public const SLOT_LOCK = ['lock_token', 'slot_start_time', 'slot_end_time', 'expires_at'];

    public const SCHEDULE_BLOCK = [
        'id', 'day_of_week', 'day_name', 'start_time', 'end_time',
        'supports_online', 'supports_in_person', 'is_active',
    ];

    public const SCHEDULE_EXCEPTION = ['id', 'start_date_time', 'end_date_time', 'reason', 'is_all_day', 'created_at'];

    public const INVITATION = ['id', 'email', 'patient_name', 'status', 'created_at', 'expires_at'];

    /** Trimmed by ValidateInvitationController. */
    public const INVITATION_PREVIEW = ['id', 'patient_name', 'status', 'created_at', 'expires_at'];
}
