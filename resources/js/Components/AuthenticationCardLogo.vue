<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'" class="inline-flex items-baseline gap-2">
        <span class="font-cinzel text-2xl font-semibold text-[var(--main-color)] hover:text-[var(--caret-color)] transition-colors">QuranTyping</span>
        <span class="text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)]">مصحف</span>
    </Link>
</template>
