<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center min-h-[40px] px-5 border border-[var(--border-color)] font-cinzel text-xs font-semibold uppercase tracking-[0.12em] text-[var(--sub-color)] hover:text-[var(--main-color)] hover:border-[var(--caret-color)] disabled:opacity-40 transition-colors">
        <slot />
    </button>
</template>
