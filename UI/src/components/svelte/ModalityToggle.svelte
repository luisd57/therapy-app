<script lang="ts">
  import type { ModalityFilter } from '../../types/api';

  interface Props {
    value: ModalityFilter;
    onChange: (value: ModalityFilter) => void;
  }

  let { value, onChange }: Props = $props();

  const options: { label: string; value: ModalityFilter }[] = [
    { label: 'Todas', value: 'ALL' },
    { label: 'Online', value: 'ONLINE' },
    { label: 'Presencial', value: 'IN_PERSON' },
  ];
</script>

<div class="flex gap-1 rounded-lg bg-neutral-100 p-1">
  {#each options as option}
    <button
      onclick={() => onChange(option.value)}
      class="rounded-md px-4 py-2 text-sm font-medium transition-colors {value === option.value
        ? 'bg-white text-brand-700 shadow-sm'
        : 'text-neutral-600 hover:text-neutral-900'}"
    >
      {option.label}
    </button>
  {/each}
</div>
