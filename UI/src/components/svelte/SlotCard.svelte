<script lang="ts">
  import type { SlotData } from '../../types/api';
  import { formatTime } from '../../utils/dates';

  interface Props {
    slot: SlotData;
    onClick: () => void;
    isLoading: boolean;
  }

  let { slot, onClick, isLoading }: Props = $props();
</script>

<button
  onclick={onClick}
  disabled={isLoading}
  class="w-full rounded-lg border border-neutral-200 bg-white px-4 py-3 text-left
         transition-all hover:border-brand-500 hover:bg-brand-50 hover:shadow-sm
         disabled:opacity-50 disabled:cursor-wait"
>
  <span class="text-sm font-semibold text-neutral-900">
    {formatTime(slot.start_time)}
  </span>
  <span class="ml-2 text-xs text-neutral-500">
    {slot.duration_minutes} min
  </span>
  {#if isLoading}
    <span class="ml-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></span>
  {/if}
</button>
