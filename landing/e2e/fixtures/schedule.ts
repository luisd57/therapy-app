import { request, type APIRequestContext, type FullConfig } from '@playwright/test';
import { API_BASE_URL } from './helpers';

const THERAPIST_EMAIL: string = process.env['THERAPIST_EMAIL'] ?? 'therapist@example.com';
const THERAPIST_PASSWORD: string = process.env['THERAPIST_PASSWORD'] ?? 'VerifyPass1!';

/** A recurring weekly availability window, in the shape the therapist API takes. */
export interface ScheduleBlock {
  day_of_week: number;
  start_time: string;
  end_time: string;
  supports_online: boolean;
  supports_in_person: boolean;
}

/** The block the swap installs: availability the recurring seed never produces on its own. */
export const IN_PERSON_ONLY_BLOCK: ScheduleBlock = {
  day_of_week: 1, // Monday
  start_time: '09:00',
  end_time: '12:00',
  supports_online: false,
  supports_in_person: true,
};

interface ListedBlock extends ScheduleBlock {
  id: string;
  is_active: boolean;
}

async function readJson(response: Awaited<ReturnType<APIRequestContext['get']>>): Promise<{
  success: boolean;
  data?: { schedules?: ListedBlock[] };
  error?: { message?: string };
}> {
  return (await response.json()) as never;
}

/**
 * Log in as the therapist. The JWT is an httpOnly cookie, so it rides on the
 * returned context and every later call through it is authenticated.
 */
export async function therapistContext(): Promise<APIRequestContext> {
  const context: APIRequestContext = await request.newContext();
  const response = await context.post(`${API_BASE_URL}/auth/therapist/login`, {
    data: { email: THERAPIST_EMAIL, password: THERAPIST_PASSWORD },
  });

  if (!response.ok()) {
    await context.dispose();
    throw new Error(
      `Therapist login failed (${response.status()}). Set THERAPIST_EMAIL / ` +
        `THERAPIST_PASSWORD to match the seeded therapist.`,
    );
  }

  return context;
}

/**
 * The blocks that currently produce availability. Inactive ones are left out on
 * purpose: they cannot be recreated faithfully, and they generate no slots.
 */
export async function activeBlocks(context: APIRequestContext): Promise<ListedBlock[]> {
  const response = await context.get(`${API_BASE_URL}/therapist/schedule`);
  if (!response.ok()) {
    throw new Error(`Listing schedule blocks failed (${response.status()}).`);
  }

  const body = await readJson(response);
  return (body.data?.schedules ?? []).filter((block): boolean => block.is_active);
}

export async function deleteBlocks(
  context: APIRequestContext,
  blocks: ListedBlock[],
): Promise<void> {
  for (const block of blocks) {
    const response = await context.delete(`${API_BASE_URL}/therapist/schedule/${block.id}`);
    if (!response.ok()) {
      throw new Error(`Deleting schedule block ${block.id} failed (${response.status()}).`);
    }
  }
}

export async function createBlock(
  context: APIRequestContext,
  block: ScheduleBlock,
): Promise<void> {
  const response = await context.post(`${API_BASE_URL}/therapist/schedule`, { data: block });
  if (!response.ok()) {
    const body = await readJson(response);
    throw new Error(
      `Creating schedule block failed (${response.status()}): ${body.error?.message ?? ''}`,
    );
  }
}

/** The blocks without their ids, in a stable order, so two schedules compare by content. */
export function normalized(blocks: ScheduleBlock[]): ScheduleBlock[] {
  return blocks
    .map(
      (block: ScheduleBlock): ScheduleBlock => ({
        day_of_week: block.day_of_week,
        start_time: block.start_time,
        end_time: block.end_time,
        supports_online: block.supports_online,
        supports_in_person: block.supports_in_person,
      }),
    )
    .sort(
      (left: ScheduleBlock, right: ScheduleBlock): number =>
        left.day_of_week - right.day_of_week || left.start_time.localeCompare(right.start_time),
    );
}

/** Make `blocks` the whole active schedule. */
export async function setSchedule(
  context: APIRequestContext,
  blocks: ScheduleBlock[],
): Promise<void> {
  await deleteBlocks(context, await activeBlocks(context));
  for (const block of blocks) {
    await createBlock(context, block);
  }
}

const BASELINE_ENV: string = 'LANDING_SCHEDULE_BASELINE';

/**
 * Record the schedule `restoreBaseline` puts back. Global setup calls this once per run,
 * before any spec and in the runner process, so a retry cannot re-record a swapped state.
 */
export async function recordBaseline(context: APIRequestContext): Promise<void> {
  const baseline: ScheduleBlock[] = normalized(await activeBlocks(context));

  if (JSON.stringify(baseline) === JSON.stringify(normalized([IN_PERSON_ONLY_BLOCK]))) {
    throw new Error(
      'The schedule is still the single block a killed run swapped in. Reseed it first: ' +
        '`docker-compose exec php php bin/console app:seed-schedule --force`',
    );
  }

  process.env[BASELINE_ENV] = JSON.stringify(baseline);
}

/** The schedule global setup recorded. Workers inherit it through the environment. */
export function seededBaseline(): ScheduleBlock[] {
  const recorded: string | undefined = process.env[BASELINE_ENV];
  if (recorded === undefined) {
    throw new Error(`${BASELINE_ENV} is not set. Global setup records it, so run through the config.`);
  }

  return JSON.parse(recorded) as ScheduleBlock[];
}

/** Put the recorded baseline back, skipping the rewrite when it is already there. */
export async function restoreBaseline(context: APIRequestContext): Promise<void> {
  const baseline: ScheduleBlock[] = seededBaseline();
  const current: ScheduleBlock[] = normalized(await activeBlocks(context));

  if (JSON.stringify(current) !== JSON.stringify(baseline)) {
    await setSchedule(context, baseline);
  }
}

/** A swap is seen by every spec running beside it, so a swapping spec refuses to share the run. */
export function requireSoleWorker(config: FullConfig): void {
  if (config.workers !== 1) {
    throw new Error(
      `This spec swaps the schedule every other spec reads, so it needs workers: 1 ` +
        `(got ${config.workers}).`,
    );
  }
}

/**
 * Swap the whole schedule for one block. Availability is computed from these blocks,
 * so this is the only way to reach a state the seed never produces. Undo with `restoreBaseline`.
 */
export async function replaceScheduleWith(
  context: APIRequestContext,
  block: ScheduleBlock,
): Promise<void> {
  // Read first, so a missing baseline fails with the schedule untouched.
  seededBaseline();

  await setSchedule(context, [block]);
}
