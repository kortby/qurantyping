<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import BadgeSeal from '@/Components/BadgeSeal.vue';

const props = defineProps({
    user: Object,
    account: Object,
    stats: Object,
    quran: Object,
    races: Object,
    weakLetters: Array,
    activity: Array,
    feedback: Array,
    progress: Object,
    badges: Array,
    badgeTotal: Number,
    recentTests: Array,
    sessions: Array,
    tokens: Array,
});

const page = usePage();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    mark_verified: false,
});

const submit = () => form.put(`/admin/users/${props.user.id}`, { preserveScroll: true });

const confirmingDeletion = ref(false);
const deleteUser = () => router.delete(`/admin/users/${props.user.id}`);
const impersonate = () => router.post(`/admin/users/${props.user.id}/impersonate`);
const resendVerification = () => router.post(`/admin/users/${props.user.id}/resend-verification`, {}, { preserveScroll: true });
const revokeSessions = () => router.delete(`/admin/users/${props.user.id}/sessions`, { preserveScroll: true });
const revokeToken = (id) => router.delete(`/admin/users/${props.user.id}/tokens/${id}`, { preserveScroll: true });

const fmt = (v) => (v ? new Date(v).toLocaleString() : '—');
const fmtDay = (v) => (v ? new Date(v).toLocaleDateString() : '—');
const num = (v) => (v ?? 0).toLocaleString();

const maxChars = computed(() => Math.max(1, ...props.activity.map((d) => d.chars)));

const H2 = 'font-cinzel text-sm uppercase tracking-[0.3em] text-[var(--caret-color)]';
const CARD = 'border border-[var(--border-color)] bg-[var(--panel-color)] p-6';
</script>

