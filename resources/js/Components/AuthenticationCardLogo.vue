<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'">
        <div class="flex flex-col items-center gap-2 group">
            <span class="text-4xl group-hover:scale-110 transition-transform duration-300">📖</span>
            <span class="font-mono text-xl text-yellow-500 tracking-tighter">QuranTyping</span>
        </div>
    </Link>
</template>
