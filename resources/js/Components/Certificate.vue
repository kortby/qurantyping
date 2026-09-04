<script setup>
import { ref } from 'vue';

const props = defineProps({
    cert: { type: Object, required: true },
});

const busy = ref(false);

const formatDate = (d) => new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });

/* ---- shareable image (hand-drawn, no dependency) ---- */

const drawCertificate = (ctx, w, h) => {
    const c = props.cert;
    // Fixed "paper" palette so the export reads the same in any theme.
    const paper = '#f6f1e7', ink = '#1f1c17', gold = '#9a6f28', sub = '#8c8371';

    ctx.fillStyle = paper;
    ctx.fillRect(0, 0, w, h);

    // double gold rule
    ctx.strokeStyle = gold;
    ctx.lineWidth = 2;
    ctx.strokeRect(48, 48, w - 96, h - 96);
    ctx.lineWidth = 6;
    ctx.strokeRect(64, 64, w - 128, h - 128);

    // corner marks
    const m = 40;
    ctx.lineWidth = 2;
    [[92, 92, 1, 1], [w - 92, 92, -1, 1], [92, h - 92, 1, -1], [w - 92, h - 92, -1, -1]].forEach(([x, y, dx, dy]) => {
        ctx.beginPath();
        ctx.moveTo(x + dx * m, y);
        ctx.lineTo(x, y);
        ctx.lineTo(x, y + dy * m);
        ctx.stroke();
    });

    ctx.textAlign = 'center';

    ctx.fillStyle = sub;
    ctx.font = "500 26px 'IBM Plex Sans', system-ui, sans-serif";
    ctx.fillText('C E R T I F I C A T E   O F   C O M P L E T I O N', w / 2, 170);

    ctx.fillStyle = ink;
    ctx.font = "700 150px 'Noto Naskh Arabic', serif";
    ctx.direction = 'rtl';
    ctx.fillText(c.surah_name_arabic, w / 2, h / 2 - 20);
    ctx.direction = 'ltr';

    ctx.font = "600 52px 'IBM Plex Sans', system-ui, sans-serif";
    ctx.fillText(`Surah ${c.surah_name_english}`, w / 2, h / 2 + 80);

    ctx.fillStyle = sub;
    ctx.font = "400 30px 'IBM Plex Mono', monospace";
    ctx.fillText(`${c.ayah_count} ayahs  ·  ${c.accuracy}% accuracy or better`, w / 2, h / 2 + 150);

    // seal
    ctx.strokeStyle = gold;
    ctx.lineWidth = 3;
    const cx = w / 2, cy = h - 210, r = 46;
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(cx, cy, r - 10, 0, Math.PI * 2);
    ctx.stroke();
    for (let i = 0; i < 8; i++) {
        const a = (i / 8) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(cx + Math.cos(a) * (r - 10), cy + Math.sin(a) * (r - 10));
        ctx.lineTo(cx + Math.cos(a) * (r + 8), cy + Math.sin(a) * (r + 8));
        ctx.stroke();
    }

    ctx.fillStyle = ink;
    ctx.font = "500 34px 'IBM Plex Sans', system-ui, sans-serif";
    ctx.fillText(c.holder, w / 2, h - 110);
    ctx.fillStyle = sub;
    ctx.font = "400 24px 'IBM Plex Mono', monospace";
    ctx.fillText(`${formatDate(c.issued_at)}  ·  QuranTyping`, w / 2, h - 72);
};

const saveImage = async () => {
    if (busy.value) return;
    busy.value = true;
    try {
        await Promise.all([
            document.fonts.load("700 150px 'Noto Naskh Arabic'"),
            document.fonts.load("600 52px 'IBM Plex Sans'"),
            document.fonts.load("400 30px 'IBM Plex Mono'"),
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
        const file = new File([blob], `quran-typing-surah-${props.cert.surah_number}.png`, { type: 'image/png' });

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
            Certificate of completion
        </figcaption>

        <p class="text-4xl sm:text-5xl text-[var(--main-color)] leading-tight" dir="rtl" style="font-family: 'Noto Naskh Arabic', serif;">
            {{ cert.surah_name_arabic }}
        </p>
        <p class="font-cinzel text-lg text-[var(--main-color)]">Surah {{ cert.surah_name_english }}</p>

        <p class="font-mono text-xs text-[var(--sub-color)]">
            {{ cert.ayah_count }} ayahs · {{ cert.accuracy }}% accuracy or better
        </p>

        <span aria-hidden="true" class="my-1 inline-flex h-8 w-8 items-center justify-center rounded-full border border-[var(--rule-color)]">
            <span class="h-4 w-4 rounded-full border border-[var(--rule-color)]"></span>
        </span>

        <p class="text-sm text-[var(--main-color)]">{{ cert.holder }}</p>
        <p class="font-mono text-[10px] text-[var(--sub-color)]">{{ formatDate(cert.issued_at) }} · QuranTyping</p>

        <button
            type="button"
            @click="saveImage"
            :disabled="busy"
            class="mt-2 min-h-[36px] px-4 border border-[var(--border-color)] font-cinzel text-[11px] uppercase tracking-[0.12em] text-[var(--sub-color)] hover:text-[var(--main-color)] hover:border-[var(--caret-color)] transition-colors disabled:opacity-50"
        >
            {{ busy ? 'Preparing…' : 'Save image' }}
        </button>
    </figure>
</template>
