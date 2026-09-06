<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useSettings } from '../useSettings';

const { t, currentLang } = useSettings();
const page = usePage();

const open = ref(false);
const localRead = ref(false);

const items = computed(() => page.props.auth?.notifications ?? []);
const unread = computed(() => (localRead.value ? 0 : page.props.auth?.unread_count ?? 0));

const LABELS = {
    friend_request: 'notifications.friend_request',
    friend_accepted: 'notifications.friend_accepted',
    ghost_won: 'notifications.ghost_won',
    ghost_raced: 'notifications.ghost_raced',
};

const label = (n) => t(LABELS[n.type] || 'notifications.generic').replace('{name}', n.actor_name || '');

const timeAgo = (iso) => {
    const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (s < 60) return t('notifications.now');
    const m = Math.floor(s / 60);
    if (m < 60) return `${m}m`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h`;
    return `${Math.floor(h / 24)}d`;
};

const markAll = async () => {
    if (localRead.value) return;
    try {
        await axios.post('/notifications/read');
        localRead.value = true;
    } catch {
        // ignore — will settle on next full navigation
    }
};

const toggle = () => {
    open.value = !open.value;
    if (open.value && unread.value > 0) markAll();
};

const go = (n) => {
    open.value = false;
    router.visit(n.url);
};

if (typeof window !== 'undefined') {
    window.addEventListener('click', (e) => {
        if (!e.target.closest('.notif-container')) open.value = false;
    });
}
</script>

<template>
    <div class="relative notif-container">
        <button type="button" @click.stop="toggle" :aria-label="t('notifications.title')"
                class="relative p-1.5 border border-[var(--border-color)] hover:border-[var(--caret-color)] transition-colors">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                 class="w-4 h-4 text-[var(--sub-color)]">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <span v-if="unread"
                  class="absolute -top-1.5 -right-1.5 min-w-[16px] h-4 px-1 rounded-full bg-[var(--caret-color)] text-[var(--bg-color)] text-[9px] font-mono flex items-center justify-center">
                {{ unread > 9 ? '9+' : unread }}
            </span>
        </button>

        <transition name="dropdown">
            <div v-if="open"
                 class="absolute mt-2 w-72 bg-[var(--panel-color)] border border-[var(--border-color)] z-[60]"
                 :class="currentLang === 'ar' ? 'left-0' : 'right-0'">
                <div class="px-4 py-2.5 border-b border-[var(--border-color)] font-mono text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)]">
                    {{ t('notifications.title') }}
                </div>
                <div v-if="items.length" class="max-h-80 overflow-y-auto divide-y divide-[var(--border-color)]">
                    <button v-for="n in items" :key="n.id" type="button" @click="go(n)"
                            class="w-full text-left px-4 py-3 flex items-start gap-2 hover:bg-[var(--caret-color)]/5 transition-colors"
                            :class="(n.read || localRead) ? 'opacity-55' : ''">
                        <span class="mt-1 w-1.5 h-1.5 rounded-full shrink-0"
                              :class="(n.read || localRead) ? 'bg-transparent' : 'bg-[var(--caret-color)]'"></span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-xs text-[var(--main-color)] leading-snug">{{ label(n) }}</span>
                            <span class="block text-[10px] font-mono text-[var(--sub-color)] mt-0.5">{{ timeAgo(n.created_at) }}</span>
                        </span>
                    </button>
                </div>
                <div v-else class="px-4 py-8 text-center font-mono text-[11px] text-[var(--sub-color)]">
                    {{ t('notifications.empty') }}
                </div>
            </div>
        </transition>
    </div>
</template>
