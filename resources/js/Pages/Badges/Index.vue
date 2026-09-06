<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BadgeSeal from '@/Components/BadgeSeal.vue';
import { useSettings } from '../../useSettings';

const props = defineProps({
    badges: { type: Array, default: () => [] },
});

const { t } = useSettings();

const earnedCount = computed(() => props.badges.filter((b) => b.earned_at).length);
const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString() : '');
</script>

<template>
    <Head><title>{{ t('badges.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-4xl mx-auto px-4 sm:px-6">
                <header class="mb-8 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
                    <div>
                        <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('badges.title') }}</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">{{ t('badges.subtitle') }}</p>
                    </div>
                    <span class="font-mono text-sm text-[var(--sub-color)] tabular-nums">
                        {{ t('badges.earned_of').replace('{n}', earnedCount).replace('{total}', badges.length) }}
                    </span>
                </header>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="b in badges"
                        :key="b.slug"
                        class="border p-4 flex items-start gap-4"
                        :class="b.earned_at ? 'border-[var(--caret-color)]/40' : 'border-[var(--border-color)]'"
                    >
                        <BadgeSeal :icon="b.icon" :tier="b.tier" :earned="!!b.earned_at" :size="52" />
                        <div class="min-w-0">
                            <p class="font-cinzel text-sm" :class="b.earned_at ? 'text-[var(--main-color)]' : 'text-[var(--sub-color)]'">{{ b.name }}</p>
                            <p class="text-[var(--sub-color)] text-xs mt-0.5 leading-snug">{{ b.description }}</p>
                            <p v-if="b.earned_at" class="font-mono text-[10px] text-[var(--caret-color)] uppercase tracking-[0.15em] mt-1.5">
                                {{ t('badges.awarded_on').replace('{date}', fmtDate(b.earned_at)) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
