<script setup>
import { computed, ref } from 'vue';
import ShareBar from './ShareBar.vue';
import { useSettings } from '../useSettings';

const props = defineProps({
    cert: { type: Object, required: true },
});

const { t } = useSettings();
const busy = ref(false);

const formatDate = (d) => new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });

const shareTitle = computed(
    () => `${props.cert.holder} completed Surah ${props.cert.surah_name_english} · QuranTyping`,
);
const shareText = computed(
    () => `I completed Surah ${props.cert.surah_name_english} on QuranTyping — ${props.cert.accuracy}% accuracy. Type the Qur'an, letter by letter:`,
);

/* ---- shareable image (hand-drawn, no dependency) ---- */

const spacedText = (ctx, text, x, y, spacing) => {
    ctx.letterSpacing = `${spacing}px`;
    ctx.fillText(text, x + spacing / 2, y);
    ctx.letterSpacing = '0px';
};

const cornerFlourish = (ctx, x, y, dx, dy, gold) => {
    const r = 26;
    ctx.strokeStyle = gold;
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.arc(x + dx * r, y + dy * r, r, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(x + dx * r, y + dy * r, r - 7, 0, Math.PI * 2);
    ctx.stroke();
    ctx.fillStyle = gold;
    ctx.beginPath();
    ctx.arc(x, y, 3.5, 0, Math.PI * 2);
    ctx.fill();
};

const diamond = (ctx, x, y, s, fill) => {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(Math.PI / 4);
    ctx.fillStyle = fill;
    ctx.fillRect(-s / 2, -s / 2, s, s);
    ctx.restore();
};

const drawCertificate = (ctx, w, h) => {
    const c = props.cert;
    // Fixed "paper" palette so the export reads the same in any theme.
    const paper = '#f7f2e7', ink = '#211d17', gold = '#9a6f28', goldSoft = '#c6a15a', sub = '#8a8069';
    const mid = w / 2;

    ctx.fillStyle = paper;
    ctx.fillRect(0, 0, w, h);

    // frame: hairline · heavy rule · inner hairline
    ctx.strokeStyle = gold;
    ctx.lineWidth = 1.5;
    ctx.strokeRect(40, 40, w - 80, h - 80);
    ctx.lineWidth = 4;
    ctx.strokeRect(54, 54, w - 108, h - 108);
    ctx.strokeStyle = goldSoft;
    ctx.lineWidth = 1;
    ctx.strokeRect(72, 72, w - 144, h - 144);

    // corner rosettes, just inside the inner hairline
    cornerFlourish(ctx, 92, 92, 1, 1, gold);
    cornerFlourish(ctx, w - 92, 92, -1, 1, gold);
    cornerFlourish(ctx, 92, h - 92, 1, -1, gold);
    cornerFlourish(ctx, w - 92, h - 92, -1, -1, gold);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';

    // eyebrow
    ctx.fillStyle = sub;
    ctx.font = "500 23px 'IBM Plex Sans', system-ui, sans-serif";
    spacedText(ctx, 'CERTIFICATE OF COMPLETION', mid, 124, 8);

    // divider under the eyebrow
    ctx.strokeStyle = gold;
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(mid - 168, 148);
    ctx.lineTo(mid - 46, 148);
    ctx.moveTo(mid + 46, 148);
    ctx.lineTo(mid + 168, 148);
    ctx.stroke();
    diamond(ctx, mid, 148, 9, gold);

    // hero — the surah name in Arabic, shrunk to fit the safe width
    let size = 116;
    ctx.direction = 'rtl';
    do {
        ctx.font = `700 ${size}px 'Noto Naskh Arabic', serif`;
        size -= 6;
    } while (ctx.measureText(c.surah_name_arabic).width > w - 500 && size > 56);
    ctx.fillStyle = ink;
    ctx.fillText(c.surah_name_arabic, mid, 284);
    ctx.direction = 'ltr';

    // short rule
    ctx.strokeStyle = goldSoft;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(mid - 44, 324);
    ctx.lineTo(mid + 44, 324);
    ctx.stroke();

    // english title
    ctx.fillStyle = ink;
    ctx.font = "600 32px 'IBM Plex Sans', system-ui, sans-serif";
    spacedText(ctx, `SURAH ${c.surah_name_english.toUpperCase()}`, mid, 368, 5);

    // stats
    ctx.fillStyle = sub;
    ctx.font = "400 21px 'IBM Plex Mono', monospace";
    spacedText(ctx, `${c.ayah_count} AYAHS   ·   ${c.accuracy}% ACCURACY`, mid, 410, 2);

    // awarded to
    ctx.fillStyle = sub;
    ctx.font = "500 16px 'IBM Plex Sans', system-ui, sans-serif";
    spacedText(ctx, 'AWARDED TO', mid, 470, 6);

    ctx.fillStyle = ink;
    ctx.font = "500 33px 'IBM Plex Sans', system-ui, sans-serif";
    ctx.fillText(c.holder, mid, 508);

    // signature rule beneath the holder
    ctx.strokeStyle = goldSoft;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(mid - 140, 524);
    ctx.lineTo(mid + 140, 524);
    ctx.stroke();

    ctx.fillStyle = sub;
    ctx.font = "400 18px 'IBM Plex Mono', monospace";
    ctx.fillText(`${formatDate(c.issued_at)}  ·  qurantyping.com`, mid, 548);

    // wax-style seal, in the right quiet zone beside the title
    const cx = w - 152, cy = 356, r = 40;
    ctx.strokeStyle = gold;
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.stroke();
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.arc(cx, cy, r - 9, 0, Math.PI * 2);
    ctx.stroke();
    for (let i = 0; i < 12; i++) {
        const a = (i / 12) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(cx + Math.cos(a) * (r - 9), cy + Math.sin(a) * (r - 9));
        ctx.lineTo(cx + Math.cos(a) * (r + 7), cy + Math.sin(a) * (r + 7));
        ctx.stroke();
    }
    diamond(ctx, cx, cy, 12, gold);
};

const saveImage = async () => {
    if (busy.value) {
        return;
    }
    busy.value = true;
    try {
        await Promise.all([
            document.fonts.load("700 132px 'Noto Naskh Arabic'"),
            document.fonts.load("600 33px 'IBM Plex Sans'"),
            document.fonts.load("400 21px 'IBM Plex Mono'"),
        ]).catch(() => {});
        await document.fonts.ready;

        const scale = 2;
        const W = 1200, H = 630;
        const canvas = document.createElement('canvas');
        canvas.width = W * scale;
        canvas.height = H * scale;
        const ctx = canvas.getContext('2d');
        ctx.scale(scale, scale);
        drawCertificate(ctx, W, H);

        const blob = await new Promise((res) => canvas.toBlob(res, 'image/png'));
        const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        const file = new File([blob], `quran-typing-surah-${props.cert.surah_number}-${stamp}.png`, { type: 'image/png' });

        if (navigator.canShare?.({ files: [file] })) {
            await navigator.share({ files: [file], title: `Surah ${props.cert.surah_name_english}` });
        } else {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = file.name;
            a.click();
            URL.revokeObjectURL(url);
        }
    } catch (e) {
        // user cancelled the share sheet, or something unsupported — ignore
    } finally {
        busy.value = false;
    }
};
</script>

<template>
    <figure class="jadwal relative flex flex-col items-center text-center gap-3 py-8 px-6">
        <figcaption class="font-mono text-[10px] uppercase tracking-[0.3em] text-[var(--sub-color)]">
            {{ t('certificates.heading') }}
        </figcaption>

        <p class="text-4xl sm:text-5xl text-[var(--main-color)] leading-tight" dir="rtl" style="font-family: 'Noto Naskh Arabic', serif;">
            {{ cert.surah_name_arabic }}
        </p>
        <p class="font-cinzel text-lg text-[var(--main-color)]">{{ t('certificates.surah_prefix') }} {{ cert.surah_name_english }}</p>

        <p class="font-mono text-xs text-[var(--sub-color)]">
            {{ t('certificates.detail').replace('{count}', cert.ayah_count).replace('{acc}', cert.accuracy) }}
        </p>

        <span aria-hidden="true" class="my-1 inline-flex h-8 w-8 items-center justify-center rounded-full border border-[var(--rule-color)]">
            <span class="h-4 w-4 rounded-full border border-[var(--rule-color)]"></span>
        </span>

        <p class="text-sm text-[var(--main-color)]">{{ cert.holder }}</p>
        <p class="font-mono text-[10px] text-[var(--sub-color)]">{{ formatDate(cert.issued_at) }} · QuranTyping</p>

        <div class="mt-2 flex flex-col items-center gap-3">
            <button
                type="button"
                @click="saveImage"
                :disabled="busy"
                class="min-h-[36px] px-4 border border-[var(--border-color)] font-cinzel text-[11px] uppercase tracking-[0.12em] text-[var(--sub-color)] hover:text-[var(--main-color)] hover:border-[var(--caret-color)] transition-colors disabled:opacity-50"
            >
                {{ busy ? t('certificates.preparing') : t('certificates.save_image') }}
            </button>
            <ShareBar v-if="cert.share_url" :url="cert.share_url" :title="shareTitle" :text="shareText" />
        </div>
    </figure>
</template>
