<script setup>
import { ref, computed } from 'vue';
import { useSettings } from '../useSettings';

const props = defineProps({
    url: { type: String, required: true },
    title: { type: String, default: '' },
    text: { type: String, default: '' },
});

const { t } = useSettings();
const copied = ref(false);

const tagged = (medium) => {
    try {
        const u = new URL(props.url, typeof window !== 'undefined' ? window.location.origin : undefined);
        u.searchParams.set('utm_source', 'cert');
        u.searchParams.set('utm_medium', medium);
        u.searchParams.set('utm_campaign', 'share');
        return u.toString();
    } catch {
        return props.url;
    }
};

const shareText = computed(() => props.text || props.title);

const networks = computed(() => [
    {
        key: 'x',
        label: 'X',
        href: `https://x.com/intent/tweet?text=${encodeURIComponent(shareText.value)}&url=${encodeURIComponent(tagged('x'))}`,
    },
    {
        key: 'whatsapp',
        label: 'WhatsApp',
        href: `https://wa.me/?text=${encodeURIComponent(`${shareText.value} ${tagged('whatsapp')}`)}`,
    },
    {
        key: 'facebook',
        label: 'Facebook',
        href: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(tagged('facebook'))}`,
    },
    {
        key: 'linkedin',
        label: 'LinkedIn',
        href: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(tagged('linkedin'))}`,
    },
    {
        key: 'telegram',
        label: 'Telegram',
        href: `https://t.me/share/url?url=${encodeURIComponent(tagged('telegram'))}&text=${encodeURIComponent(shareText.value)}`,
    },
]);

const canNativeShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function';

const nativeShare = async () => {
    try {
        await navigator.share({ title: props.title, text: shareText.value, url: tagged('native') });
    } catch {
        // sheet dismissed — nothing to do
    }
};

const copyLink = async () => {
    const link = tagged('copy');
    let ok = false;

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(link);
            ok = true;
        }
    } catch {
        ok = false;
    }

    if (!ok) {
        const ta = document.createElement('textarea');
        ta.value = link;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            ok = document.execCommand('copy');
        } catch {
            ok = false;
        }
        document.body.removeChild(ta);
    }

    if (ok) {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    }
};

const chip = 'min-h-[36px] px-4 inline-flex items-center border border-[var(--border-color)] font-cinzel text-[11px] uppercase tracking-[0.12em] text-[var(--sub-color)] hover:text-[var(--main-color)] hover:border-[var(--caret-color)] transition-colors';
</script>

<template>
    <div class="flex flex-wrap items-center justify-center gap-2">
        <button v-if="canNativeShare" type="button" @click="nativeShare"
                :class="[chip, 'border-[var(--caret-color)] text-[var(--caret-color)]']">
            {{ t('certificates.share') }}
        </button>

        <a v-for="n in networks" :key="n.key" :href="n.href" target="_blank" rel="noopener noreferrer" :class="chip">
            {{ n.label }}
        </a>

        <button type="button" @click="copyLink" :class="chip">
            {{ copied ? t('certificates.link_copied') : t('certificates.share_link') }}
        </button>
    </div>
</template>
