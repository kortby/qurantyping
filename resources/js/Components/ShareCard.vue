<script setup>
import { ref } from 'vue';
import { useSettings } from '../useSettings';

const props = defineProps({
    wpm: { type: Number, required: true },
    accuracy: { type: [Number, String], required: true },
    surahArabic: { type: String, default: '' },
    surahEnglish: { type: String, default: '' },
    surahNumber: { type: [Number, String], default: null },
    startAyah: { type: [Number, String], default: null },
    endAyah: { type: [Number, String], default: null },
    streak: { type: Number, default: 0 },
});

const { t } = useSettings();
const busy = ref(false);

/* ---- shareable image (hand-drawn, no dependency — mirrors Certificate.vue) ---- */

const drawCard = (ctx, w, h) => {
    // Fixed "paper" palette so the export reads the same in any theme.
    const paper = '#f6f1e7', ink = '#1f1c17', gold = '#9a6f28', sub = '#8c8371';
    const mid = w / 2;

    ctx.fillStyle = paper;
    ctx.fillRect(0, 0, w, h);

    // double gold rule
    ctx.strokeStyle = gold;
    ctx.lineWidth = 3;
    ctx.strokeRect(54, 54, w - 108, h - 108);
    ctx.lineWidth = 8;
    ctx.strokeRect(74, 74, w - 148, h - 148);

    // corner marks
    const m = 54;
    ctx.lineWidth = 3;
    [[110, 110, 1, 1], [w - 110, 110, -1, 1], [110, h - 110, 1, -1], [w - 110, h - 110, -1, -1]].forEach(([x, y, dx, dy]) => {
        ctx.beginPath();
        ctx.moveTo(x + dx * m, y);
        ctx.lineTo(x, y);
        ctx.lineTo(x, y + dy * m);
        ctx.stroke();
    });

    ctx.textAlign = 'center';

    // wordmark
    ctx.fillStyle = sub;
    ctx.font = "500 34px 'IBM Plex Sans', system-ui, sans-serif";
    ctx.fillText('Q U R A N T Y P I N G', mid, 250);

    // surah
    ctx.fillStyle = ink;
    ctx.font = "700 168px 'Noto Naskh Arabic', serif";
    ctx.direction = 'rtl';
    ctx.fillText(props.surahArabic || '—', mid, 500);
    ctx.direction = 'ltr';

    if (props.surahEnglish) {
        ctx.font = "600 58px 'IBM Plex Sans', system-ui, sans-serif";
        ctx.fillText(`Surah ${props.surahEnglish}`, mid, 610);
    }

    if (props.surahNumber && props.startAyah && props.endAyah) {
        ctx.fillStyle = gold;
        ctx.font = "400 40px 'IBM Plex Mono', monospace";
        ctx.fillText(`${props.surahNumber}:${props.startAyah}–${props.endAyah}`, mid, props.surahEnglish ? 686 : 620);
    }

    // hero stats
    const statY = 1040;
    const col = w / 4;
    const stat = (x, value, label) => {
        ctx.fillStyle = gold;
        ctx.font = "700 210px 'IBM Plex Sans', system-ui, sans-serif";
        ctx.fillText(String(value), x, statY);
        ctx.fillStyle = sub;
        ctx.font = "500 34px 'IBM Plex Mono', monospace";
        ctx.fillText(label, x, statY + 74);
    };
    stat(col, props.wpm, 'W P M');
    stat(mid + col, `${Math.round(Number(props.accuracy))}%`, 'A C C U R A C Y');

    // divider
    ctx.strokeStyle = gold;
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(mid - 180, statY + 150);
    ctx.lineTo(mid + 180, statY + 150);
    ctx.stroke();

    // streak
    if (props.streak > 0) {
        const label = props.streak === 1 ? '1 DAY STREAK' : `${props.streak} DAY STREAK`;
        ctx.font = "500 44px 'IBM Plex Mono', monospace";
        const pad = 44;
        const tw = ctx.measureText(label).width;
        const bw = tw + pad * 2, bh = 96, bx = mid - bw / 2, by = statY + 226;
        ctx.strokeStyle = gold;
        ctx.lineWidth = 3;
        ctx.strokeRect(bx, by, bw, bh);
        ctx.fillStyle = gold;
        ctx.textBaseline = 'middle';
        ctx.fillText(label, mid, by + bh / 2 + 2);
        ctx.textBaseline = 'alphabetic';
    }

    // seal
    ctx.strokeStyle = gold;
    ctx.lineWidth = 4;
    const cx = mid, cy = h - 330, r = 58;
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(cx, cy, r - 13, 0, Math.PI * 2);
    ctx.stroke();
    for (let i = 0; i < 8; i++) {
        const a = (i / 8) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(cx + Math.cos(a) * (r - 13), cy + Math.sin(a) * (r - 13));
        ctx.lineTo(cx + Math.cos(a) * (r + 11), cy + Math.sin(a) * (r + 11));
        ctx.stroke();
    }

    // url
    ctx.fillStyle = ink;
    ctx.font = "500 44px 'IBM Plex Sans', system-ui, sans-serif";
    ctx.fillText('qurantyping.com', mid, h - 190);
};

const saveImage = async () => {
    if (busy.value) {
        return;
    }
    busy.value = true;
    try {
        await Promise.all([
            document.fonts.load("700 168px 'Noto Naskh Arabic'"),
            document.fonts.load("700 210px 'IBM Plex Sans'"),
            document.fonts.load("500 44px 'IBM Plex Mono'"),
        ]).catch(() => {});
        await document.fonts.ready;

        const scale = 2;
        const W = 1080, H = 1920;
        const canvas = document.createElement('canvas');
        canvas.width = W * scale;
        canvas.height = H * scale;
        const ctx = canvas.getContext('2d');
        ctx.scale(scale, scale);
        drawCard(ctx, W, H);

        const blob = await new Promise((res) => canvas.toBlob(res, 'image/png'));
        const file = new File([blob], `qurantyping-${props.surahNumber || 'result'}.png`, { type: 'image/png' });
        const title = props.surahEnglish
            ? `Surah ${props.surahEnglish} — ${props.wpm} wpm, ${Math.round(Number(props.accuracy))}%`
            : `${props.wpm} wpm on QuranTyping`;

        if (navigator.canShare?.({ files: [file] })) {
            await navigator.share({ files: [file], title });
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
    <button
        type="button"
        @click="saveImage"
        :disabled="busy"
        class="min-h-[44px] px-6 border border-[var(--border-color)] font-cinzel text-xs uppercase tracking-[0.12em] text-[var(--sub-color)] hover:text-[var(--main-color)] hover:border-[var(--caret-color)] transition-colors disabled:opacity-50"
    >
        {{ busy ? t('share.preparing') : t('share.button') }}
    </button>
</template>
