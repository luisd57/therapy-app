<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import type { SlotData, ModalityFilter, Modality, LockResponse } from '../../types/api';
  import { ApiError } from '../../types/api';
  import { fetchAvailableSlots, lockSlot } from '../../services/api';
  import {
    getWeekStart,
    getWeekDates,
    getWeekdayName,
    formatShortDate,
    toDateParam,
  } from '../../utils/dates';
  import ModalityToggle from './ModalityToggle.svelte';
  import SlotCard from './SlotCard.svelte';
  import ErrorBanner from './ErrorBanner.svelte';

  interface Props {
    onSlotSelected: (slot: SlotData, modality: Modality, lockData: LockResponse | null) => void;
    errorMessage?: string;
  }

  let { onSlotSelected, errorMessage = '' }: Props = $props();

  let weekStart = $state(getWeekStart(new Date()));
  let modality: ModalityFilter = $state('ALL');
  let slotsByDate: Record<string, SlotData[]> = $state({});
  let isLoading = $state(false);
  let lockingSlot: string | null = $state(null);
  let error = $state(errorMessage);
  let refreshInterval: ReturnType<typeof setInterval> | undefined;

  const MAX_AUTO_ADVANCE_WEEKS = 4;
  let isInitialLoad = true;
  let autoAdvanceAttempts = 0;

  const weekDates = $derived(getWeekDates(weekStart));

  async function loadSlots() {
    isLoading = true;
    error = '';
    try {
      const weekEnd = new Date(weekStart);
      weekEnd.setDate(weekStart.getDate() + 6);

      const response = await fetchAvailableSlots({
        from: toDateParam(weekStart),
        to: toDateParam(weekEnd),
        modality: modality === 'ALL' ? undefined : modality,
      });

      if (isInitialLoad && response.total_slots === 0 && autoAdvanceAttempts < MAX_AUTO_ADVANCE_WEEKS) {
        autoAdvanceAttempts++;
        const d = new Date(weekStart);
        d.setDate(d.getDate() + 7);
        weekStart = d;
        return loadSlots();
      }

      isInitialLoad = false;
      autoAdvanceAttempts = 0;
      slotsByDate = response.slots_by_date;
    } catch (err) {
      if (err instanceof ApiError) {
        error = err.message;
      } else {
        error = 'Error de conexión. Por favor intenta de nuevo.';
      }
    } finally {
      isLoading = false;
    }
  }

  function prevWeek() {
    const now = getWeekStart(new Date());
    if (weekStart <= now) return;
    const d = new Date(weekStart);
    d.setDate(d.getDate() - 7);
    weekStart = d;
    loadSlots();
  }

  function nextWeek() {
    const d = new Date(weekStart);
    d.setDate(d.getDate() + 7);
    weekStart = d;
    loadSlots();
  }

  function onModalityChange(value: ModalityFilter) {
    modality = value;
    loadSlots();
  }

  async function handleSlotClick(slot: SlotData) {
    const selectedModality: Modality = modality === 'ALL' ? 'ONLINE' : modality;
    lockingSlot = slot.start_time;
    error = '';

    try {
      const lockData = await lockSlot({
        slot_start_time: slot.start_time,
        modality: selectedModality,
      });
      onSlotSelected(slot, selectedModality, lockData);
    } catch (err) {
      if (err instanceof ApiError && err.code === 'SLOT_NOT_AVAILABLE') {
        error = 'Este horario ya no está disponible. Por favor selecciona otro.';
        await loadSlots();
      } else {
        // Lock failed for another reason — proceed without lock
        onSlotSelected(slot, selectedModality, null);
      }
    } finally {
      lockingSlot = null;
    }
  }

  function isPastWeek(): boolean {
    const now = getWeekStart(new Date());
    return weekStart <= now;
  }

  onMount(() => {
    loadSlots();
    refreshInterval = setInterval(loadSlots, 30000);
  });

  onDestroy(() => {
    if (refreshInterval) clearInterval(refreshInterval);
  });
</script>

<div class="space-y-6">
  <!-- Controls -->
  <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
    <ModalityToggle value={modality} onChange={onModalityChange} />
    <div class="flex items-center gap-2">
      <button
        onclick={prevWeek}
        disabled={isPastWeek()}
        class="rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium
               hover:bg-neutral-50 disabled:opacity-30 disabled:cursor-not-allowed"
      >
        &larr; Anterior
      </button>
      <span class="text-sm font-medium text-neutral-700 min-w-[120px] text-center">
        {formatShortDate(toDateParam(weekStart))} &ndash; {formatShortDate(toDateParam(new Date(weekStart.getTime() + 6 * 86400000)))}
      </span>
      <button
        onclick={nextWeek}
        class="rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium hover:bg-neutral-50"
      >
        Siguiente &rarr;
      </button>
    </div>
  </div>

  {#if error}
    <ErrorBanner message={error} onDismiss={() => (error = '')} />
  {/if}

  <!-- Slot Grid -->
  {#if isLoading && Object.keys(slotsByDate).length === 0}
    <div class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></div>
    </div>
  {:else}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3">
      {#each weekDates as dateStr}
        {@const daySlots = slotsByDate[dateStr] ?? []}
        <div class="rounded-xl border {daySlots.length > 0 ? 'border-neutral-200 bg-white' : 'border-neutral-100 bg-neutral-50'} p-3">
          <div class="mb-2 text-center">
            <div class="text-xs font-medium uppercase text-neutral-500 capitalize">
              {getWeekdayName(dateStr)}
            </div>
            <div class="text-sm font-semibold text-neutral-800">
              {formatShortDate(dateStr)}
            </div>
          </div>
          {#if daySlots.length > 0}
            <div class="space-y-2">
              {#each daySlots as slot}
                <SlotCard
                  {slot}
                  onClick={() => handleSlotClick(slot)}
                  isLoading={lockingSlot === slot.start_time}
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
