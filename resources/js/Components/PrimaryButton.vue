<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-6 py-2.5 bg-[var(--caret-color)] hover:bg-[var(--caret-color)] hover:scale-[1.02] text-[var(--bg-color)] border border-transparent rounded-xl font-cinzel text-xs font-bold tracking-widest active:scale-95 disabled:opacity-50 transition-all duration-200 shadow-lg shadow-black/10">
        <slot />
    </button>
</template>
