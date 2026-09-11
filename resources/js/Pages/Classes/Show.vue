<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SurahSelect from '@/Components/SurahSelect.vue';
import { useSettings } from '../../useSettings';

const props = defineProps({
    group: { type: Object, required: true },
    isOwner: { type: Boolean, default: false },
    roster: { type: Array, default: () => [] },
    myProgress: { type: Object, default: null },
    assignments: { type: Array, default: () => [] },
});

const { t } = useSettings();

const surahs = ref([]);
onMounted(async () => {
    if (!props.isOwner) return;
    try {
        const { data } = await axios.get('/api/surahs');
        surahs.value = data;
    } catch {
        // the assignment form still works without the picker pre-filled
    }
});

const assignForm = useForm({
    surah_number: '',
    start_ayah: 1,
    end_ayah: 1,
    due_on: '',
});

watch(() => assignForm.surah_number, (num) => {
    const surah = surahs.value.find((s) => s.surah_number == num);
    if (surah) {
        assignForm.start_ayah = 1;
        assignForm.end_ayah = surah.total_ayahs;
    }
});

const createAssignment = () => {
    assignForm.post(`/classes/${props.group.id}/assignments`, {
        preserveScroll: true,
        onSuccess: () => assignForm.reset(),
    });
};

const deleteAssignment = (assignmentId) => {
    if (confirm(t('classes.delete_assignment_confirm'))) {
        router.delete(`/classes/${props.group.id}/assignments/${assignmentId}`, { preserveScroll: true });
    }
};

