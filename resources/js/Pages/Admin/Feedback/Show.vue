<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    feedback: Object,
});

const page = usePage();

const confirmingDeletion = ref(false);

const toggleHandled = () => {
    router.patch(`/admin/feedback/${props.feedback.id}`, {
        handled: !props.feedback.handled_at,
    }, { preserveScroll: true });
};

const destroy = () => {
    router.delete(`/admin/feedback/${props.feedback.id}`);
};

const formatDate = (value) => value ? new Date(value).toLocaleString() : '—';

const typeStyles = {
    bug: 'border-[var(--error-color)]/40 text-[var(--error-color)]',
    suggestion: 'border-[var(--lapis-color)]/40 text-[var(--lapis-color)]',
    other: 'border-[var(--border-color)] text-[var(--sub-color)]',
};
</script>

<template>
    <Head>
        <title>Feedback #{{ feedback.id }} - Admin | QuranTyping</title>
    </Head>

    <AppLayout>
        <div class="py-8 animate-fade-in min-h-[80vh]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <Link href="/admin/feedback" class="inline-flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)] hover:text-[var(--caret-color)] transition-colors">
                    ← Back to feedback
                </Link>

                <div v-if="page.props.flash.message" class="border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 font-mono text-xs text-emerald-500">
                    {{ page.props.flash.message }}
                </div>

                <!-- Header -->
                <div class="bg-[var(--panel-color)] border border-[var(--border-color)] p-6 space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-[9px] uppercase tracking-[0.2em] px-2 py-0.5 border" :class="typeStyles[feedback.type] || typeStyles.other">
                            {{ feedback.type }}
                        </span>
                        <span
                            class="text-[9px] uppercase tracking-[0.2em] px-2 py-0.5 border"
                            :class="feedback.handled_at ? 'border-emerald-500/40 text-emerald-500' : 'border-[var(--caret-color)]/40 text-[var(--caret-color)]'"
                        >
                            {{ feedback.handled_at ? 'Handled' : 'Open' }}
                        </span>
                        <span class="font-mono text-[10px] uppercase tracking-widest opacity-40 ml-auto">#{{ feedback.id }}</span>
                    </div>

                    <div class="font-mono text-xs text-[var(--sub-color)] space-y-1">
                        <p>
                            From
                            <template v-if="feedback.user">
                                <Link :href="`/admin/users/${feedback.user.id}`" class="text-[var(--main-color)] hover:text-[var(--caret-color)] transition-colors">
                                    {{ feedback.user.name }}
                                </Link>
                                &lt;{{ feedback.user.email }}&gt;
                            </template>
                            <span v-else class="opacity-40">a deleted user</span>
                        </p>
                        <p>Received {{ formatDate(feedback.created_at) }}</p>
                        <p v-if="feedback.handled_at">Handled {{ formatDate(feedback.handled_at) }}</p>
                    </div>
                </div>

                <!-- Message -->
                <div class="bg-[var(--panel-color)] border border-[var(--border-color)] p-6">
                    <h2 class="font-cinzel text-sm uppercase tracking-[0.3em] text-[var(--caret-color)] mb-4">Message</h2>
                    <p class="whitespace-pre-wrap font-mono text-sm leading-relaxed text-[var(--main-color)]">{{ feedback.message }}</p>
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap items-center gap-3">
                    <PrimaryButton v-if="!feedback.handled_at" type="button" @click="toggleHandled">Mark as handled</PrimaryButton>
                    <SecondaryButton v-else type="button" @click="toggleHandled">Reopen</SecondaryButton>
                    <DangerButton type="button" @click="confirmingDeletion = true">Delete</DangerButton>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>Delete feedback #{{ feedback.id }}</template>
            <template #content>
                This permanently removes the message. This cannot be undone.
            </template>
            <template #footer>
                <SecondaryButton @click="confirmingDeletion = false">Cancel</SecondaryButton>
                <DangerButton class="ms-3" @click="destroy">Delete</DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
