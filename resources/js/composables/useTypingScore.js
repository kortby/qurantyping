import { computed, ref, unref } from 'vue';

/**
 * Minimal typing-scoring logic for the race room. A trimmed cousin of the engine
 * in Pages/TypingTest.vue: no tashkeel mode, no hifz — just the pure comparison
 * plus live WPM/accuracy/progress and a token list for rendering.
 */

const TASHKEEL = /[ؐ-ؚـً-ٰٟۖ-ۭ۝]/g;
const AYAH_SEPARATOR = / ?۝[٠-٩]+ ?/g;
const BREAK_SENTINEL = '␞';

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

/**
 * The passage as tokens, one per typed-against logic character. `brk: true`
 * marks the single space that stands in for an "۝<digits>" ayah divider, so the
 * UI can draw a roundel there instead of a blank.
 */
export function logicTokens(displayText) {
    const marked = (displayText || '')
        .normalize('NFC')
        .replace(AYAH_SEPARATOR, BREAK_SENTINEL)
        .replace(/[ \t\n]+/g, ' ')
        .replace(/^[\s␞]+|\s+$/g, '');

    return [...marked].map((ch) =>
        ch === BREAK_SENTINEL ? { ch: ' ', brk: true } : { ch, brk: false },
    );
}

export function useTypingScore(sourceTextRef, userInputRef, startedAtRef) {
    const tokens = computed(() => logicTokens(unref(sourceTextRef)));
    const sourceChars = computed(() => tokens.value.map((t) => t.ch));
    const sourceText = computed(() => sourceChars.value.join(''));

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

    const correctRun = computed(() => {
        const src = sourceChars.value;
        const inp = typed.value;
        let run = 0;
        for (let i = 0; i < inp.length && i < src.length; i++) {
            if (normalize(inp[i]) === normalize(src[i])) run++;
            else break;
        }
        return run;
    });

    const progress = computed(() =>
        sourceChars.value.length === 0 ? 0 : Math.min(1, correctRun.value / sourceChars.value.length),
    );

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

    /** Per-token render status for the passage display. */
    const charStates = computed(() => {
        const inp = typed.value;
        return tokens.value.map((tok, i) => {
            let status = 'untyped';
            if (i < inp.length) {
                status = normalize(inp[i]) === normalize(tok.ch) ? 'correct' : 'incorrect';
            } else if (i === inp.length) {
                status = 'active';
            }
            return { ...tok, status };
        });
    });

    return {
        tokens,
        sourceText,
        sourceChars,
        charStates,
        correctCount,
        correctRun,
        firstErrorIndex,
        progress,
        wpm,
        accuracy,
        isComplete,
        elapsedMs,
    };
}

export function useRaceInput() {
    return ref('');
}
