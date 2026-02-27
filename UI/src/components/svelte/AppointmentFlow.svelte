<script lang="ts">
  import type { SlotData, Modality, LockResponse, AppointmentSummary } from '../../types/api';
  import SlotBrowser from './SlotBrowser.svelte';
  import AppointmentForm from './AppointmentForm.svelte';
  import ThankYou from './ThankYou.svelte';

  type FlowStep =
    | { step: 'browsing'; errorMessage?: string }
    | { step: 'filling_form'; slot: SlotData; modality: Modality; lockData: LockResponse | null }
    | { step: 'success'; appointment: AppointmentSummary };

  let current: FlowStep = $state({ step: 'browsing' });

  function handleSlotSelected(slot: SlotData, modality: Modality, lockData: LockResponse | null) {
    current = { step: 'filling_form', slot, modality, lockData };
  }

  function handleBack(errorMessage?: string) {
    current = { step: 'browsing', errorMessage };
  }

  function handleSuccess(appointment: AppointmentSummary) {
    current = { step: 'success', appointment };
  }

  function handleRestart() {
    current = { step: 'browsing' };
  }
</script>

<div>
  {#if current.step === 'browsing'}
    <SlotBrowser
      onSlotSelected={handleSlotSelected}
      errorMessage={current.errorMessage}
    />
  {:else if current.step === 'filling_form'}
    <AppointmentForm
      slot={current.slot}
      modality={current.modality}
      lockData={current.lockData}
      onSuccess={handleSuccess}
      onBack={handleBack}
    />
  {:else if current.step === 'success'}
    <ThankYou
      appointment={current.appointment}
      onRestart={handleRestart}
    />
  {/if}
</div>
