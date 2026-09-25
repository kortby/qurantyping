<script setup>
import { computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ChatList from '@/Components/Chat/ChatList.vue';
import ChatWindow from '@/Components/Chat/ChatWindow.vue';
import { useChat, startChat, stopChat } from '@/composables/useChat';
import { useSettings } from '@/useSettings';

const { t } = useSettings();
const page = usePage();
const chat = useChat();

const signedIn = computed(() => !!page.props.auth?.user);
const unread = computed(() => chat.unreadTotal.value);
const compact = computed(() => chat.state.focusMode);
const windowLift = computed(() => (chat.state.keyboard?.visible ? chat.state.keyboard.height : 0));

const toggleList = () => {
    chat.state.listOpen = !chat.state.listOpen;
    if (chat.state.listOpen) chat.loadConversations();
};

onMounted(() => startChat(page.props.auth?.user, page.props.auth?.chat_unread ?? 0));

watch(() => page.props.auth?.user?.id, (id) => {
    if (id) startChat(page.props.auth.user, page.props.auth.chat_unread ?? 0);
    else stopChat();
});

if (typeof window !== 'undefined') {
    window.addEventListener('click', (e) => {
        if (!e.target.closest('.chat-dock-controls')) chat.state.listOpen = false;
    });
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') chat.state.listOpen = false;
    });
}
</script>

<template>
    <div v-if="signedIn || chat.state.keyboard"
         class="fixed bottom-0 end-3 md:end-4 z-[55] flex flex-row-reverse items-end gap-3 pointer-events-none">
        <!-- Friend list + the dock's control bar -->
        <div class="chat-dock-controls flex flex-col items-end gap-3 pb-3 md:pb-4">
            <transition name="dropdown">
                <ChatList v-if="signedIn && chat.state.listOpen" />
            </transition>

            <div class="pointer-events-auto flex items-stretch h-10 bg-[var(--panel-color)] border border-[var(--rule-color)] shadow-md divide-x divide-[var(--border-color)] rtl:divide-x-reverse font-mono text-[10px] uppercase tracking-[0.15em]">
                <button v-if="chat.state.keyboard" type="button" :aria-pressed="chat.state.keyboard.visible"
                        :aria-label="chat.state.keyboard.visible ? t('hide_keyboard') : t('show_keyboard')"
                        :title="compact ? (chat.state.keyboard.visible ? t('hide_keyboard') : t('show_keyboard')) : null"
                        class="flex items-center gap-2 transition-colors"
                        :class="[compact ? 'px-3' : 'px-3.5', chat.state.keyboard.visible ? 'text-[var(--main-color)]' : 'text-[var(--sub-color)] hover:text-[var(--main-color)]']"
                        @click="chat.state.keyboard.toggle()">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2.5" y="6" width="19" height="12" rx="1.5" />
                        <path d="M6 10h.01M9.5 10h.01M13 10h.01M16.5 10h.01M8 14h8" />
                    </svg>
                    <span v-if="!compact">{{ chat.state.keyboard.visible ? t('hide_keyboard') : t('show_keyboard') }}</span>
                </button>

                <button v-if="signedIn" type="button" :aria-expanded="chat.state.listOpen" :aria-label="t('chat.title')"
                        :title="compact ? t('chat.title') : null"
                        class="relative flex items-center gap-2 transition-colors"
                        :class="[compact ? 'px-3' : 'px-3.5', chat.state.listOpen ? 'text-[var(--main-color)]' : 'text-[var(--sub-color)] hover:text-[var(--main-color)]']"
                        @click="toggleList">
                    <svg viewBox="0 0 24 24" class="w-4 h-4 text-[var(--lapis-color)]" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
                    </svg>
                    <span v-if="!compact">{{ t('chat.title') }}</span>
                    <span v-if="unread && compact" class="absolute top-2 end-2 w-2 h-2 rounded-full bg-[var(--lapis-color)]"></span>
                    <span v-else-if="unread"
                          class="min-w-[18px] h-[18px] px-1 rounded-full bg-[var(--lapis-color)] text-white text-[10px] tracking-normal flex items-center justify-center">
                        {{ unread > 99 ? '99+' : unread }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Open conversations -->
        <div v-for="win in chat.state.windows" :key="win.id"
             :style="windowLift ? { marginBottom: `${windowLift}px` } : null"
             class="transition-[margin] duration-300"
             :class="win.minimized ? 'hidden md:block' : 'fixed inset-0 z-[70] md:static md:z-auto'">
            <ChatWindow :friend-id="win.id" :minimized="win.minimized" />
        </div>
    </div>
</template>
