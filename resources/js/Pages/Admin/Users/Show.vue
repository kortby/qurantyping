<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    user: Object,
    stats: Object,
    progress: Object,
    badges: Array,
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

const deleteUser = () => {
    router.delete(`/admin/users/${props.user.id}`);
};

const impersonate = () => {
    router.post(`/admin/users/${props.user.id}/impersonate`);
};

const resendVerification = () => {
    router.post(`/admin/users/${props.user.id}/resend-verification`, {}, { preserveScroll: true });
};

const revokeSessions = () => {
    router.delete(`/admin/users/${props.user.id}/sessions`, { preserveScroll: true });
};

const revokeToken = (id) => {
    router.delete(`/admin/users/${props.user.id}/tokens/${id}`, { preserveScroll: true });
};

const formatDate = (value) => value ? new Date(value).toLocaleString() : '—';
</script>

<template>
    <Head>
        <title>{{ user.name }} - Admin | QuranTyping</title>
    </Head>

    <AppLayout>
        <div class="py-8 animate-fade-in min-h-[80vh]">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <Link href="/admin/users" class="inline-flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] hover:text-[var(--caret-color)] transition-colors">
                    ← Back to users
                </Link>

                <div v-if="page.props.flash.message" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 font-mono text-xs text-emerald-500">
                    {{ page.props.flash.message }}
                </div>

                <!-- Header -->
                <div class="bg-[var(--panel-color)] border border-[var(--border-color)] p-6 flex flex-col sm:flex-row sm:items-center gap-6">
                    <img :src="user.profile_photo_url" :alt="user.name" class="w-20 h-20 rounded-2xl object-cover border border-[var(--border-color)]" />
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-cinzel font-bold text-[var(--caret-color)]">{{ user.name }}</h1>
                            <span v-if="user.is_super_admin" class="text-[8px] uppercase tracking-widest px-1.5 py-0.5 rounded bg-[var(--caret-color)]/10 text-[var(--caret-color)] border border-[var(--caret-color)]/20">admin</span>
                        </div>
                        <p class="font-mono text-sm opacity-70 mt-1">{{ user.email }}</p>
                        <p class="font-mono text-[10px] uppercase tracking-widest opacity-40 mt-2">
                            <span :class="user.email_verified_at ? 'text-emerald-500' : 'text-[var(--error-color)]'">
                                {{ user.email_verified_at ? 'Verified' : 'Unverified' }}
                            </span>
                            · Joined {{ formatDate(user.created_at) }}
                            · Last login {{ user.last_login_at ? formatDate(user.last_login_at) : 'never' }}
                            <span v-if="user.oauth_provider"> · via {{ user.oauth_provider }}</span>
                        </p>
                    </div>
                    <div v-if="!user.is_self && !user.is_super_admin" class="flex flex-col gap-2">
                        <PrimaryButton type="button" @click="impersonate">Impersonate</PrimaryButton>
                        <DangerButton type="button" @click="confirmingDeletion = true">Delete user</DangerButton>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-[var(--panel-color)] rounded-2xl border border-[var(--border-color)] p-5 text-center">
                        <p class="text-3xl font-cinzel font-bold text-[var(--caret-color)]">{{ stats.tests_count }}</p>
                        <p class="text-[9px] uppercase tracking-widest opacity-40 mt-1">Tests</p>
                    </div>
                    <div class="bg-[var(--panel-color)] rounded-2xl border border-[var(--border-color)] p-5 text-center">
                        <p class="text-3xl font-cinzel font-bold text-[var(--caret-color)]">{{ stats.best_wpm }}</p>
                        <p class="text-[9px] uppercase tracking-widest opacity-40 mt-1">Best WPM</p>
                    </div>
                    <div class="bg-[var(--panel-color)] rounded-2xl border border-[var(--border-color)] p-5 text-center">
                        <p class="text-3xl font-cinzel font-bold text-[var(--caret-color)]">{{ stats.avg_wpm }}</p>
                        <p class="text-[9px] uppercase tracking-widest opacity-40 mt-1">Avg WPM</p>
                    </div>
                </div>

                <!-- Practice & memorisation -->
                <div v-if="progress" class="border border-[var(--border-color)] p-6 space-y-5">
                    <h2 class="font-cinzel text-sm uppercase tracking-[0.3em] text-[var(--caret-color)]">Practice &amp; memorisation</h2>

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
                        · {{ progress.hifz.daily_new }} new/day
                        · auto-advance {{ progress.hifz.auto_advance ? 'on' : 'off' }}
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

                <!-- Edit -->
                <form @submit.prevent="submit" class="bg-[var(--panel-color)] border border-[var(--border-color)] p-6 space-y-4">
                    <h2 class="font-cinzel text-sm uppercase tracking-[0.3em] text-[var(--caret-color)]">Edit account</h2>
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
                    <label v-if="!user.email_verified_at" class="flex items-center gap-2 font-mono text-xs opacity-80">
                        <input v-model="form.mark_verified" type="checkbox" class="rounded border-[var(--border-color)] bg-[var(--bg-color)] text-[var(--caret-color)]" />
                        Mark email as verified
                    </label>
                    <div class="flex items-center gap-3">
                        <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">Save changes</PrimaryButton>
                        <SecondaryButton v-if="!user.email_verified_at" type="button" @click="resendVerification">Resend verification email</SecondaryButton>
                        <span v-if="form.recentlySuccessful" class="font-mono text-xs text-emerald-500">Saved.</span>
                    </div>
                </form>

                <!-- Badges -->
                <div class="bg-[var(--panel-color)] border border-[var(--border-color)] p-6">
                    <h2 class="font-cinzel text-sm uppercase tracking-[0.3em] text-[var(--caret-color)] mb-4">Badges</h2>
                    <div v-if="badges.length" class="flex flex-wrap gap-2">
                        <span v-for="badge in badges" :key="badge.id" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 font-mono text-xs">
                            <span>{{ badge.icon }}</span> {{ badge.name }}
                        </span>
                    </div>
                    <p v-else class="font-mono text-xs opacity-40">No badges.</p>
                </div>

                <!-- Recent tests -->
                <div class="bg-[var(--panel-color)] border border-[var(--border-color)] overflow-hidden">
                    <h2 class="font-cinzel text-sm uppercase tracking-[0.3em] text-[var(--caret-color)] p-6 pb-3">Recent tests</h2>
                    <table v-if="recentTests.length" class="w-full text-left font-mono text-sm">
                        <thead>
                            <tr class="bg-[var(--caret-color)]/5 text-[var(--sub-color)] uppercase tracking-[0.2em] text-[10px]">
                                <th class="px-6 py-3 font-bold">WPM</th>
                                <th class="px-6 py-3 font-bold text-center">Accuracy</th>
                                <th class="px-6 py-3 font-bold text-center">Chars</th>
                                <th class="px-6 py-3 font-bold text-center">Errors</th>
                                <th class="px-6 py-3 font-bold text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border-color)]">
                            <tr v-for="test in recentTests" :key="test.id">
                                <td class="px-6 py-3 font-bold text-[var(--caret-color)]">{{ test.wpm }}</td>
                                <td class="px-6 py-3 text-center">{{ Math.round(test.accuracy) }}%</td>
                                <td class="px-6 py-3 text-center opacity-70">{{ test.char_count }}</td>
                                <td class="px-6 py-3 text-center text-[var(--error-color)]">{{ test.total_errors }}</td>
                                <td class="px-6 py-3 text-right opacity-50">{{ formatDate(test.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="font-mono text-xs opacity-40 px-6 pb-6">No tests completed.</p>
                </div>

                <!-- Browser sessions -->
                <div class="bg-[var(--panel-color)] border border-[var(--border-color)] p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-cinzel text-sm uppercase tracking-[0.3em] text-[var(--caret-color)]">Browser sessions</h2>
                        <DangerButton v-if="sessions.length" type="button" @click="revokeSessions">Revoke all</DangerButton>
                    </div>
                    <ul v-if="sessions.length" class="space-y-2 font-mono text-xs">
                        <li v-for="session in sessions" :key="session.id" class="flex items-center justify-between gap-4 border-b border-[var(--border-color)] pb-2 last:border-0">
                            <span class="opacity-70 truncate">{{ session.user_agent || 'Unknown device' }}</span>
                            <span class="opacity-40 whitespace-nowrap">{{ session.ip_address }} · {{ session.last_active }}</span>
                        </li>
                    </ul>
                    <p v-else class="font-mono text-xs opacity-40">No active sessions.</p>
                </div>

                <!-- API tokens -->
                <div class="bg-[var(--panel-color)] border border-[var(--border-color)] p-6">
                    <h2 class="font-cinzel text-sm uppercase tracking-[0.3em] text-[var(--caret-color)] mb-4">API tokens</h2>
                    <ul v-if="tokens.length" class="space-y-2 font-mono text-xs">
                        <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between gap-4 border-b border-[var(--border-color)] pb-2 last:border-0">
                            <span class="opacity-80">{{ token.name }}</span>
                            <span class="flex items-center gap-3">
                                <span class="opacity-40">Last used {{ token.last_used_at ? formatDate(token.last_used_at) : 'never' }}</span>
                                <button type="button" @click="revokeToken(token.id)" class="text-[var(--error-color)] hover:underline">Revoke</button>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="font-mono text-xs opacity-40">No API tokens.</p>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>Delete {{ user.name }}</template>
            <template #content>
                This permanently deletes the user and all of their typing tests. This cannot be undone.
            </template>
            <template #footer>
                <SecondaryButton @click="confirmingDeletion = false">Cancel</SecondaryButton>
                <DangerButton class="ms-3" @click="deleteUser">Delete user</DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
