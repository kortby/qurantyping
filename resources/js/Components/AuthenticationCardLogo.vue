<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'">
        <div class="flex flex-col items-center gap-2 group">
            <span class="text-5xl group-hover:scale-110 transition-transform duration-300 drop-shadow-lg">📖</span>
            <span class="font-cinzel text-2xl text-[var(--caret-color)] tracking-[0.1em] font-bold">QuranTyping</span>
        </div>
    </Link>
</template>
