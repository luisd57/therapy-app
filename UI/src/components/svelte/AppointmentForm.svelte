<script lang="ts">
  import type { SlotData, Modality, LockResponse, AppointmentSummary } from '../../types/api';
  import { ApiError } from '../../types/api';
  import { requestAppointment } from '../../services/api';
  import { formatDate, formatTime } from '../../utils/dates';
  import ErrorBanner from './ErrorBanner.svelte';

  interface Props {
    slot: SlotData;
    modality: Modality;
    lockData: LockResponse | null;
    lockWarning?: string;
    onSuccess: (appointment: AppointmentSummary) => void;
    onBack: (errorMessage?: string) => void;
  }

  let { slot, modality, lockData, lockWarning, onSuccess, onBack }: Props = $props();

  let fullName = $state('');
  let phone = $state('');
  let email = $state('');
  let city = $state('');
  let country = $state('');
  let fieldErrors: Record<string, string> = $state({});
  let topError = $state('');
  let isSubmitting = $state(false);

  const modalityLabel = modality === 'ONLINE' ? 'Online' : 'Presencial';

  async function handleSubmit() {
    fieldErrors = {};
    topError = '';
    isSubmitting = true;

    const body: Record<string, string> = {
      slot_start_time: slot.start_time,
      modality,
      full_name: fullName,
      phone,
      email,
      city,
      country,
    };

    if (lockData?.lock_token) {
      body.lock_token = lockData.lock_token;
    }

    try {
      const response = await requestAppointment(body as any);
      onSuccess(response.appointment);
    } catch (err) {
      if (!(err instanceof ApiError)) {
        topError = 'Error de conexión. Por favor intenta de nuevo.';
        isSubmitting = false;
        return;
      }

      if (err.code === 'SLOT_NOT_AVAILABLE') {
        onBack('Este horario ya no está disponible.');
        return;
      }

      if (err.code === 'INVALID_LOCK_TOKEN') {
        // Lock expired — retry without lock token
        try {
          delete body.lock_token;
          const response = await requestAppointment(body as any);
          onSuccess(response.appointment);
          return;
        } catch (retryErr) {
          if (retryErr instanceof ApiError && retryErr.code === 'SLOT_NOT_AVAILABLE') {
            onBack('Este horario ya no está disponible.');
            return;
          }
          if (retryErr instanceof ApiError && retryErr.code === 'VALIDATION_ERROR' && retryErr.details) {
            fieldErrors = retryErr.details;
          } else {
            topError = retryErr instanceof ApiError ? retryErr.message : 'Error inesperado.';
          }
          isSubmitting = false;
          return;
        }
      }

      if (err.code === 'VALIDATION_ERROR' && err.details) {
        fieldErrors = err.details;
      } else {
        topError = err.message;
      }
    } finally {
      isSubmitting = false;
    }
  }
</script>

<div class="mx-auto max-w-lg">
  <!-- Selected slot header -->
  <div class="mb-6 rounded-xl bg-brand-50 border border-brand-200 p-4 text-center">
    <p class="text-sm font-medium text-brand-700 capitalize">
      {formatDate(slot.start_time)}
    </p>
    <p class="text-2xl font-bold text-brand-800">
      {formatTime(slot.start_time)}
    </p>
    <span class="mt-1 inline-block rounded-full bg-brand-100 px-3 py-1 text-xs font-medium text-brand-700">
      {modalityLabel}
    </span>
  </div>

  <button
    onclick={() => onBack()}
    class="mb-4 text-sm text-neutral-600 hover:text-neutral-900"
  >
    &larr; Cambiar horario
  </button>

  {#if lockWarning}
    <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-amber-800 text-sm">
      {lockWarning}
    </div>
  {/if}

  {#if topError}
    <div class="mb-4">
      <ErrorBanner message={topError} onDismiss={() => (topError = '')} />
    </div>
  {/if}

  <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
    <div>
      <label for="fullName" class="block text-sm font-medium text-neutral-700 mb-1">
        Nombre completo
      </label>
      <input
        id="fullName"
        type="text"
        bind:value={fullName}
        required
        class="w-full rounded-lg border {fieldErrors.full_name ? 'border-red-400' : 'border-neutral-300'}
               px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
      />
      {#if fieldErrors.full_name}
        <p class="mt-1 text-xs text-red-600">{fieldErrors.full_name}</p>
      {/if}
    </div>

    <div>
      <label for="phone" class="block text-sm font-medium text-neutral-700 mb-1">
        Teléfono
      </label>
      <input
        id="phone"
        type="tel"
        bind:value={phone}
        required
        placeholder="+584141234567"
        class="w-full rounded-lg border {fieldErrors.phone ? 'border-red-400' : 'border-neutral-300'}
               px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
      />
      {#if fieldErrors.phone}
        <p class="mt-1 text-xs text-red-600">{fieldErrors.phone}</p>
      {/if}
    </div>

    <div>
      <label for="email" class="block text-sm font-medium text-neutral-700 mb-1">
        Correo electrónico
      </label>
      <input
        id="email"
        type="email"
        bind:value={email}
        required
        class="w-full rounded-lg border {fieldErrors.email ? 'border-red-400' : 'border-neutral-300'}
               px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
      />
      {#if fieldErrors.email}
        <p class="mt-1 text-xs text-red-600">{fieldErrors.email}</p>
      {/if}
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label for="city" class="block text-sm font-medium text-neutral-700 mb-1">
          Ciudad
        </label>
        <input
          id="city"
          type="text"
          bind:value={city}
          required
          class="w-full rounded-lg border {fieldErrors.city ? 'border-red-400' : 'border-neutral-300'}
                 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
        />
        {#if fieldErrors.city}
          <p class="mt-1 text-xs text-red-600">{fieldErrors.city}</p>
        {/if}
      </div>
      <div>
        <label for="country" class="block text-sm font-medium text-neutral-700 mb-1">
          País
        </label>
        <input
          id="country"
          type="text"
          bind:value={country}
          required
          class="w-full rounded-lg border {fieldErrors.country ? 'border-red-400' : 'border-neutral-300'}
                 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
        />
        {#if fieldErrors.country}
          <p class="mt-1 text-xs text-red-600">{fieldErrors.country}</p>
        {/if}
      </div>
    </div>

    <button
      type="submit"
      disabled={isSubmitting}
      class="w-full rounded-lg bg-brand-500 px-6 py-3 text-sm font-semibold text-white
             transition-colors hover:bg-brand-600 disabled:opacity-50 disabled:cursor-wait"
    >
      {#if isSubmitting}
        Enviando...
      {:else}
        Solicitar cita
      {/if}
    </button>
  </form>
</div>
