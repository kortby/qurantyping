import { computed, ref, unref } from 'vue';

/**
 * Minimal typing-scoring logic for the race room. A trimmed cousin of the engine
 * in Pages/TypingTest.vue: no tashkeel mode, no hifz, no clusters UI — just the
 * pure comparison + live WPM/accuracy/progress.
 */

const TASHKEEL = /[ؐ-ؚـً-ٰٟۖ-ۭ۝]/g;
const AYAH_SEPARATOR = / ?۝[٠-٩]+ ?/g;

export function normalize(text) {
    if (!text) return '';
    return text
        .normalize('NFC')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/[ۀة]/g, 'ه')
        .replace(/[ىي]/g, 'ي')
        .replace(TASHKEEL, '')
        .trim();
}

/** Collapse the "۝<digits>" ayah markers to a single space, matching the display text. */
export function toLogicText(displayText) {
    return (displayText || '').normalize('NFC').replace(AYAH_SEPARATOR, ' ').replace(/\s+/g, ' ').trim();
}

export function useTypingScore(sourceTextRef, userInputRef, startedAtRef) {
    const sourceText = computed(() => toLogicText(unref(sourceTextRef)));
    const sourceChars = computed(() => sourceText.value.split(''));

    const typed = computed(() => (unref(userInputRef) || '').split(''));

    const correctCount = computed(() => {
        let n = 0;
        const src = sourceChars.value;
        const inp = typed.value;
        for (let i = 0; i < inp.length && i < src.length; i++) {
            if (normalize(inp[i]) === normalize(src[i])) n++;
        }
        return n;
    });

    const firstErrorIndex = computed(() => {
        const src = sourceChars.value;
        const inp = typed.value;
        for (let i = 0; i < inp.length; i++) {
            if (normalize(inp[i]) !== normalize(src[i])) return i;
        }
        return -1;
    });

    const progress = computed(() => {
        if (sourceChars.value.length === 0) return 0;
        // Count the leading run of correct characters.
        const src = sourceChars.value;
        const inp = typed.value;
        let run = 0;
        for (let i = 0; i < inp.length && i < src.length; i++) {
            if (normalize(inp[i]) === normalize(src[i])) run++;
            else break;
        }
        return Math.min(1, run / src.length);
    });

    const elapsedMs = computed(() => {
        const start = unref(startedAtRef);
        return start ? Math.max(1, Date.now() - start) : 0;
    });

    const wpm = computed(() => {
        if (!elapsedMs.value) return 0;
        return Math.round((typed.value.length / 5) / (elapsedMs.value / 60000));
    });

    const accuracy = computed(() => {
        if (typed.value.length === 0) return 100;
        return Math.round((correctCount.value / typed.value.length) * 100);
    });

    const isComplete = computed(
        () => typed.value.length >= sourceChars.value.length && firstErrorIndex.value === -1,
    );

    return { sourceText, sourceChars, correctCount, firstErrorIndex, progress, wpm, accuracy, isComplete, elapsedMs };
}

export function useRaceInput() {
    return ref('');
}
