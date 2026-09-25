<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ChatWindow from '@/Components/Chat/ChatWindow.vue';
import { useChat, startChat, stopChat } from '@/composables/useChat';
import { useSettings } from '@/useSettings';

const { t, currentLang } = useSettings();
const page = usePage();
const chat = useChat();
const search = ref('');

const conversations = computed(() => {
    const term = search.value.trim().toLowerCase();
    return term
        ? chat.state.conversations.filter((c) => c.name.toLowerCase().includes(term))
        : chat.state.conversations;
});

const onlineCount = computed(() => chat.state.conversations.filter((c) => c.online).length);

const locale = computed(() => ({ ar: 'ar', fr: 'fr' }[currentLang.value] ?? 'en'));

const shortTime = (iso) => {
    const date = new Date(iso);
    const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
    if (minutes < 1) return t('chat.now');
    if (minutes < 60) return `${minutes}m`;
    if (minutes < 1440) return `${Math.floor(minutes / 60)}h`;
    if (minutes < 10080) return date.toLocaleDateString(locale.value, { weekday: 'short' });
    return date.toLocaleDateString(locale.value, { day: 'numeric', month: 'short' });
};

const preview = (c) => {
    const m = c.last_message;
    if (!m) return c.online ? t('chat.active_now') : t('chat.start');
    return m.sender_id === chat.state.me ? `${t('chat.you')}: ${m.body}` : m.body;
};

const toggleList = () => {
    chat.state.listOpen = !chat.state.listOpen;
    if (chat.state.listOpen) chat.loadConversations();
};

const open = (friendId) => {
    search.value = '';
    chat.openChat(friendId);
};

onMounted(() => startChat(page.props.auth?.user, page.props.auth?.chat_unread ?? 0));

watch(() => page.props.auth?.user?.id, (id) => {
    if (id) startChat(page.props.auth.user, page.props.auth.chat_unread ?? 0);
    else stopChat();
});

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') chat.state.listOpen = false;
    });
}
</script>

<template>
    <div v-if="$page.props.auth?.user" :style="{ bottom: `${chat.state.bottomOffset}px` }" class="fixed end-3 md:end-4 z-40 transition-[bottom] duration-300 flex flex-row-reverse items-end gap-3 pointer-events-none">
        <!-- Launcher + friend list -->
        <div class="flex flex-col items-end gap-3 pb-3 md:pb-4">
            <transition name="dropdown">
                <div v-if="chat.state.listOpen"
                     class="pointer-events-auto fixed inset-0 z-[70] md:static md:z-auto flex flex-col md:w-80 md:h-[28rem] bg-[var(--panel-color)] border border-[var(--rule-color)] shadow-md">
                    <header class="flex items-center gap-2 px-4 h-12 shrink-0 border-b border-[var(--border-color)]">
                        <h2 class="flex-1 text-sm font-semibold text-[var(--main-color)]">{{ t('chat.title') }}</h2>
                        <span class="font-mono text-[10px] uppercase tracking-[0.15em] text-[var(--sub-color)]">
                            {{ t('chat.online_count').replace('{n}', onlineCount) }}
                        </span>
                        <button type="button" :aria-label="t('chat.close')"
                                class="w-9 h-9 flex items-center justify-center text-[var(--sub-color)] hover:text-[var(--main-color)] transition-colors"
                                @click="chat.state.listOpen = false">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                        </button>
                    </header>

                    <div class="px-3 py-2 border-b border-[var(--border-color)]">
                        <input v-model="search" type="search" :placeholder="t('chat.search')"
                               class="w-full px-3 py-1.5 text-sm bg-[var(--bg-color)] text-[var(--main-color)] border border-[var(--border-color)] rounded-full focus:outline-none focus:border-[var(--lapis-color)] focus:ring-0 placeholder:text-[var(--sub-color)]" />
                    </div>

                    <div class="flex-1 overflow-y-auto overscroll-contain">
                        <p v-if="!chat.state.loaded" class="px-4 py-8 text-center font-mono text-[11px] text-[var(--sub-color)]">{{ t('chat.loading') }}</p>

                        <div v-else-if="!chat.state.conversations.length" class="px-6 py-10 text-center flex flex-col items-center gap-3">
                            <p class="text-sm text-[var(--sub-color)]">{{ t('chat.no_friends') }}</p>
                            <Link href="/friends" class="font-mono text-[11px] uppercase tracking-[0.15em] text-[var(--lapis-color)] hover:opacity-80"
                                  @click="chat.state.listOpen = false">
                                {{ t('chat.find_friends') }}
                            </Link>
                        </div>

                        <p v-else-if="!conversations.length" class="px-4 py-8 text-center font-mono text-[11px] text-[var(--sub-color)]">{{ t('chat.no_match') }}</p>

                        <button v-for="c in conversations" :key="c.id" type="button"
                                class="w-full flex items-center gap-3 px-3 py-2.5 text-start hover:bg-[var(--caret-color)]/10 transition-colors"
                                @click="open(c.id)">
                            <span class="relative shrink-0">
                                <img :src="c.profile_photo_url" :alt="c.name" class="w-10 h-10 rounded-full object-cover border border-[var(--border-color)]" />
                                <span v-if="c.online" class="absolute bottom-0 end-0 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-[var(--panel-color)]"></span>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="flex items-baseline gap-2">
                                    <span class="flex-1 truncate text-sm" :class="c.unread ? 'font-semibold text-[var(--main-color)]' : 'text-[var(--main-color)]'">{{ c.name }}</span>
                                    <span v-if="c.last_message" class="shrink-0 font-mono text-[10px] text-[var(--sub-color)]">{{ shortTime(c.last_message.created_at) }}</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="flex-1 truncate text-xs [unicode-bidi:plaintext]" :class="c.unread ? 'text-[var(--main-color)] font-medium' : 'text-[var(--sub-color)]'">{{ preview(c) }}</span>
                                    <span v-if="c.unread" class="shrink-0 min-w-[18px] h-[18px] px-1 rounded-full bg-[var(--lapis-color)] text-white text-[10px] font-mono flex items-center justify-center">
                                        {{ c.unread > 9 ? '9+' : c.unread }}
                                    </span>
                                </span>
                            </span>
                        </button>
                    </div>
                </div>
            </transition>

            <button type="button" :aria-label="t('chat.title')" :aria-expanded="chat.state.listOpen"
                    class="pointer-events-auto relative flex items-center gap-2 h-11 px-4 bg-[var(--panel-color)] border border-[var(--rule-color)] text-[var(--main-color)] shadow-md hover:border-[var(--caret-color)] transition-colors"
                    @click="toggleList">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-[var(--lapis-color)]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
                </svg>
                <span class="hidden sm:inline text-xs uppercase tracking-[0.12em]">{{ t('chat.title') }}</span>
                <span v-if="chat.unreadTotal.value"
                      class="absolute -top-2 -end-2 min-w-[20px] h-5 px-1 rounded-full bg-[var(--error-color)] text-white text-[10px] font-mono flex items-center justify-center">
                    {{ chat.unreadTotal.value > 99 ? '99+' : chat.unreadTotal.value }}
                </span>
            </button>
        </div>

        <!-- Open conversations -->
        <div v-for="win in chat.state.windows" :key="win.id"
             :class="win.minimized ? 'hidden md:block' : 'fixed inset-0 z-[70] md:static md:z-auto'">
            <ChatWindow :friend-id="win.id" :minimized="win.minimized" />
        </div>
    </div>
</template>
