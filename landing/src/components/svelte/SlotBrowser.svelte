<script lang="ts">
  import { onMount } from 'svelte';
  import type { SlotData, Modality } from '../../types/api';
  import { ApiError } from '../../types/api';
  import { PRACTICE_TIMEZONE_FALLBACK } from '../../config';
  import { fetchAvailableSlots, fetchNextAvailableWeek } from '../../services/api';
  import {
    detectTimeZone,
    dayKeyInZone,
    todayKeyInZone,
    weekStartKey,
    weekStartForAvailability,
    weekKeys,
    addDaysToKey,
    windowForKeys,
    formatShortDayKey,
    formatWeekdayDayKey,
    zoneLabel,
  } from '../../utils/dates';
  import ModalityToggle from './ModalityToggle.svelte';
  import SlotCard from './SlotCard.svelte';
  import ErrorBanner from './ErrorBanner.svelte';

  interface Props {
    /** Chosen before the grid renders. The toggle below can still change it. */
    initialModality: Modality;
    onSlotSelected: (
      slot: SlotData,
      modality: Modality,
      practiceZone: string,
    ) => void;
    errorMessage?: string;
  }

  let { initialModality, onSlotSelected, errorMessage = '' }: Props = $props();

  // The grid is laid out in the viewer's zone, so someone in Madrid sees each
  // slot under the day it falls on for them, not for the practice.
  let viewerZone = $state(detectTimeZone());
  let practiceZone = $state(PRACTICE_TIMEZONE_FALLBACK);

  let weekStart = $state(weekStartKey(todayKeyInZone(detectTimeZone())));
  let modality: Modality = $state(initialModality);
  let slots: SlotData[] = $state([]);
  let isLoading = $state(false);
  let isInitialLoading = $state(true);
  let error = $state(errorMessage);

  const weekDates = $derived(weekKeys(weekStart));

  /** Slots bucketed by the day they fall on in the viewer's zone. */
  const slotsByDay = $derived(
    slots.reduce<Record<string, SlotData[]>>((buckets, slot) => {
      const key = dayKeyInZone(slot.start_time, viewerZone);
      (buckets[key] ??= []).push(slot);
      return buckets;
    }, {}),
  );

  const showsBothZones = $derived(viewerZone !== practiceZone);

  /** Slots landing in the rendered columns, which is what the viewer can act on. */
  const visibleSlotCount = $derived(
    weekDates.reduce((total, key) => total + (slotsByDay[key]?.length ?? 0), 0),
  );

  async function loadSlots() {
    isLoading = true;
    error = '';
    try {
      const response = await fetchAvailableSlots({
        ...windowForKeys(weekDates),
        modality,
      });

      slots = response.slots;
      practiceZone = response.practice_timezone;
    } catch (err) {
      // Drop what is on screen. It was fetched for another week or another
      // modality, and clicking it would submit a slot nobody can host.
      slots = [];
      error =
        err instanceof ApiError
          ? err.message
          : 'Error de conexión. Por favor intenta de nuevo.';
    } finally {
      isLoading = false;
    }
  }

  function prevWeek() {
    if (isPastWeek()) return;
    weekStart = addDaysToKey(weekStart, -7);
    loadSlots();
  }

  function nextWeek() {
    weekStart = addDaysToKey(weekStart, 7);
    loadSlots();
  }

  function onModalityChange(value: Modality) {
    if (value === modality) return;
    modality = value;
    // Cleared before the refetch, so a slow or failed response never leaves the
    // previous modality's slots on screen under the new label.
    slots = [];
    if (isInitialLoading) return;
    loadSlots();
  }

  function handleSlotClick(slot: SlotData) {
    // The browsed modality, never a substitute: the slot came from a schedule
    // block filtered on this value, and anything else the API refuses.
    onSlotSelected(slot, modality, practiceZone);
  }

  /**
   * Comparing day keys as strings, not Dates: the previous version compared a
   * noon-anchored Date against a midnight one, so the guard silently stopped
   * working after the first load and let people page into past weeks.
   */
  function isPastWeek(): boolean {
    return weekStart <= weekStartKey(todayKeyInZone(viewerZone));
  }

  onMount(async () => {
    try {
      const response = await fetchNextAvailableWeek({ modality });

      practiceZone = response.practice_timezone;

      if (response.found && response.week_start) {
        // Resolved in the viewer's zone: which day an instant belongs to
        // depends on who is looking.
        weekStart = weekStartForAvailability(
          response.week_start,
          response.slots.map((slot) => slot.start_time),
          viewerZone,
        );
        // Refetch rather than keep response.slots: the rolling window covers
        // only part of the week we just picked, so later columns would be wrong.
        await loadSlots();
      } else {
        slots = [];
      }
    } catch (err) {
      error = 'Error de conexión. Por favor intenta de nuevo.';
    } finally {
      isInitialLoading = false;
    }
  });
