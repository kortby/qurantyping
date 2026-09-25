<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
    other: Object,
    friends: Boolean,
    total: Number,
    messages: Array,
    has_more: Boolean,
    before: Number,
});

const people = computed(() => ({ [props.user.id]: props.user, [props.other.id]: props.other }));

const rows = computed(() => props.messages.map((m, i) => {
    const prev = props.messages[i - 1];
    const day = new Date(m.created_at).toDateString();
    return {
        ...m,
        fromUser: m.sender_id === props.user.id,
        showDay: !prev || new Date(prev.created_at).toDateString() !== day,
        showName: !prev || prev.sender_id !== m.sender_id || new Date(prev.created_at).toDateString() !== day,
    };
}));

const fmtDay = (v) => new Date(v).toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
const fmtTime = (v) => new Date(v).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Head><title>{{ user.name }} ↔ {{ other.name }} - Admin | QuranTyping</title></Head>

    <AppLayout>
        <div class="py-8 animate-fade-in min-h-[80vh]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <Link href="/admin/chats" class="inline-flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] hover:text-[var(--caret-color)] transition-colors">
                    ← All chats
                </Link>

                <div class="border border-[var(--border-color)] bg-[var(--panel-color)] p-6 flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <img :src="user.profile_photo_url" :alt="user.name" class="w-10 h-10 rounded-full object-cover border border-[var(--border-color)]" />
                        <Link :href="`/admin/users/${user.id}`" class="min-w-0">
                            <span class="block text-[var(--main-color)] truncate hover:text-[var(--caret-color)] transition-colors">{{ user.name }}</span>
                            <span class="block font-mono text-[10px] text-[var(--sub-color)] truncate">{{ user.email }}</span>
                        </Link>
                    </div>
                    <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-center" :class="friends ? 'text-[var(--caret-color)]' : 'text-[var(--error-color)]'">
                        {{ friends ? 'Friends' : 'No longer friends' }}<br />
                        <span class="text-[var(--sub-color)]">{{ total }} messages</span>
                    </span>
                    <div class="flex items-center gap-3 flex-1 min-w-0 sm:justify-end sm:text-right">
                        <Link :href="`/admin/users/${other.id}`" class="min-w-0 sm:order-1">
                            <span class="block text-[var(--main-color)] truncate hover:text-[var(--caret-color)] transition-colors">{{ other.name }}</span>
                            <span class="block font-mono text-[10px] text-[var(--sub-color)] truncate">{{ other.email }}</span>
                        </Link>
                        <img :src="other.profile_photo_url" :alt="other.name" class="w-10 h-10 rounded-full object-cover border border-[var(--border-color)] sm:order-2" />
                    </div>
                </div>

                <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)]">
                    Read-only · viewing does not mark messages as read
                </p>

                <div class="border border-[var(--border-color)] bg-[var(--panel-color)] px-4 sm:px-6 py-4">
                    <div class="flex justify-between font-mono text-[10px] uppercase tracking-[0.2em] mb-2">
                        <Link v-if="has_more" :href="`/admin/chats/${user.id}/${other.id}?before=${messages[0].id}`" preserve-scroll
                              class="text-[var(--lapis-color)] hover:opacity-70">← Older</Link>
                        <span v-else></span>
                        <Link v-if="before" :href="`/admin/chats/${user.id}/${other.id}`" class="text-[var(--lapis-color)] hover:opacity-70">Latest →</Link>
                    </div>

                    <p v-if="!messages.length" class="py-12 text-center font-mono text-sm text-[var(--sub-color)]">No messages between these users.</p>

                    <template v-for="m in rows" :key="m.id">
                        <div v-if="m.showDay" class="flex items-center gap-3 my-4">
                            <span class="flex-1 border-t border-[var(--border-color)]"></span>
                            <span class="font-mono text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)]">{{ fmtDay(m.created_at) }}</span>
                            <span class="flex-1 border-t border-[var(--border-color)]"></span>
                        </div>

                        <div class="flex flex-col" :class="[m.fromUser ? 'items-start' : 'items-end', m.showName ? 'mt-3' : 'mt-1']">
                            <span v-if="m.showName" class="font-mono text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)] mb-1">{{ people[m.sender_id]?.name }}</span>
                            <p class="max-w-[80%] px-3 py-1.5 text-sm leading-snug whitespace-pre-wrap break-words [unicode-bidi:plaintext] rounded-2xl"
                               :class="m.fromUser
                                   ? 'bg-[var(--bg-color)] text-[var(--main-color)] border border-[var(--border-color)] rounded-es-md'
                                   : 'bg-[var(--lapis-color)] text-white rounded-ee-md'">{{ m.body }}</p>
                            <span class="font-mono text-[9px] text-[var(--sub-color)] mt-0.5">
                                {{ fmtTime(m.created_at) }} · {{ m.read_at ? `seen ${fmtTime(m.read_at)}` : 'unread' }}
                            </span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
