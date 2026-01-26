<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-6 py-2 bg-yellow-500 hover:bg-yellow-400 text-[#323437] border border-transparent rounded-xl font-mono text-sm font-bold tracking-widest active:scale-95 disabled:opacity-50 transition-all duration-200">
        <slot />
    </button>
</template>
