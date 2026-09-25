import { reactive, computed } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const HEARTBEAT_MS = 30000;
const TYPING_THROTTLE_MS = 2500;
const TYPING_VISIBLE_MS = 4000;
const STORAGE_KEY = 'chat_windows';

/**
 * One chat store for the whole SPA session, so open windows and threads survive
 * Inertia page visits (the layout remounts on pages that don't use a persistent layout).
 */
const state = reactive({
    me: null,
    started: false,
    loaded: false,
    listOpen: false,
    conversations: [],
    threads: {},
    windows: [],
    typing: {},
    initialUnread: 0,
    bottomOffset: 0,
});

let heartbeatTimer = null;
let tempId = 0;
const lastTypingSent = {};
const typingTimers = {};
const pairChannels = {};

const conversation = (friendId) => state.conversations.find((c) => c.id === friendId);

const pairName = (friendId) => {
    const [low, high] = [state.me, friendId].sort((a, b) => a - b);
    return `chat.${low}.${high}`;
};

const persistWindows = () => {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state.windows));
    } catch {
        // storage blocked — windows just won't survive a full reload
    }
};

const ensureThread = (friendId) => {
    if (!state.threads[friendId]) {
        state.threads[friendId] = { messages: [], loaded: false, loading: false, hasMore: false };
    }
    return state.threads[friendId];
};

const appendMessage = (friendId, message) => {
    const thread = ensureThread(friendId);
    if (thread.messages.some((m) => m.id === message.id)) return false;
    thread.messages.push(message);
    return true;
};

const touchConversation = (friendId, message) => {
    const convo = conversation(friendId);
    if (!convo) return;
    convo.last_message = message;
    const at = (c) => (c.last_message ? new Date(c.last_message.created_at).getTime() : 0);
    state.conversations.sort((a, b) => at(b) - at(a));
};

const isWindowActive = (friendId) => {
    const win = state.windows.find((w) => w.id === friendId);
    return !!win && !win.minimized && document.visibilityState === 'visible';
};

async function loadConversations() {
    try {
        const { data } = await axios.get('/chat');
        state.conversations = data.conversations;
        state.loaded = true;
    } catch {
        // dock stays usable with whatever it already has
    }
}

async function heartbeat() {
    try {
        const { data } = await axios.post('/chat/heartbeat');
        const online = new Set(data.online);
        state.conversations.forEach((c) => { c.online = online.has(c.id); });
    } catch {
        // offline or logged out — next beat retries
    }
}

async function loadThread(friendId, older = false) {
    const thread = ensureThread(friendId);
    if (thread.loading || (thread.loaded && !older)) return;
    thread.loading = true;
    try {
        const before = older ? thread.messages.find((m) => m.id > 0)?.id : null;
        const { data } = await axios.get(`/chat/${friendId}`, { params: before ? { before } : {} });
        const known = new Set(thread.messages.map((m) => m.id));
        const fresh = data.messages.filter((m) => !known.has(m.id));
        thread.messages = [...fresh, ...thread.messages];
        thread.hasMore = data.has_more;
        thread.loaded = true;
    } finally {
        thread.loading = false;
    }
}

async function markRead(friendId) {
    const convo = conversation(friendId);
    if (convo && !convo.unread) return;
    if (convo) convo.unread = 0;
    try {
        await axios.post(`/chat/${friendId}/read`);
    } catch {
        // retried the next time the window is focused
    }
}

function subscribePair(friendId) {
    if (pairChannels[friendId] || !window.Echo) return;
    pairChannels[friendId] = window.Echo.private(pairName(friendId))
        .listenForWhisper('typing', (e) => {
            if (e.id !== friendId) return;
            state.typing[friendId] = true;
            clearTimeout(typingTimers[friendId]);
            typingTimers[friendId] = setTimeout(() => { state.typing[friendId] = false; }, TYPING_VISIBLE_MS);
        });
}

function unsubscribePair(friendId) {
    if (!pairChannels[friendId]) return;
    window.Echo?.leave(pairName(friendId));
    delete pairChannels[friendId];
    state.typing[friendId] = false;
}

function onIncoming({ message }) {
    const mine = message.sender_id === state.me;
    const friendId = mine ? message.recipient_id : message.sender_id;

    if (mine) {
        const thread = ensureThread(friendId);
        const pending = thread.messages.find((m) => m.id < 0 && m.body === message.body && m.status === 'sending');
        if (pending) return;
    }

    appendMessage(friendId, message);
    touchConversation(friendId, message);

    if (mine) return;

    state.typing[friendId] = false;
    const convo = conversation(friendId);

    if (isWindowActive(friendId)) {
        markRead(friendId);
        return;
    }

    if (convo) {
        convo.unread = (convo.unread ?? 0) + 1;
    } else {
        state.initialUnread += 1;
        loadConversations();
    }

    if (!state.windows.some((w) => w.id === friendId) && window.matchMedia('(min-width: 768px)').matches) {
        openChat(friendId, { minimized: true });
    }
}

function onNotification(notification) {
    router.reload({ only: ['auth', 'incoming', 'outgoing', 'friends'] });
    if (notification.type === 'friend_accepted') loadConversations();
}

