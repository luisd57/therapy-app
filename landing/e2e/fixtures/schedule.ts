import { request, type APIRequestContext } from '@playwright/test';
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

/**
 * Swap the whole schedule for one block and hand back an undo. Availability is
 * computed from these blocks, so this is the only way to put the API in a state
 * the recurring seed never produces.
 */
export async function replaceScheduleWith(
  context: APIRequestContext,
  block: ScheduleBlock,
): Promise<() => Promise<void>> {
  const saved: ListedBlock[] = await activeBlocks(context);

  await deleteBlocks(context, saved);
  await createBlock(context, block);

  return async (): Promise<void> => {
    await deleteBlocks(context, await activeBlocks(context));
    for (const original of saved) {
      await createBlock(context, original);
    }
  };
}
