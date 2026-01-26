<script setup>
defineProps({
    value: String,
});
</script>

<template>
    <label class="block font-cinzel text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)] mb-2">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
