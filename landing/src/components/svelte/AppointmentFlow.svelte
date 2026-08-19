<script lang="ts">
  import type { SlotData, Modality, LockResponse, AppointmentSummary } from '../../types/api';
  import { ApiError } from '../../types/api';
  import { lockSlot } from '../../services/api';
  import { PRACTICE_TIMEZONE_FALLBACK } from '../../config';
  import { detectTimeZone } from '../../utils/dates';
  import { preselectedModality } from '../../utils/modality';
  import ModalityChooser from './ModalityChooser.svelte';
  import SlotBrowser from './SlotBrowser.svelte';
  import AppointmentForm from './AppointmentForm.svelte';
  import ThankYou from './ThankYou.svelte';

  // Held here so the form and confirmation keep showing both times after the
  // browser is unmounted.
  let viewerZone = $state(detectTimeZone());
  let practiceZone = $state(PRACTICE_TIMEZONE_FALLBACK);

  // Modality rides on every step after the choice, so no step can render
  // without one and nothing has to guess a default later.
  type FlowStep =
    | { step: 'choosing_modality'; preselected: Modality | null }
    | { step: 'browsing'; modality: Modality; errorMessage?: string }
    | { step: 'filling_form'; slot: SlotData; modality: Modality; lockData: LockResponse | null; lockWarning?: string }
    | { step: 'success'; modality: Modality; appointment: AppointmentSummary };

  let current: FlowStep = $state({
    step: 'choosing_modality',
    preselected: preselectedModality(viewerZone, practiceZone),
  });

  function handleModalityChosen(modality: Modality) {
    current = { step: 'browsing', modality };
  }

  function handleSlotSelected(
    slot: SlotData,
    modality: Modality,
    selectedPracticeZone: string,
  ) {
    practiceZone = selectedPracticeZone;

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
    if (current.step !== 'filling_form') return;
    current = { step: 'browsing', modality: current.modality, errorMessage };
  }

  function handleSuccess(appointment: AppointmentSummary) {
    if (current.step !== 'filling_form') return;
    current = { step: 'success', modality: current.modality, appointment };
  }

  function handleRestart() {
    if (current.step !== 'success') return;
    current = { step: 'browsing', modality: current.modality };
  }
</script>

<div>
  {#if current.step === 'choosing_modality'}
    <ModalityChooser
      preselected={current.preselected}
      onChoose={handleModalityChosen}
    />
  {:else if current.step === 'browsing'}
    <SlotBrowser
      initialModality={current.modality}
      onSlotSelected={handleSlotSelected}
      errorMessage={current.errorMessage}
    />
  {:else if current.step === 'filling_form'}
    <AppointmentForm
      slot={current.slot}
      modality={current.modality}
      {viewerZone}
      {practiceZone}
      lockData={current.lockData}
      lockWarning={current.lockWarning}
      onSuccess={handleSuccess}
      onBack={handleBack}
    />
  {:else if current.step === 'success'}
    <ThankYou
      appointment={current.appointment}
      {viewerZone}
      {practiceZone}
      onRestart={handleRestart}
    />
  {/if}
</div>
