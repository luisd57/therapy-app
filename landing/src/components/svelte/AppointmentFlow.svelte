<script lang="ts">
  import type { SlotData, Modality, LockResponse, AppointmentSummary } from '../../types/api';
  import { ApiError } from '../../types/api';
  import { lockSlot } from '../../services/api';
  import SlotBrowser from './SlotBrowser.svelte';
  import AppointmentForm from './AppointmentForm.svelte';
  import ThankYou from './ThankYou.svelte';

  type FlowStep =
    | { step: 'browsing'; errorMessage?: string }
    | { step: 'filling_form'; slot: SlotData; modality: Modality; lockData: LockResponse | null; lockWarning?: string }
    | { step: 'success'; appointment: AppointmentSummary };

  let current: FlowStep = $state({ step: 'browsing' });

  function handleSlotSelected(slot: SlotData, modality: Modality) {
    // Optimistic: show form immediately, lock in background
    current = { step: 'filling_form', slot, modality, lockData: null };

    lockSlot({ slot_start_time: slot.start_time, modality })
      .then((lockData) => {
        if (current.step === 'filling_form') {
          current = { ...current, lockData };
        }
      })
      .catch((err: unknown) => {
        if (current.step !== 'filling_form') return;
        if (err instanceof ApiError && err.code === 'SLOT_NOT_AVAILABLE') {
          current = {
            ...current,
            lockWarning: 'Este horario puede haber sido tomado, pero puedes enviar tu solicitud igual.',
          };
        }
        // Network errors or other failures: silently proceed without lock
      });
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
      lockWarning={current.lockWarning}
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
