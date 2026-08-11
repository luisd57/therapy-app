<script lang="ts">
  import { formatFullDate, formatTime, zoneLabel } from '../../utils/dates';

  interface Props {
    /** ISO-8601 instant with an offset, exactly as the API emitted it. */
    startTime: string;
    viewerZone: string;
    practiceZone: string;
    /** Rendered smaller on the confirmation screen than on the form header. */
    size?: 'large' | 'medium';
  }

  let { startTime, viewerZone, practiceZone, size = 'large' }: Props = $props();

  // Only worth showing twice when the two differ. For a patient in Venezuela
  // a second line would just be noise.
  const showsBothZones = $derived(viewerZone !== practiceZone);
</script>

<p class="text-sm font-medium text-brand-700 capitalize">
  {formatFullDate(startTime, viewerZone)}
</p>
<p class="{size === 'large' ? 'text-2xl' : 'text-lg'} font-bold text-brand-800">
  {formatTime(startTime, viewerZone)}
  <span class="text-xs font-medium text-brand-600">tu hora</span>
</p>

{#if showsBothZones}
  <!-- The therapist's time, spelled out. This is the number she will say on the
       phone, and the one the patient would otherwise have to compute. -->
  <p class="mt-1 text-sm text-neutral-600" data-testid="practice-time">
    {formatTime(startTime, practiceZone)} en {zoneLabel(practiceZone)}
  </p>
{/if}
