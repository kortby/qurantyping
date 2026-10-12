import { describe, it, expect } from 'vitest';
import {
    normalizeDisplayText,
    buildVisualMapping,
    normalizeForComparison,
} from '../../resources/js/lib/arabicText.js';
import { logicTokens, normalize } from '../../resources/js/composables/useTypingScore.js';

// "ٱلْـَٔاخِرَةِ" — the word al-ākhira as written in the Uthmani rasm of
// Ar-Ra'd 13:26 (and 470 other ayat): alef-wasla, lam, sukun, TATWEEL, hamza
// above, fatha, alef, kha, kasra, ra, fatha, ta-marbuta, kasra. The sukun sits
// on the lam while the fatha+hamza sit on the kashida, so once the tatweel is
// removed the two marks collapse onto one base in a non-canonical order.
const AL_AKHIRA = 'ٱلْـَٔاخِرَةِ';

/**
 * Faithful model of the typing engine's completion check: an ideal typist keys
 * the displayed logic glyphs in order; the browser re-normalizes the resulting
 * input to NFC (TypingTest.vue:739). The test then completes iff every typed
 * character matches its expected counterpart under normalizeForComparison.
 */
function isCompletable(raw, usePunctuation) {
    const display = normalizeDisplayText(raw);
    const { logicText } = buildVisualMapping(display);
    const expected = logicText.split('');
    const typed = logicText.normalize('NFC').split(''); // what the browser hands back
    if (typed.length !== expected.length) {
        return false;
    }
    return expected.every(
        (ch, i) => normalizeForComparison(typed[i], usePunctuation) === normalizeForComparison(ch, usePunctuation),
    );
}

describe('normalizeDisplayText', () => {
    it('strips the tatweel and leaves the text in canonical NFC order', () => {
        const display = normalizeDisplayText(AL_AKHIRA);
        expect(display).not.toContain('ـ'); // no tatweel
        expect(display).toBe(display.normalize('NFC')); // already canonical — the fix
    });

    it('is idempotent', () => {
        const once = normalizeDisplayText(AL_AKHIRA);
        expect(normalizeDisplayText(once)).toBe(once);
    });
});

describe('buildVisualMapping', () => {
    it('drops the unkeyable hamza-above (U+0654) from the logic sequence', () => {
        const { logicText } = buildVisualMapping(normalizeDisplayText(AL_AKHIRA));
        expect(logicText).not.toContain('ٔ');
    });

    it('produces a logic sequence already in canonical order', () => {
        const { logicText } = buildVisualMapping(normalizeDisplayText(AL_AKHIRA));
        // Regression guard for the Ar-Ra'd 13:26 bug: before the re-NFC fix the
        // logic text kept sukun before fatha, an order the typist's NFC'd input
        // could never reproduce, so the daily challenge could never complete.
        expect(logicText).toBe(logicText.normalize('NFC'));
    });
});

describe('typing completion (Harakat on)', () => {
    it('lets al-ākhira (Ar-Ra\'d 13:26) be completed', () => {
        expect(isCompletable(AL_AKHIRA, true)).toBe(true);
    });

    it('lets a plain ayah with a dagger alif be completed', () => {
        // "ٱلرَّحْمَـٰنِ" (1:3) — contains a dagger alif (U+0670) on the kashida.
        const rahman = 'ٱلرَّحْمَـٰنِ';
        expect(isCompletable(rahman, true)).toBe(true);
    });
});

/**
 * Race-room model (useTypingScore): an ideal racer keys the token glyphs; the
 * browser re-normalizes the input to NFC. The race completes iff every typed
 * character matches its token under normalize().
 */
function raceCompletable(raw, keepHarakat) {
    const tokens = logicTokens(raw);
    const expected = tokens.map((t) => t.ch);
    const typed = expected.join('').normalize('NFC').split('');
    if (typed.length !== expected.length) {
        return false;
    }
    return expected.every((ch, i) => normalize(typed[i], keepHarakat) === normalize(ch, keepHarakat));
}

describe('race mode (useTypingScore)', () => {
    it('drops the tatweel instead of leaving an untypable empty token', () => {
        const tokens = logicTokens(AL_AKHIRA);
        expect(tokens.every((t) => t.ch !== 'ـ')).toBe(true);
        // A token whose normalized form is empty could never be matched by a keystroke.
        expect(tokens.every((t) => t.brk || normalize(t.ch, true) !== '')).toBe(true);
    });

    it('lets al-ākhira (Ar-Ra\'d 13:26) be completed in a race', () => {
        expect(raceCompletable(AL_AKHIRA, true)).toBe(true);
    });
});
