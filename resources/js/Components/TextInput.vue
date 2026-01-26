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
        class="w-full bg-[var(--bg-color)] border-[var(--border-color)] text-[var(--main-color)] focus:border-[var(--caret-color)] focus:ring-[var(--caret-color)]/20 rounded-xl shadow-sm font-mono placeholder-[var(--sub-color)]/40 transition-all duration-200 autofill:shadow-[0_0_0_1000px_var(--bg-color)_inset] autofill:text-[var(--main-color)]"
        style="-webkit-text-fill-color: var(--main-color)"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>

<style scoped>
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px var(--bg-color) inset !important;
    -webkit-text-fill-color: var(--main-color) !important;
    transition: background-color 5000s ease-in-out 0s;
}
</style>
