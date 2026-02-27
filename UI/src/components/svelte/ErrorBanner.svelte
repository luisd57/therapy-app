<script lang="ts">
  interface Props {
    message: string;
    onDismiss?: () => void;
  }

  let { message, onDismiss }: Props = $props();
</script>

{#if message}
  <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-red-800 text-sm flex items-start gap-3">
    <svg class="h-5 w-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
    </svg>
    <span class="flex-1">{message}</span>
    {#if onDismiss}
      <button onclick={onDismiss} class="text-red-600 hover:text-red-800 font-medium text-sm">
        Cerrar
      </button>
    {/if}
  </div>
{/if}
