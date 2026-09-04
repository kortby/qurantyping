<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: Object,
    due: Array,
    daily_new: Number,
    has_new: Boolean,
});

const dailyNew = ref(props.daily_new);

const saveDailyNew = () => {
    router.post(route('user.settings.hifz-daily-new'), { hifz_daily_new: dailyNew.value }, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head><title>Hifz - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6">
                <header class="mb-8 flex items-end justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">Hifz</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                            Type from memory · spaced review
                        </p>
                    </div>
                    <Link href="/certificates" class="font-mono text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] hover:text-[var(--main-color)] transition-colors whitespace-nowrap">
                        Certificates →
                    </Link>
                </header>

                <!-- First run -->
                <div v-if="stats.total === 0" class="border border-[var(--border-color)] p-6 text-center">
                    <p class="text-[var(--main-color)] mb-2">You haven't started memorising anything yet.</p>
                    <p class="text-[var(--sub-color)] text-sm mb-6 max-w-md mx-auto">
                        Hifz mode hides the text as you type and brings each ayah back for review
                        on a widening schedule.
                    </p>
                    <Link href="/?hifz=1&mode=new" class="inline-flex items-center min-h-[44px] px-8 bg-[var(--caret-color)] text-[var(--bg-color)] font-cinzel font-semibold">
                        Learn your first ayah
                    </Link>
                </div>

                <template v-else>
                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <div class="border border-[var(--border-color)] p-4 text-center">
                            <p class="text-3xl font-cinzel font-semibold text-[var(--main-color)] tabular-nums">{{ stats.total }}</p>
                            <p class="text-[9px] uppercase tracking-[0.2em] text-[var(--sub-color)] mt-1 font-mono">ayahs</p>
                        </div>
                        <div class="border border-[var(--border-color)] p-4 text-center">
                            <p class="text-3xl font-cinzel font-semibold text-[var(--main-color)] tabular-nums">{{ stats.review }}</p>
                            <p class="text-[9px] uppercase tracking-[0.2em] text-[var(--sub-color)] mt-1 font-mono">mature</p>
                        </div>
                        <div class="border p-4 text-center" :class="stats.due > 0 ? 'border-[var(--caret-color)]' : 'border-[var(--border-color)]'">
                            <p class="text-3xl font-cinzel font-semibold tabular-nums" :class="stats.due > 0 ? 'text-[var(--caret-color)]' : 'text-[var(--main-color)]'">{{ stats.due }}</p>
                            <p class="text-[9px] uppercase tracking-[0.2em] text-[var(--sub-color)] mt-1 font-mono">due today</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row gap-3 mb-8">
                        <Link
                            v-if="stats.due > 0"
                            href="/?hifz=1&mode=due"
                            class="flex-1 inline-flex items-center justify-center min-h-[48px] bg-[var(--caret-color)] text-[var(--bg-color)] font-cinzel font-semibold"
                        >
                            Review {{ stats.due }} due
                        </Link>
                        <Link
                            v-if="has_new"
                            href="/?hifz=1&mode=new"
                            class="flex-1 inline-flex items-center justify-center min-h-[48px] border border-[var(--border-color)] text-[var(--sub-color)] font-cinzel font-semibold uppercase tracking-[0.12em] text-xs hover:text-[var(--main-color)] hover:border-[var(--caret-color)] transition-colors"
                        >
                            Learn new
                        </Link>
                        <p v-if="stats.due === 0 && !has_new" class="flex-1 text-center text-[var(--sub-color)] font-mono text-sm py-3">
                            All caught up. Nothing due, nothing new in range.
                        </p>
                    </div>

                    <!-- Due breakdown -->
                    <div v-if="due.length" class="border border-[var(--border-color)]">
                        <p class="px-4 py-3 font-mono text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)] border-b border-[var(--border-color)]">
                            Due for review
                        </p>
                        <ul class="divide-y divide-[var(--border-color)]">
                            <li v-for="row in due" :key="row.surah" class="px-4 py-3 flex items-center justify-between gap-4">
                                <span class="text-[var(--main-color)]">
                                    {{ row.surah }}
                                    <span class="text-[var(--sub-color)]" dir="rtl" style="font-family: 'Noto Naskh Arabic', serif;">{{ row.surah_arabic }}</span>
                                </span>
                                <span class="font-mono text-xs text-[var(--sub-color)]">
                                    {{ row.ayahs.length }} ayah{{ row.ayahs.length === 1 ? '' : 's' }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Setting -->
                    <div class="mt-8 flex items-center gap-3 font-mono text-sm">
                        <label for="daily_new" class="text-[var(--sub-color)] text-[10px] uppercase tracking-[0.15em]">New ayahs per day</label>
                        <input
                            id="daily_new"
                            v-model.number="dailyNew"
                            type="number" inputmode="numeric" min="1" max="50"
                            @change="saveDailyNew"
                            class="w-16 min-h-[36px] text-center bg-[var(--bg-color)] border border-[var(--border-color)] text-[var(--main-color)] focus:border-[var(--caret-color)] focus:outline-none"
                        />
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
