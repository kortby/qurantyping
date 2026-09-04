<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center min-h-[40px] px-5 bg-[var(--error-color)] text-white font-cinzel text-xs font-semibold uppercase tracking-[0.12em] hover:opacity-90 disabled:opacity-40 transition-opacity">
        <slot />
    </button>
</template>