</script>

<div class="space-y-4">
  <!-- Which zone the times below are in. Never leave this implicit: a silent
       conversion is how people miss a session by several hours. -->
  <div
    class="rounded-lg border border-brand-100 bg-brand-50/60 px-4 py-2.5 text-sm text-neutral-700"
    data-testid="timezone-banner"
  >
    Horarios en <strong class="font-semibold">{zoneLabel(viewerZone)}</strong>
    {#if showsBothZones}
      &middot; consultorio en <strong class="font-semibold">{zoneLabel(practiceZone)}</strong>
    {/if}
  </div>

  <!-- Controls -->
  <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
    <ModalityToggle value={modality} onChange={onModalityChange} />
    <div class="flex items-center gap-2">
      <button
        onclick={prevWeek}
        disabled={isPastWeek() || isLoading || isInitialLoading}
        class="rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium
               hover:bg-neutral-50 disabled:opacity-30 disabled:cursor-not-allowed"
      >
        &larr; Anterior
      </button>
      <span class="text-sm font-medium text-neutral-700 min-w-[120px] text-center">
        {formatShortDayKey(weekDates[0])} &ndash; {formatShortDayKey(weekDates[6])}
      </span>
      <button
        onclick={nextWeek}
        disabled={isLoading || isInitialLoading}
        class="rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium hover:bg-neutral-50
               disabled:opacity-30 disabled:cursor-not-allowed"
      >
        Siguiente &rarr;
      </button>
    </div>
  </div>

  {#if error}
    <ErrorBanner message={error} onDismiss={() => (error = '')} />
  {/if}

  {#if isInitialLoading || isLoading}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3">
      {#each Array(7) as _}
        <div class="rounded-xl border border-neutral-100 bg-neutral-50 p-3 animate-pulse">
          <div class="mb-3 mx-auto h-3 w-12 rounded bg-neutral-200"></div>
          <div class="mb-2 mx-auto h-2 w-8 rounded bg-neutral-200"></div>
          <div class="mt-4 space-y-2">
            <div class="h-10 rounded-lg bg-neutral-200"></div>
            <div class="h-10 rounded-lg bg-neutral-200"></div>
          </div>
        </div>
      {/each}
    </div>
  {:else if visibleSlotCount === 0 && !error}
    <!-- Seven "Sin disponibilidad" columns and nothing else is indistinguishable
         from a broken grid, so say it once and drop the grid. Not shown on
         error: a failed request is not the same as no availability. -->
    <p
      class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-6 text-center text-sm text-neutral-600"
      data-testid="week-empty"
    >
      No hay horarios disponibles en esta semana. Usa "Siguiente" para ver la próxima.
    </p>
  {:else}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3">
      {#each weekDates as dayKey}
        {@const daySlots = slotsByDay[dayKey] ?? []}
        <div class="rounded-xl border {daySlots.length > 0 ? 'border-neutral-200 bg-white' : 'border-neutral-100 bg-neutral-50'} p-3">
          <div class="mb-2 text-center">
            <div class="text-xs font-medium capitalize text-neutral-500">
              {formatWeekdayDayKey(dayKey)}
            </div>
            <div class="text-sm font-semibold text-neutral-800">
              {formatShortDayKey(dayKey)}
            </div>
          </div>
          {#if daySlots.length > 0}
            <div class="space-y-2">
              {#each daySlots as slot}
                <SlotCard
                  {slot}
                  timeZone={viewerZone}
                  onClick={() => handleSlotClick(slot)}
                  isLoading={false}
                />
              {/each}
            </div>
          {:else}
            <p class="py-4 text-center text-xs text-neutral-400">
              Sin disponibilidad
            </p>
          {/if}
        </div>
      {/each}
    </div>
  {/if}
</div>
