<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { useChat } from '@/composables/useChat';
import { useSettings } from '@/useSettings';

const props = defineProps({
    friendId: { type: Number, required: true },
    minimized: { type: Boolean, default: false },
});

const { t, currentLang } = useSettings();
const chat = useChat();

const draft = ref('');
const scroller = ref(null);
const input = ref(null);

const friend = computed(() => chat.conversation(props.friendId));
const thread = computed(() => chat.state.threads[props.friendId] ?? { messages: [], loaded: false, hasMore: false, loading: false });
const typing = computed(() => !!chat.state.typing[props.friendId]);
const me = computed(() => chat.state.me);

const lastSeenId = computed(() => {
    const seen = thread.value.messages.filter((m) => m.sender_id === me.value && m.read_at);
    return seen.length ? seen[seen.length - 1].id : null;
});

const sameDay = (a, b) => new Date(a).toDateString() === new Date(b).toDateString();

const locale = computed(() => ({ ar: 'ar', fr: 'fr' }[currentLang.value] ?? 'en'));

const timeLabel = (iso) => new Date(iso).toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit' });

const dayLabel = (iso) => {
    const date = new Date(iso);
    const today = new Date();
    const yesterday = new Date(today.getTime() - 86400000);
    if (sameDay(date, today)) return t('chat.today');
    if (sameDay(date, yesterday)) return t('chat.yesterday');
    return date.toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: date.getFullYear() === today.getFullYear() ? undefined : 'numeric' });
};

/** Messages with the grouping flags the bubbles need (day divider, first/last of a run). */
const rows = computed(() => {
    const list = thread.value.messages;
    return list.map((m, i) => {
        const prev = list[i - 1];
        const next = list[i + 1];
        const gap = (a, b) => Math.abs(new Date(a.created_at) - new Date(b.created_at)) > 5 * 60000;
        const joinsPrev = prev && prev.sender_id === m.sender_id && !gap(prev, m) && sameDay(prev.created_at, m.created_at);
        const joinsNext = next && next.sender_id === m.sender_id && !gap(m, next) && sameDay(m.created_at, next.created_at);
        return {
            ...m,
            mine: m.sender_id === me.value,
            showDay: !prev || !sameDay(prev.created_at, m.created_at),
            first: !joinsPrev,
            last: !joinsNext,
        };
    });
});

const isNearBottom = () => {
    const el = scroller.value;
    return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80;
};

const scrollToBottom = async () => {
    await nextTick();
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
};

const loadOlder = async () => {
    const el = scroller.value;
    if (!el || !thread.value.hasMore || thread.value.loading) return;
    const before = el.scrollHeight;
    await chat.loadThread(props.friendId, true);
    await nextTick();
    el.scrollTop = el.scrollHeight - before;
};

const onScroll = () => {
    if (scroller.value?.scrollTop < 40) loadOlder();
};

