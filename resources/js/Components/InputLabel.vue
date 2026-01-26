<script setup>
defineProps({
    value: String,
});
</script>

<template>
    <label class="block font-mono text-xs uppercase tracking-widest text-gray-500 mb-2">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
