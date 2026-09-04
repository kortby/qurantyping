<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSettings } from '../../useSettings';

defineProps({
    config: Object,
});

const { t } = useSettings();

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('contest.inactive_title')" />
    <div class="flex flex-col items-center justify-center min-h-[60vh] py-20">
        <div class="bg-[var(--panel-color)] border border-[var(--border-color)] px-10 py-12 text-center max-w-2xl">
            <h1 class="text-3xl font-cinzel font-bold text-[var(--main-color)] mb-4">{{ t('contest.inactive_title') }}</h1>
            <p class="text-[var(--sub-color)] mb-8 leading-relaxed">
                {{ t('contest.inactive_desc') }}
            </p>
            <Link :href="route('dashboard')" class="bg-[var(--caret-color)] text-[var(--bg-color)] px-6 py-3 font-bold hover:opacity-90 transition-opacity uppercase tracking-wider">
                {{ t('contest.back_to_dashboard') }}
            </Link>
        </div>
    </div>
</template>