const autosize = () => {
    const el = input.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 112)}px`;
};

const send = () => {
    if (!draft.value.trim()) return;
    chat.sendMessage(props.friendId, draft.value.slice(0, 2000));
    draft.value = '';
    nextTick(autosize);
    scrollToBottom();
};

const onKeydown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
        e.preventDefault();
        send();
    }
};

const onInput = () => {
    autosize();
    chat.notifyTyping(props.friendId);
};

const onFocus = () => chat.markRead(props.friendId);

watch(() => thread.value.messages.length, async (length, previous) => {
    const lastMine = thread.value.messages[length - 1]?.sender_id === me.value;
    if (isNearBottom() || lastMine || !previous) await scrollToBottom();
});

watch(typing, (value) => { if (value && isNearBottom()) scrollToBottom(); });

watch(() => props.minimized, (value) => {
    if (!value) {
        scrollToBottom();
        nextTick(() => input.value?.focus());
    }
});

onMounted(() => {
    scrollToBottom();
    if (!props.minimized) input.value?.focus();
});
</script>

<template>
    <section class="flex flex-col w-full md:w-80 bg-[var(--panel-color)] border border-[var(--rule-color)] shadow-md pointer-events-auto"
             :class="minimized ? '' : 'h-[100dvh] md:h-[26rem]'"
             :aria-label="friend?.name">
        <header class="flex items-center gap-2.5 px-3 h-12 shrink-0 border-b border-[var(--border-color)] cursor-pointer select-none"
                @click="chat.toggleMinimized(friendId)">
            <span class="relative shrink-0">
                <img :src="friend?.profile_photo_url" :alt="friend?.name" class="w-8 h-8 rounded-full object-cover border border-[var(--border-color)]" />
                <span v-if="friend?.online" class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-[var(--panel-color)]"></span>
            </span>
            <span class="flex-1 min-w-0">
                <span class="block text-sm text-[var(--main-color)] truncate">{{ friend?.name ?? '…' }}</span>
                <span class="block font-mono text-[10px] text-[var(--sub-color)] truncate">
                    {{ typing ? t('chat.typing') : (friend?.online ? t('chat.online') : t('chat.offline')) }}
                </span>
            </span>
            <span v-if="minimized && friend?.unread"
                  class="min-w-[18px] h-[18px] px-1 rounded-full bg-[var(--error-color)] text-white text-[10px] font-mono flex items-center justify-center">
                {{ friend.unread > 9 ? '9+' : friend.unread }}
            </span>
            <button type="button" :aria-label="minimized ? t('chat.expand') : t('chat.minimize')"
                    class="w-9 h-9 flex items-center justify-center text-[var(--sub-color)] hover:text-[var(--main-color)] transition-colors"
                    @click.stop="chat.toggleMinimized(friendId)">
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path v-if="minimized" d="M6 15l6-6 6 6" />
                    <path v-else d="M5 12h14" />
                </svg>
            </button>
            <button type="button" :aria-label="t('chat.close')"
                    class="w-9 h-9 flex items-center justify-center text-[var(--sub-color)] hover:text-[var(--error-color)] transition-colors"
                    @click.stop="chat.closeChat(friendId)">
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </header>

        <template v-if="!minimized">
            <div ref="scroller" class="flex-1 overflow-y-auto overscroll-contain px-3 py-3" @scroll.passive="onScroll">
                <p v-if="thread.loading && !thread.messages.length" class="text-center font-mono text-[11px] text-[var(--sub-color)] py-6">
                    {{ t('chat.loading') }}
                </p>
                <p v-else-if="thread.hasMore" class="text-center py-1">
                    <button type="button" class="font-mono text-[10px] uppercase tracking-[0.15em] text-[var(--lapis-color)] hover:opacity-80" @click="loadOlder">
                        {{ thread.loading ? t('chat.loading') : t('chat.older') }}
                    </button>
                </p>

                <div v-if="thread.loaded && !thread.messages.length" class="flex flex-col items-center text-center gap-2 py-8">
                    <img :src="friend?.profile_photo_url" :alt="friend?.name" class="w-14 h-14 rounded-full border border-[var(--rule-color)]" />
                    <p class="text-sm text-[var(--main-color)]">{{ friend?.name }}</p>
                    <p class="font-mono text-[11px] text-[var(--sub-color)]">{{ t('chat.say_salam') }}</p>
                </div>

                <template v-for="row in rows" :key="row.id">
                    <div v-if="row.showDay" class="flex items-center gap-3 my-3">
                        <span class="flex-1 border-t border-[var(--border-color)]"></span>
                        <span class="font-mono text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)]">{{ dayLabel(row.created_at) }}</span>
                        <span class="flex-1 border-t border-[var(--border-color)]"></span>
                    </div>

                    <div class="flex items-end gap-2" :class="[row.mine ? 'justify-end' : 'justify-start', row.first ? 'mt-2' : 'mt-0.5']">
                        <span v-if="!row.mine" class="w-6 shrink-0">
                            <img v-if="row.last" :src="friend?.profile_photo_url" alt="" class="w-6 h-6 rounded-full object-cover" />
                        </span>
                        <div class="max-w-[75%] flex flex-col" :class="row.mine ? 'items-end' : 'items-start'">
                            <p class="px-3 py-1.5 text-sm leading-snug whitespace-pre-wrap break-words [unicode-bidi:plaintext]"
                               :title="timeLabel(row.created_at)"
                               :class="[
                                   row.mine
                                       ? 'bg-[var(--lapis-color)] text-white rounded-2xl rounded-ee-md'
                                       : 'bg-[var(--bg-color)] text-[var(--main-color)] border border-[var(--border-color)] rounded-2xl rounded-es-md',
                                   row.status === 'sending' ? 'opacity-60' : '',
                                   row.status === 'failed' ? 'opacity-60 ring-1 ring-[var(--error-color)]' : '',
                               ]">{{ row.body }}</p>
                            <button v-if="row.status === 'failed'" type="button"
                                    class="mt-0.5 font-mono text-[10px] text-[var(--error-color)] hover:underline"
                                    @click="chat.retryMessage(friendId, row)">
                                {{ row.error || t('chat.failed') }} · {{ t('chat.retry') }}
                            </button>
                            <span v-else-if="row.last" class="mt-0.5 font-mono text-[9px] text-[var(--sub-color)]">
                                {{ timeLabel(row.created_at) }}<template v-if="row.id === lastSeenId"> · {{ t('chat.seen') }}</template>
                            </span>
                        </div>
                    </div>
                </template>

                <div v-if="typing" class="flex items-end gap-2 mt-2">
                    <img :src="friend?.profile_photo_url" alt="" class="w-6 h-6 rounded-full object-cover" />
                    <span class="chat-typing px-3 py-2.5 bg-[var(--bg-color)] border border-[var(--border-color)] rounded-2xl rounded-es-md flex gap-1" :aria-label="t('chat.typing')">
                        <span></span><span></span><span></span>
                    </span>
                </div>
            </div>

            <form class="flex items-end gap-2 px-2 py-2 border-t border-[var(--border-color)] shrink-0" @submit.prevent="send">
                <textarea ref="input" v-model="draft" rows="1" maxlength="2000" dir="auto"
                          :placeholder="t('chat.placeholder')"
                          class="flex-1 resize-none max-h-28 px-3 py-2 text-sm bg-[var(--bg-color)] text-[var(--main-color)] border border-[var(--border-color)] rounded-2xl focus:outline-none focus:border-[var(--lapis-color)] focus:ring-0 placeholder:text-[var(--sub-color)]"
                          @keydown="onKeydown" @input="onInput" @focus="onFocus"></textarea>
                <button type="submit" :disabled="!draft.trim()" :aria-label="t('chat.send')"
                        class="w-10 h-10 shrink-0 flex items-center justify-center text-[var(--lapis-color)] disabled:text-[var(--sub-color)] disabled:opacity-50 hover:opacity-80 transition-opacity">
                    <svg viewBox="0 0 24 24" class="w-5 h-5 rtl:-scale-x-100" fill="currentColor"><path d="M3.4 20.4l17.45-7.48a1 1 0 000-1.84L3.4 3.6a1 1 0 00-1.39 1.2L4.5 12l-2.49 7.2a1 1 0 001.39 1.2z" /></svg>
                </button>
            </form>
        </template>
    </section>
</template>

<style scoped>
.chat-typing span {
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    background: var(--sub-color);
    animation: chat-typing 1.2s infinite ease-in-out;
}
.chat-typing span:nth-child(2) { animation-delay: 0.15s; }
.chat-typing span:nth-child(3) { animation-delay: 0.3s; }

@keyframes chat-typing {
    0%, 60%, 100% { opacity: 0.3; }
    30% { opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
    .chat-typing span { animation: none; opacity: 0.6; }
}
</style>
