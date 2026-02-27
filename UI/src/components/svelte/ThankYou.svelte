<script lang="ts">
  import type { AppointmentSummary } from '../../types/api';
  import { formatDate, formatTime } from '../../utils/dates';

  interface Props {
    appointment: AppointmentSummary;
    onRestart: () => void;
  }

  let { appointment, onRestart }: Props = $props();

  const modalityLabel = appointment.modality === 'ONLINE' ? 'Online' : 'Presencial';
</script>

<div class="mx-auto max-w-lg text-center">
  <div class="mb-6">
    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-100">
      <svg class="h-8 w-8 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
      </svg>
    </div>
  </div>

  <h3 class="text-2xl font-bold text-neutral-900">
    Solicitud recibida
  </h3>

  <p class="mt-3 text-neutral-600">
    Tu solicitud de cita ha sido enviada. La terapeuta se pondrá en contacto contigo para confirmar el pago y finalizar la cita.
  </p>

  <div class="mt-6 rounded-xl bg-neutral-50 border border-neutral-200 p-4">
    <p class="text-sm font-medium text-neutral-700 capitalize">
      {formatDate(appointment.start_time)}
    </p>
    <p class="text-lg font-bold text-neutral-900">
      {formatTime(appointment.start_time)}
    </p>
    <span class="mt-1 inline-block rounded-full bg-brand-100 px-3 py-1 text-xs font-medium text-brand-700">
      {modalityLabel}
    </span>
  </div>

  <button
    onclick={onRestart}
    class="mt-8 rounded-lg border border-neutral-200 px-6 py-3 text-sm font-medium
           text-neutral-700 hover:bg-neutral-50 transition-colors"
  >
    Reservar otra cita
  </button>
</div>
