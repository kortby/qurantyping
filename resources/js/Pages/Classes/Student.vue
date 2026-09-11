<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

const props = defineProps({
    group: { type: Object, required: true },
    student: { type: Object, required: true },
    progress: { type: Object, required: true },
    assignments: { type: Array, default: () => [] },
});

const { t } = useSettings();

const fmtDay = (v) => (v ? new Date(v).toLocaleDateString() : '—');
const num = (v) => (v ?? 0).toLocaleString();

const CARD = 'border border-[var(--border-color)] p-4';
const LABEL = 'text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] mb-1';
const VALUE = 'text-xl text-[var(--main-color)] tabular-nums';
</script>

<template>
    <Head><title>{{ props.student.name }} - {{ props.group.name }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-4xl mx-auto px-4 sm:px-6">
                <header class="mb-8">
                    <Link :href="`/classes/${props.group.id}`" class="inline-flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] hover:text-[var(--caret-color)] transition-colors mb-2">
                        ← {{ t('classes.back_to_class') }}
                    </Link>
                    <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ props.student.name }}</h1>
                    <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)] mt-1">
                        {{ props.group.name }} · {{ t('classes.joined_on') }} {{ fmtDay(props.student.joined_at) }}
                    </p>
                </header>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 font-mono text-sm mb-10">
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.tests') }}</p>
                        <p :class="VALUE">{{ num(progress.tests_count) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.best_wpm') }}</p>
                        <p :class="VALUE">{{ num(progress.best_wpm) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.avg_wpm') }}</p>
                        <p :class="VALUE">{{ num(progress.avg_wpm) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.avg_accuracy') }}</p>
                        <p :class="VALUE">{{ progress.avg_accuracy }}%</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.total_chars') }}</p>
                        <p :class="VALUE">{{ num(progress.total_chars) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.total_errors') }}</p>
                        <p :class="VALUE">{{ num(progress.total_errors) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.streak') }}</p>
                        <p :class="VALUE">{{ num(progress.streak.current) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.longest_streak') }}</p>
                        <p :class="VALUE">{{ num(progress.streak.longest) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.hifz_learning') }}</p>
                        <p :class="VALUE">{{ num(progress.hifz.learning) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.hifz_review') }}</p>
                        <p :class="VALUE">{{ num(progress.hifz.review) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.hifz_due') }}</p>
                        <p :class="VALUE">{{ num(progress.hifz.due) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.hifz_total') }}</p>
                        <p :class="VALUE">{{ num(progress.hifz.total) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.first_test_at') }}</p>
                        <p class="text-xl text-[var(--main-color)]">{{ fmtDay(progress.first_test_at) }}</p>
                    </div>
                    <div :class="CARD">
                        <p :class="LABEL">{{ t('classes.last_practiced') }}</p>
                        <p class="text-xl text-[var(--main-color)]">{{ fmtDay(progress.last_practiced_on) }}</p>
                    </div>
                </div>

                <section class="mb-10">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('classes.assignments_title') }}</h2>
                    <p v-if="!assignments.length" class="font-mono text-xs text-[var(--sub-color)] opacity-70">{{ t('classes.no_assignments') }}</p>
                    <div v-else class="border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                        <div v-for="a in assignments" :key="a.id" class="flex items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p class="text-[var(--main-color)]">
                                    {{ a.surah_name }} <span class="text-[var(--sub-color)]">{{ a.start_ayah }}–{{ a.end_ayah }}</span>
                                </p>
                                <p v-if="a.due_on" class="text-[10px] uppercase tracking-[0.1em] text-[var(--sub-color)] opacity-70 mt-0.5">
                                    {{ t('classes.due_date') }}: {{ fmtDay(a.due_on) }}
                                </p>
                            </div>
                            <span :class="a.completed ? 'text-[var(--caret-color)]' : 'text-[var(--sub-color)]'" class="text-xs uppercase tracking-[0.1em] shrink-0">
                                {{ a.completed ? t('classes.completed') : t('classes.not_completed') }}
                            </span>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('classes.recent_tests') }}</h2>
                    <p v-if="!progress.recent_tests.length" class="font-mono text-xs text-[var(--sub-color)] opacity-70">{{ t('classes.no_tests') }}</p>
                    <div v-else class="border border-[var(--border-color)] overflow-x-auto">
                        <table class="w-full font-mono text-xs">
                            <thead>
                                <tr class="border-b border-[var(--border-color)] text-[var(--sub-color)] uppercase tracking-[0.1em]">
                                    <th class="px-4 py-3 text-left">{{ t('classes.mode') }}</th>
                                    <th class="px-4 py-3 text-left">{{ t('classes.passage') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('classes.avg_wpm') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('classes.avg_accuracy') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('classes.date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <tr v-for="t2 in progress.recent_tests" :key="t2.id">
                                    <td class="px-4 py-3 text-[var(--main-color)] uppercase">{{ t2.mode }}</td>
                                    <td class="px-4 py-3 text-[var(--sub-color)]">{{ t2.range ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-[var(--sub-color)]">{{ num(t2.wpm) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-[var(--sub-color)]">{{ t2.accuracy }}%</td>
                                    <td class="px-4 py-3 text-right text-[var(--sub-color)]">{{ fmtDay(t2.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
