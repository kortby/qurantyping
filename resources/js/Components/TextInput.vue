<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: String,
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        class="w-full bg-[#323437]/50 border-gray-700/50 text-[#d1d0c5] focus:border-yellow-500/50 focus:ring-yellow-500/20 rounded-xl shadow-sm font-mono"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
