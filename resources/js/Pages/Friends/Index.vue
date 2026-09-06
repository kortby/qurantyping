<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

const props = defineProps({
    friends: { type: Array, default: () => [] },
    incoming: { type: Array, default: () => [] },
    outgoing: { type: Array, default: () => [] },
    results: { type: Array, default: () => null },
    query: { type: String, default: '' },
});

const { t } = useSettings();

const search = ref(props.query || '');
const busy = ref(false);
let debounce = null;

watch(search, (value) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.reload({
            only: ['results', 'query'],
            data: { q: value },
        });
    }, 300);
});

const act = (method, url, data = {}) => {
    if (busy.value) return;
    busy.value = true;
    router[method](url, data, {
        preserveScroll: true,
        onFinish: () => (busy.value = false),
    });
};

const sendRequest = (id) => act('post', '/friends', { friend_id: id });
const accept = (friendshipId) => act('patch', `/friends/${friendshipId}`);
const remove = (friendshipId) => act('delete', `/friends/${friendshipId}`);
</script>

<template>
    <Head><title>{{ t('friends.title') }} - QuranTyping</title></Head>

    <AppLayout>
        <div class="py-10 sm:py-12 animate-fade-in min-h-[80vh]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6">
                <header class="mb-8 flex items-end justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-cinzel font-semibold text-[var(--caret-color)]">{{ t('friends.title') }}</h1>
                        <p class="text-[var(--sub-color)] font-mono text-[10px] uppercase tracking-[0.3em] mt-1">
                            {{ t('friends.subtitle') }}
                        </p>
                    </div>
                    <Link href="/?ghost=pb"
                          class="shrink-0 border border-[var(--border-color)] text-[var(--main-color)] px-4 py-2 font-mono text-[10px] uppercase tracking-[0.2em] hover:border-[var(--caret-color)] transition-colors">
                        {{ t('friends.race_pb') }}
                    </Link>
                </header>

                <!-- Find people -->
                <section class="mb-10">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('friends.find') }}</h2>
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="t('friends.search_placeholder')"
                        class="w-full bg-transparent border border-[var(--border-color)] px-4 py-2.5 font-mono text-sm text-[var(--main-color)] focus:border-[var(--caret-color)] focus:outline-none"
                    />
                    <div v-if="results && results.length" class="mt-2 border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                        <div v-for="person in results" :key="person.id" class="flex items-center justify-between px-4 py-3">
                            <span class="flex items-center gap-3">
                                <img v-if="person.profile_photo_url" :src="person.profile_photo_url" alt="" class="w-7 h-7 rounded-full object-cover" />
                                <span class="text-[var(--main-color)]">{{ person.name }}</span>
                            </span>
                            <button type="button" :disabled="busy" @click="sendRequest(person.id)"
                                    class="text-[10px] uppercase tracking-[0.2em] text-[var(--caret-color)] hover:opacity-70 disabled:opacity-40 transition-opacity">
                                {{ t('friends.add') }}
                            </button>
                        </div>
                    </div>
                    <p v-else-if="results && search.length >= 2" class="mt-2 font-mono text-xs text-[var(--sub-color)]">
                        {{ t('friends.no_matches') }}
                    </p>
                </section>

                <!-- Incoming requests -->
                <section v-if="incoming.length" class="mb-10">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('friends.incoming') }}</h2>
                    <div class="border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                        <div v-for="row in incoming" :key="row.friendship_id" class="flex items-center justify-between px-4 py-3">
                            <span class="flex items-center gap-3">
                                <img v-if="row.profile_photo_url" :src="row.profile_photo_url" alt="" class="w-7 h-7 rounded-full object-cover" />
                                <span class="text-[var(--main-color)]">{{ row.name }}</span>
                            </span>
                            <span class="flex items-center gap-4 text-[10px] uppercase tracking-[0.2em]">
                                <button type="button" :disabled="busy" @click="accept(row.friendship_id)"
                                        class="text-[var(--caret-color)] hover:opacity-70 disabled:opacity-40 transition-opacity">
                                    {{ t('friends.accept') }}
                                </button>
                                <button type="button" :disabled="busy" @click="remove(row.friendship_id)"
                                        class="text-[var(--sub-color)] hover:text-[var(--error-color)] disabled:opacity-40 transition-colors">
                                    {{ t('friends.decline') }}
                                </button>
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Outgoing requests -->
                <section v-if="outgoing.length" class="mb-10">
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('friends.outgoing') }}</h2>
                    <div class="border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                        <div v-for="row in outgoing" :key="row.friendship_id" class="flex items-center justify-between px-4 py-3">
                            <span class="text-[var(--main-color)]">{{ row.name }}</span>
                            <button type="button" :disabled="busy" @click="remove(row.friendship_id)"
                                    class="text-[10px] uppercase tracking-[0.2em] text-[var(--sub-color)] hover:text-[var(--error-color)] disabled:opacity-40 transition-colors">
                                {{ t('friends.cancel') }}
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Friends -->
                <section>
                    <h2 class="font-mono text-[10px] uppercase tracking-[0.25em] text-[var(--sub-color)] mb-3">{{ t('friends.your_friends') }}</h2>
                    <div v-if="friends.length" class="border border-[var(--border-color)] divide-y divide-[var(--border-color)] font-mono text-sm">
                        <div v-for="friend in friends" :key="friend.id" class="flex items-center justify-between px-4 py-3 gap-4">
                            <span class="flex items-center gap-3 min-w-0">
                                <img v-if="friend.profile_photo_url" :src="friend.profile_photo_url" alt="" class="w-7 h-7 rounded-full object-cover shrink-0" />
                                <span class="text-[var(--main-color)] truncate">{{ friend.name }}</span>
                                <span v-if="friend.best_wpm" class="text-[var(--sub-color)] tabular-nums shrink-0">{{ friend.best_wpm }} wpm</span>
                            </span>
                            <span class="flex items-center gap-4 text-[10px] uppercase tracking-[0.2em] shrink-0">
                                <Link v-if="friend.ghost_test_id" :href="`/?ghost=${friend.ghost_test_id}`"
                                      class="text-[var(--caret-color)] hover:opacity-70 transition-opacity">
                                    {{ t('friends.race_ghost') }}
                                </Link>
                                <span v-else class="text-[var(--sub-color)] opacity-50">{{ t('friends.no_replay') }}</span>
                                <button v-if="friend.friendship_id" type="button" :disabled="busy" @click="remove(friend.friendship_id)"
                                        class="text-[var(--sub-color)] hover:text-[var(--error-color)] disabled:opacity-40 transition-colors">
                                    {{ t('friends.unfriend') }}
                                </button>
                            </span>
                        </div>
                    </div>
                    <p v-else class="font-mono text-xs text-[var(--sub-color)]">{{ t('friends.empty') }}</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