function onRead(e) {
    if (e.reader_id === state.me) {
        const convo = conversation(e.sender_id);
        if (convo) convo.unread = 0;
        return;
    }
    const thread = state.threads[e.reader_id];
    thread?.messages.forEach((m) => {
        if (m.sender_id === state.me && m.id > 0 && m.id <= e.up_to_id && !m.read_at) m.read_at = e.read_at;
    });
    const last = conversation(e.reader_id)?.last_message;
    if (last && last.sender_id === state.me && last.id <= e.up_to_id) last.read_at = e.read_at;
}

export function startChat(user, unread = 0) {
    if (!user || state.started) return;
    state.started = true;
    state.me = user.id;
    state.initialUnread = unread;

    window.Echo?.private(`App.Models.User.${user.id}`)
        .listen('.chat.message', onIncoming)
        .listen('.chat.read', onRead)
        .notification(onNotification);

    try {
        const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
        if (Array.isArray(saved)) {
            state.windows = saved.filter((w) => Number.isInteger(w.id));
            state.windows.forEach((w) => subscribePair(w.id));
        }
    } catch {
        // ignore corrupt storage
    }

    loadConversations();
    heartbeatTimer = setInterval(heartbeat, HEARTBEAT_MS);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') return;
        heartbeat();
        state.windows.filter((w) => !w.minimized).forEach((w) => markRead(w.id));
    });
}

export function stopChat() {
    if (!state.started) return;
    clearInterval(heartbeatTimer);
    window.Echo?.leave(`App.Models.User.${state.me}`);
    Object.keys(pairChannels).forEach((id) => unsubscribePair(Number(id)));
    Object.assign(state, { me: null, started: false, loaded: false, listOpen: false, conversations: [], threads: {}, windows: [], typing: {} });
    persistWindows();
}

export function openChat(friendId, { minimized = false } = {}) {
    const max = window.matchMedia('(min-width: 1280px)').matches ? 3 : window.matchMedia('(min-width: 768px)').matches ? 2 : 1;
    const existing = state.windows.find((w) => w.id === friendId);

    if (existing) {
        if (!minimized) existing.minimized = false;
    } else {
        state.windows.unshift({ id: friendId, minimized });
        while (state.windows.length > max) {
            unsubscribePair(state.windows.pop().id);
        }
    }

    subscribePair(friendId);
    persistWindows();
    loadThread(friendId);
    if (!minimized) {
        state.listOpen = false;
        markRead(friendId);
    }
}

export function closeChat(friendId) {
    state.windows = state.windows.filter((w) => w.id !== friendId);
    unsubscribePair(friendId);
    persistWindows();
}

export function toggleMinimized(friendId) {
    const win = state.windows.find((w) => w.id === friendId);
    if (!win) return;
    win.minimized = !win.minimized;
    persistWindows();
    if (!win.minimized) markRead(friendId);
}

export async function sendMessage(friendId, body) {
    const text = body.trim();
    if (!text) return;

    const optimistic = {
        id: --tempId,
        sender_id: state.me,
        recipient_id: friendId,
        body: text,
        read_at: null,
        created_at: new Date().toISOString(),
        status: 'sending',
    };
    appendMessage(friendId, optimistic);
    touchConversation(friendId, optimistic);

    try {
        const { data } = await axios.post(`/chat/${friendId}`, { body: text });
        const thread = ensureThread(friendId);
        const index = thread.messages.findIndex((m) => m.id === optimistic.id);
        const alreadyEchoed = thread.messages.some((m) => m.id === data.message.id);
        if (index !== -1) {
            if (alreadyEchoed) thread.messages.splice(index, 1);
            else thread.messages.splice(index, 1, data.message);
        }
        touchConversation(friendId, data.message);
    } catch (e) {
        const target = ensureThread(friendId).messages.find((m) => m.id === optimistic.id);
        if (target) {
            target.status = 'failed';
            target.error = e.response?.data?.errors?.body?.[0] ?? null;
        }
    }
}

export function retryMessage(friendId, message) {
    const thread = ensureThread(friendId);
    thread.messages = thread.messages.filter((m) => m.id !== message.id);
    sendMessage(friendId, message.body);
}

export function notifyTyping(friendId) {
    const now = Date.now();
    if (now - (lastTypingSent[friendId] ?? 0) < TYPING_THROTTLE_MS) return;
    lastTypingSent[friendId] = now;
    try {
        pairChannels[friendId]?.whisper('typing', { id: state.me });
    } catch {
        // client events disabled on the socket server — typing hints just don't show
    }
}

/**
 * Lift the dock above a page's own bottom-docked UI (e.g. the typing test's virtual keyboard).
 */
export function setChatBottomOffset(px) {
    state.bottomOffset = Math.max(0, Math.round(px));
}

export function useChat() {
    const unreadTotal = computed(() => (state.loaded
        ? state.conversations.reduce((sum, c) => sum + (c.unread || 0), 0)
        : state.initialUnread));

    return {
        state,
        unreadTotal,
        conversation,
        loadConversations,
        loadThread,
        markRead,
        openChat,
        closeChat,
        toggleMinimized,
        sendMessage,
        retryMessage,
        notifyTyping,
    };
}