<template>
    <Head><title>{{ user.name }} - Admin | QuranTyping</title></Head>

    <AppLayout>
        <div class="py-8 animate-fade-in min-h-[80vh]">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <Link href="/admin/users" class="inline-flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] hover:text-[var(--caret-color)] transition-colors">
                    ← Back to users
                </Link>

                <div v-if="page.props.flash.message" class="border border-[var(--caret-color)]/40 bg-[var(--caret-color)]/10 px-4 py-3 font-mono text-xs text-[var(--caret-color)]">
                    {{ page.props.flash.message }}
                </div>

                <!-- Header -->
                <div :class="[CARD, 'flex flex-col sm:flex-row sm:items-center gap-6']">
                    <img :src="user.profile_photo_url" :alt="user.name" class="w-20 h-20 object-cover border border-[var(--border-color)]" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)] truncate">{{ user.name }}</h1>
                            <span v-if="user.is_super_admin" class="text-[8px] uppercase tracking-widest px-1.5 py-0.5 border border-[var(--caret-color)]/30 text-[var(--caret-color)]">admin</span>
                        </div>
                        <p class="font-mono text-sm text-[var(--sub-color)] mt-1 truncate">{{ user.email }} · #{{ user.id }}</p>
                        <p class="font-mono text-[10px] uppercase tracking-widest text-[var(--sub-color)] mt-2 flex flex-wrap gap-x-2 gap-y-0.5">
                            <span :class="user.email_verified_at ? 'text-[var(--caret-color)]' : 'text-[var(--error-color)]'">{{ user.email_verified_at ? 'Verified' : 'Unverified' }}</span>
                            <span>· Joined {{ fmtDay(user.created_at) }}</span>
                            <span>· Last login {{ user.last_login_at ? fmt(user.last_login_at) : 'never' }}</span>
                            <span v-if="user.oauth_provider">· via {{ user.oauth_provider }}</span>
                            <span v-if="account.two_factor" class="text-[var(--caret-color)]">· 2FA</span>
                            <span>· Reciter {{ account.reciter }}</span>
                        </p>
                    </div>
                    <div v-if="!user.is_self && !user.is_super_admin" class="flex flex-col gap-2">
                        <PrimaryButton type="button" @click="impersonate">Impersonate</PrimaryButton>
                        <DangerButton type="button" @click="confirmingDeletion = true">Delete user</DangerButton>
                    </div>
                </div>

                <!-- Top stats -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div v-for="s in [
                        { k: 'Tests', v: stats.tests_count },
                        { k: 'Best WPM', v: stats.best_wpm },
                        { k: 'Avg WPM', v: stats.avg_wpm },
                        { k: 'Hours', v: stats.hours_practiced },
                    ]" :key="s.k" class="border border-[var(--border-color)] bg-[var(--panel-color)] p-5 text-center">
                        <p class="text-3xl font-cinzel font-semibold text-[var(--caret-color)] tabular-nums">{{ s.v }}</p>
                        <p class="text-[9px] uppercase tracking-widest text-[var(--sub-color)] mt-1">{{ s.k }}</p>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-6">
                    <!-- Qur'an coverage -->
                    <div :class="CARD">
                        <h2 :class="H2">Qur'an coverage</h2>
                        <div class="h-2 flex mt-4 mb-2 overflow-hidden">
                            <div class="bg-[#1e6b47]" :style="{ width: quran.memorised_pct + '%' }"></div>
                            <div class="bg-[#3f9d6b]" :style="{ width: Math.max(0, quran.mastered_pct - quran.memorised_pct) + '%' }"></div>
                            <div class="bg-[#7fbf9c]/60" :style="{ width: Math.max(0, quran.practiced_pct - quran.mastered_pct) + '%' }"></div>
                            <div class="bg-[var(--border-color)] flex-1"></div>
                        </div>
                        <p class="font-mono text-[11px] text-[var(--sub-color)]">
                            {{ quran.practiced_pct }}% practised · {{ quran.mastered_pct }}% mastered · {{ quran.memorised }} memorised
                            <span class="block">{{ num(quran.practiced) }} / {{ num(quran.ayah_count) }} ayahs</span>
                        </p>
                    </div>

                    <!-- Races -->
                    <div :class="CARD">
                        <h2 :class="H2">Races</h2>
                        <div class="grid grid-cols-3 gap-3 mt-4 font-mono text-sm text-center">
                            <div><p class="text-xl text-[var(--main-color)] tabular-nums">{{ races.finished }}</p><p class="text-[9px] uppercase tracking-widest text-[var(--sub-color)]">Finished</p></div>
                            <div><p class="text-xl text-[var(--caret-color)] tabular-nums">{{ races.wins }}</p><p class="text-[9px] uppercase tracking-widest text-[var(--sub-color)]">Wins</p></div>
                            <div><p class="text-xl text-[var(--main-color)] tabular-nums">{{ races.best_wpm }}</p><p class="text-[9px] uppercase tracking-widest text-[var(--sub-color)]">Best WPM</p></div>
                        </div>
                        <p class="font-mono text-[11px] text-[var(--sub-color)] mt-3">
                            {{ races.podiums }} podiums · last {{ races.last_at ? fmtDay(races.last_at) : '—' }}
                        </p>
                    </div>
                </div>

                <!-- Practice & memorisation -->
                <div v-if="progress" :class="[CARD, 'space-y-5']">
                    <h2 :class="H2">Practice &amp; memorisation</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 font-mono text-sm">
                        <div>
                            <p class="text-[9px] uppercase tracking-[0.2em] text-[var(--sub-color)]">Streak</p>
                            <p class="text-xl text-[var(--main-color)] tabular-nums">{{ progress.streak.current }}<span class="text-[var(--sub-color)] text-xs"> / {{ progress.streak.longest }} best</span></p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase tracking-[0.2em] text-[var(--sub-color)]">Today's goal</p>
                            <p class="text-xl tabular-nums" :class="progress.streak.goal.met ? 'text-[var(--caret-color)]' : 'text-[var(--main-color)]'">{{ progress.streak.goal.chars_today }}<span class="text-[var(--sub-color)] text-xs"> / {{ progress.streak.goal.target }}</span></p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase tracking-[0.2em] text-[var(--sub-color)]">Hifz ayahs</p>
                            <p class="text-xl text-[var(--main-color)] tabular-nums">{{ progress.hifz.total }}<span class="text-[var(--sub-color)] text-xs"> · {{ progress.hifz.review }} mature</span></p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase tracking-[0.2em] text-[var(--sub-color)]">Due now</p>
                            <p class="text-xl tabular-nums" :class="progress.hifz.due > 0 ? 'text-[var(--caret-color)]' : 'text-[var(--main-color)]'">{{ progress.hifz.due }}</p>
                        </div>
                    </div>
                    <p class="font-mono text-[11px] text-[var(--sub-color)]">
                        Last practised {{ progress.streak.practiced_today ? 'today' : (user.last_practiced_on ?? '—') }}
                        · grace used {{ account.streak_grace_used_on ?? 'never' }}
                        · avg accuracy {{ stats.avg_accuracy }}% · {{ num(stats.total_chars) }} chars total · {{ stats.tashkeel_tests }} harakat tests
                    </p>
                    <div v-if="progress.certificates.length">
                        <p class="text-[9px] uppercase tracking-[0.2em] text-[var(--sub-color)] mb-2 font-mono">Certificates ({{ progress.certificates.length }})</p>
                        <ul class="flex flex-wrap gap-2 font-mono text-xs">
                            <li v-for="c in progress.certificates" :key="c.surah_number" class="border border-[var(--border-color)] px-2.5 py-1">
                                {{ c.surah_name_english }} · {{ c.accuracy }}% · {{ c.issued_at }}
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Activity -->
                <div v-if="activity.length" :class="CARD">
                    <h2 :class="H2">Activity — last {{ activity.length }} days</h2>
                    <div class="flex items-end gap-1 h-20 mt-4">
                        <div v-for="d in activity" :key="d.date" class="flex-1 bg-[#3f9d6b] min-h-[2px]"
                             :style="{ height: Math.round((d.chars / maxChars) * 100) + '%' }"
                             :title="`${d.date}: ${d.tests} tests · ${num(d.chars)} chars · ${d.minutes} min`"></div>
                    </div>
                    <p class="font-mono text-[10px] text-[var(--sub-color)] mt-2">{{ stats.active_days }} active days total · first test {{ stats.first_test_at ? fmtDay(stats.first_test_at) : '—' }}</p>
                </div>

                <div class="grid sm:grid-cols-2 gap-6">
                    <!-- Weak letters -->
                    <div :class="CARD">
                        <h2 :class="H2">Weakest characters</h2>
                        <div v-if="weakLetters.length" class="flex flex-wrap gap-2 mt-4">
                            <div v-for="w in weakLetters" :key="w.character" class="flex items-center gap-2 border border-[var(--border-color)] px-2 py-1 font-mono text-xs">
                                <span class="text-lg" style="font-family:'Noto Naskh Arabic',serif" dir="rtl">{{ w.character }}</span>
                                <span class="text-[var(--sub-color)]">{{ w.accuracy }}% · {{ w.attempts }}</span>
                            </div>
                        </div>
                        <p v-else class="font-mono text-xs text-[var(--sub-color)] mt-4">No letter data.</p>
                    </div>

                    <!-- Settings -->
                    <div :class="CARD">
                        <h2 :class="H2">Settings</h2>
                        <dl class="mt-4 font-mono text-xs grid grid-cols-2 gap-y-2 text-[var(--sub-color)]">
                            <dt>Daily goal</dt><dd class="text-[var(--main-color)]">{{ account.daily_goal_chars }} chars</dd>
                            <dt>Reciter</dt><dd class="text-[var(--main-color)]">{{ account.reciter }}</dd>
                            <dt>Error sound</dt><dd class="text-[var(--main-color)]">{{ account.error_sound ? 'on' : 'off' }}</dd>
                            <dt>Auto-advance</dt><dd class="text-[var(--main-color)]">{{ account.auto_advance ? 'on' : 'off' }}</dd>
                            <dt>Hifz new/day</dt><dd class="text-[var(--main-color)]">{{ account.hifz_daily_new }}</dd>
                            <dt>2FA</dt><dd class="text-[var(--main-color)]">{{ account.two_factor ? 'enabled' : 'off' }}</dd>
                        </dl>
                    </div>
                </div>

                <!-- Badges -->
                <div :class="CARD">
                    <h2 :class="H2">Badges — {{ badges.length }} / {{ badgeTotal }}</h2>
                    <div v-if="badges.length" class="flex flex-wrap gap-3 mt-4">
                        <span v-for="b in badges" :key="b.id" class="flex flex-col items-center gap-1 w-16" :title="b.name + (b.awarded_at ? ' · ' + fmtDay(b.awarded_at) : '')">
                            <BadgeSeal :icon="b.icon" tier="gold" :earned="true" :size="40" />
                            <span class="font-mono text-[9px] text-[var(--sub-color)] text-center leading-tight truncate w-full">{{ b.name }}</span>
                        </span>
                    </div>
                    <p v-else class="font-mono text-xs text-[var(--sub-color)] mt-4">No badges.</p>
                </div>

                <!-- Recent tests -->
                <div class="border border-[var(--border-color)] bg-[var(--panel-color)] overflow-hidden">
                    <h2 :class="[H2, 'p-6 pb-3']">Recent tests</h2>
                    <div class="overflow-x-auto">
                        <table v-if="recentTests.length" class="w-full min-w-[560px] text-left font-mono text-sm">
                            <thead>
                                <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.2em] text-[10px]">
                                    <th class="px-6 py-3">WPM</th>
                                    <th class="px-6 py-3 text-center">Acc</th>
                                    <th class="px-6 py-3 text-center">Chars</th>
                                    <th class="px-6 py-3 text-center">Errors</th>
                                    <th class="px-6 py-3 text-center">Mode</th>
                                    <th class="px-6 py-3 text-center">Passage</th>
                                    <th class="px-6 py-3 text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <tr v-for="t in recentTests" :key="t.id">
                                    <td class="px-6 py-3 font-semibold text-[var(--caret-color)]">{{ t.wpm }}</td>
                                    <td class="px-6 py-3 text-center">{{ Math.round(t.accuracy) }}%</td>
                                    <td class="px-6 py-3 text-center text-[var(--sub-color)]">{{ t.char_count }}</td>
                                    <td class="px-6 py-3 text-center text-[var(--error-color)]">{{ t.total_errors }}</td>
                                    <td class="px-6 py-3 text-center text-[var(--sub-color)]">{{ t.mode }}<span v-if="t.tashkeel" class="text-[var(--caret-color)]"> ·ت</span></td>
                                    <td class="px-6 py-3 text-center text-[var(--sub-color)]">{{ t.range ?? '—' }}</td>
                                    <td class="px-6 py-3 text-right text-[var(--sub-color)]">{{ fmt(t.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="font-mono text-xs text-[var(--sub-color)] px-6 pb-6">No tests completed.</p>
                    </div>
                </div>

                <!-- Feedback -->
                <div v-if="feedback.length" :class="CARD">
                    <h2 :class="H2">Feedback submitted</h2>
                    <ul class="mt-4 space-y-2 font-mono text-xs">
                        <li v-for="f in feedback" :key="f.id" class="flex items-start justify-between gap-4 border-b border-[var(--border-color)] pb-2 last:border-0">
                            <Link :href="`/admin/feedback/${f.id}`" class="min-w-0">
                                <span class="text-[var(--sub-color)] uppercase text-[10px] tracking-[0.15em]">{{ f.type }}</span>
                                <span class="block text-[var(--main-color)] truncate">{{ f.excerpt }}</span>
                            </Link>
                            <span class="whitespace-nowrap text-[var(--sub-color)]">
                                <span v-if="f.handled" class="text-[var(--caret-color)]">handled</span>
                                <span v-else>open</span> · {{ fmtDay(f.created_at) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Edit -->
                <form @submit.prevent="submit" :class="[CARD, 'space-y-4']">
                    <h2 :class="H2">Edit account</h2>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Name" />
                            <TextInput v-model="form.name" type="text" class="mt-1" />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Email" />
                            <TextInput v-model="form.email" type="email" class="mt-1" />
                            <InputError :message="form.errors.email" class="mt-1" />
                        </div>
                    </div>
                    <label v-if="!user.email_verified_at" class="flex items-center gap-2 font-mono text-xs text-[var(--sub-color)]">
                        <input v-model="form.mark_verified" type="checkbox" class="border-[var(--border-color)] bg-[var(--bg-color)] text-[var(--caret-color)]" />
                        Mark email as verified
                    </label>
                    <div class="flex items-center gap-3">
                        <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">Save changes</PrimaryButton>
                        <SecondaryButton v-if="!user.email_verified_at" type="button" @click="resendVerification">Resend verification email</SecondaryButton>
                        <span v-if="form.recentlySuccessful" class="font-mono text-xs text-[var(--caret-color)]">Saved.</span>
                    </div>
                </form>

                <!-- Sessions -->
                <div :class="CARD">
                    <div class="flex items-center justify-between mb-4">
                        <h2 :class="H2">Browser sessions</h2>
                        <DangerButton v-if="sessions.length" type="button" @click="revokeSessions">Revoke all</DangerButton>
                    </div>
                    <ul v-if="sessions.length" class="space-y-2 font-mono text-xs">
                        <li v-for="s in sessions" :key="s.id" class="flex items-center justify-between gap-4 border-b border-[var(--border-color)] pb-2 last:border-0">
                            <span class="text-[var(--sub-color)] truncate">{{ s.user_agent || 'Unknown device' }}<span v-if="s.is_current_device" class="text-[var(--caret-color)]"> · this device</span></span>
                            <span class="text-[var(--sub-color)] whitespace-nowrap">{{ s.ip_address }} · {{ s.last_active }}</span>
                        </li>
                    </ul>
                    <p v-else class="font-mono text-xs text-[var(--sub-color)]">No active sessions.</p>
                </div>

                <!-- Tokens -->
                <div :class="CARD">
                    <h2 :class="[H2, 'mb-4']">API tokens</h2>
                    <ul v-if="tokens.length" class="space-y-2 font-mono text-xs">
                        <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between gap-4 border-b border-[var(--border-color)] pb-2 last:border-0">
                            <span class="text-[var(--main-color)]">{{ token.name }}</span>
                            <span class="flex items-center gap-3">
                                <span class="text-[var(--sub-color)]">Last used {{ token.last_used_at ? fmt(token.last_used_at) : 'never' }}</span>
                                <button type="button" @click="revokeToken(token.id)" class="text-[var(--error-color)] hover:underline">Revoke</button>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="font-mono text-xs text-[var(--sub-color)]">No API tokens.</p>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>Delete {{ user.name }}</template>
            <template #content>This permanently deletes the user and all of their typing tests. This cannot be undone.</template>
            <template #footer>
                <SecondaryButton @click="confirmingDeletion = false">Cancel</SecondaryButton>
                <DangerButton class="ms-3" @click="deleteUser">Delete user</DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