const copied = ref(false);
const copyJoinLink = async () => {
    try {
        await navigator.clipboard.writeText(props.group.join_url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // clipboard blocked — the input is selectable as a fallback
    }
};

const confirmDelete = () => {
    if (confirm(t('classes.delete_confirm'))) {
        router.delete(`/classes/${props.group.id}`);
    }
};

const fmtDay = (v) => (v ? new Date(v).toLocaleDateString() : '—');
const num = (v) => (v ?? 0).toLocaleString();
</script>

<template>
    <Head><title>{{ props.group.name }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-4xl mx-auto px-4 sm:px-6">
                <header class="mb-8 flex items-start justify-between gap-4">
                    <div>
                        <Link href="/classes" class="inline-flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] hover:text-[var(--caret-color)] transition-colors mb-2">
                            ← {{ t('classes.title') }}
                        </Link>
                        <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ props.group.name }}</h1>
                    </div>
                    <button v-if="isOwner" type="button" @click="confirmDelete"
                            class="shrink-0 border border-[var(--error-color)] text-[var(--error-color)] px-4 py-2 font-mono text-[10px] uppercase tracking-[0.2em] hover:bg-[var(--error-color)]/10 transition-colors">
                        {{ t('classes.delete') }}
                    </button>
                </header>

                <!-- Assignments -->
                <section class="mb-10">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('classes.assignments_title') }}</h2>

                    <form v-if="isOwner" @submit.prevent="createAssignment" class="flex flex-wrap items-end gap-2 mb-4">
                        <SurahSelect
                            class="w-full sm:w-auto"
                            :model-value="assignForm.surah_number"
                            :options="surahs"
                            :label="t('surah')"
                            @update:model-value="v => assignForm.surah_number = v"
                        />
                        <div class="flex items-center gap-1">
                            <input v-model.number="assignForm.start_ayah" type="number" min="1" aria-label="Start ayah"
                                   class="w-16 text-center font-mono text-sm bg-transparent border border-[var(--border-color)] px-2 py-2 focus:outline-none focus:border-[var(--caret-color)]" />
                            <span class="text-[var(--sub-color)] opacity-40 px-1">–</span>
                            <input v-model.number="assignForm.end_ayah" type="number" min="1" aria-label="End ayah"
                                   class="w-16 text-center font-mono text-sm bg-transparent border border-[var(--border-color)] px-2 py-2 focus:outline-none focus:border-[var(--caret-color)]" />
                        </div>
                        <input v-model="assignForm.due_on" type="date" :aria-label="t('classes.due_date')"
                               class="font-mono text-sm bg-transparent border border-[var(--border-color)] px-3 py-2 text-[var(--main-color)] focus:outline-none focus:border-[var(--caret-color)]" />
                        <button type="submit" :disabled="assignForm.processing || !assignForm.surah_number"
                                class="border border-[var(--caret-color)] text-[var(--caret-color)] px-4 py-2 font-mono text-[10px] uppercase tracking-[0.2em] hover:bg-[var(--caret-color)]/10 transition-colors disabled:opacity-40">
                            {{ t('classes.assign_button') }}
                        </button>
                    </form>

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
                            <div class="flex items-center gap-3 shrink-0">
                                <template v-if="isOwner">
                                    <span class="text-[var(--sub-color)] text-xs tabular-nums">{{ a.completed_count }}/{{ a.members_count }} {{ t('classes.completed') }}</span>
                                    <button type="button" @click="deleteAssignment(a.id)"
                                            class="text-[var(--error-color)] hover:opacity-80 transition-opacity text-xs uppercase tracking-[0.1em]">
                                        {{ t('classes.remove') }}
                                    </button>
                                </template>
                                <template v-else>
                                    <span :class="a.completed ? 'text-[var(--caret-color)]' : 'text-[var(--sub-color)]'" class="text-xs uppercase tracking-[0.1em]">
                                        {{ a.completed ? t('classes.completed') : t('classes.not_completed') }}
                                    </span>
                                    <Link v-if="!a.completed" :href="a.start_url"
                                          class="text-[var(--lapis-color)] hover:opacity-80 transition-opacity underline underline-offset-4 text-xs">
                                        {{ t('classes.start') }}
                                    </Link>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Teacher: join link -->
                <section v-if="isOwner" class="mb-10">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('classes.join_link') }}</h2>
                    <div class="flex gap-2">
                        <input
                            :value="props.group.join_url"
                            type="text"
                            readonly
                            onclick="this.select()"
                            class="flex-1 min-w-0 bg-transparent border border-[var(--border-color)] px-4 py-2.5 font-mono text-xs text-[var(--sub-color)] focus:outline-none"
                        />
                        <button type="button" @click="copyJoinLink"
                                class="shrink-0 border border-[var(--caret-color)] text-[var(--caret-color)] px-4 font-mono text-[10px] uppercase tracking-[0.2em] hover:bg-[var(--caret-color)]/10 transition-colors">
                            {{ copied ? t('classes.copied') : t('classes.copy') }}
                        </button>
                    </div>
                    <p class="mt-2 font-mono text-[10px] text-[var(--sub-color)] opacity-70">{{ t('classes.join_hint') }}</p>
                </section>

                <!-- Teacher: roster -->
                <section v-if="isOwner">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('classes.roster') }}</h2>
                    <p v-if="!roster.length" class="font-mono text-xs text-[var(--sub-color)] opacity-70">{{ t('classes.no_students') }}</p>
                    <div v-else class="border border-[var(--border-color)] overflow-x-auto">
                        <table class="w-full font-mono text-xs">
                            <thead>
                                <tr class="border-b border-[var(--border-color)] text-[var(--sub-color)] uppercase tracking-[0.1em]">
                                    <th class="px-4 py-3 text-left">{{ t('classes.student') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('classes.joined_on') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('classes.tests') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('classes.avg_wpm') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('classes.avg_accuracy') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('classes.streak') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('classes.hifz_due') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('classes.last_practiced') }}</th>
                                    <th class="px-4 py-3 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <tr v-for="s in roster" :key="s.id">
                                    <td class="px-4 py-3 text-[var(--main-color)]">{{ s.name }}</td>
                                    <td class="px-4 py-3 text-right text-[var(--sub-color)]">{{ fmtDay(s.joined_at) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-[var(--sub-color)]">{{ num(s.tests_count) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-[var(--sub-color)]">{{ num(s.avg_wpm) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-[var(--sub-color)]">{{ s.avg_accuracy }}%</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-[var(--sub-color)]">{{ num(s.streak) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-[var(--sub-color)]">{{ num(s.hifz_due) }}</td>
                                    <td class="px-4 py-3 text-right text-[var(--sub-color)]">{{ fmtDay(s.last_practiced_on) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <Link :href="`/classes/${group.id}/students/${s.id}`"
                                              class="text-[var(--lapis-color)] hover:opacity-80 transition-opacity underline underline-offset-4">
                                            {{ t('classes.details') }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Student: my own progress -->
                <section v-else-if="myProgress">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('classes.my_progress') }}</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 font-mono text-sm">
                        <div class="border border-[var(--border-color)] p-4">
                            <p class="text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] mb-1">{{ t('classes.tests') }}</p>
                            <p class="text-xl text-[var(--main-color)] tabular-nums">{{ num(myProgress.tests_count) }}</p>
                        </div>
                        <div class="border border-[var(--border-color)] p-4">
                            <p class="text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] mb-1">{{ t('classes.avg_wpm') }}</p>
                            <p class="text-xl text-[var(--main-color)] tabular-nums">{{ num(myProgress.avg_wpm) }}</p>
                        </div>
                        <div class="border border-[var(--border-color)] p-4">
                            <p class="text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] mb-1">{{ t('classes.avg_accuracy') }}</p>
                            <p class="text-xl text-[var(--main-color)] tabular-nums">{{ myProgress.avg_accuracy }}%</p>
                        </div>
                        <div class="border border-[var(--border-color)] p-4">
                            <p class="text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] mb-1">{{ t('classes.streak') }}</p>
                            <p class="text-xl text-[var(--main-color)] tabular-nums">{{ num(myProgress.streak) }}</p>
                        </div>
                        <div class="border border-[var(--border-color)] p-4">
                            <p class="text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] mb-1">{{ t('classes.hifz_due') }}</p>
                            <p class="text-xl text-[var(--main-color)] tabular-nums">{{ num(myProgress.hifz_due) }}</p>
                        </div>
                        <div class="border border-[var(--border-color)] p-4">
                            <p class="text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] mb-1">{{ t('classes.last_practiced') }}</p>
                            <p class="text-xl text-[var(--main-color)]">{{ fmtDay(myProgress.last_practiced_on) }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
