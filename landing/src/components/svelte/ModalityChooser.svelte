<script lang="ts">
  import type { Modality } from '../../types/api';

  interface Props {
    /** Null leaves the choice open, which is what a viewer in the practice zone gets. */
    preselected: Modality | null;
    onChoose: (modality: Modality) => void;
  }

  let { preselected, onChoose }: Props = $props();

  let selected: Modality | null = $state(preselected);

  const options: { value: Modality; label: string; detail: string }[] = [
    {
      value: 'ONLINE',
      label: 'Online',
      detail: 'Por videollamada, desde donde estés.',
    },
    {
      value: 'IN_PERSON',
      label: 'Presencial',
      detail: 'En el consultorio, en Mérida, Venezuela.',
    },
  ];
</script>

<div class="mx-auto max-w-lg" data-testid="modality-chooser">
  <h3 id="modality-chooser-heading" class="text-center text-lg font-semibold text-neutral-900">
    ¿Cómo prefieres tu sesión?
  </h3>
  <p class="mt-2 text-center text-sm text-neutral-600">
    Los horarios disponibles dependen de la modalidad que elijas.
  </p>

  <div class="mt-6 space-y-3" role="group" aria-labelledby="modality-chooser-heading">
    {#each options as option}
      <button
        onclick={() => (selected = option.value)}
        aria-pressed={selected === option.value}
        data-testid="modality-option-{option.value}"
        class="w-full rounded-xl border p-4 text-left transition-colors {selected === option.value
          ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500'
          : 'border-neutral-200 bg-white hover:border-neutral-300'}"
      >
        <span class="block font-medium text-neutral-900">{option.label}</span>
        <span class="mt-1 block text-sm text-neutral-600">{option.detail}</span>
      </button>
    {/each}
  </div>

  <button
    onclick={() => selected && onChoose(selected)}
    disabled={selected === null}
    data-testid="modality-continue"
    class="mt-6 w-full rounded-lg bg-brand-600 px-4 py-3 font-medium text-white
           transition-colors hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-40"
  >
    Continuar
  </button>
</div>
