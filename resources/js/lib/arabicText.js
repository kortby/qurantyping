/**
 * Pure Arabic-text helpers shared by the typing engine in Pages/TypingTest.vue.
 * Extracted so the comparison logic can be unit-tested without mounting the
 * component. See tests/js/arabicText.test.js.
 */

// Uthmani rasm annotations (maddah/hamza above or below a carrier letter, e.g.
// the ٓ in "إِنِّىٓ" at 71:9) have no key on any Arabic keyboard layout — they
// must stay visible in the rendered text but never require a keystroke.
export const UNKEYABLE_MARK = /[ٕٓٔ]/;

/**
 * The passage text as rendered to the typist: NFC, with the Tatweel (aesthetic
 * stretch) removed, then re-normalized to NFC.
 *
 * The second NFC is load-bearing. Tatweel (U+0640) is a zero-combining-class
 * base, so it can sit between two marks that belong to different carriers —
 * e.g. the sukun on the lam and the fatha on the kashida in "ٱلْـَٔاخِرَةِ"
 * (13:26). Removing the Tatweel merges those marks onto a single base; without
 * a re-NFC they stay in an order canonical ordering would never produce, so the
 * typist's own NFC'd keystrokes can never match and the test cannot complete.
 */
export function normalizeDisplayText(raw) {
    return raw?.normalize('NFC').replace(/ـ/g, '').normalize('NFC').trim() || '';
}

/**
 * Map visual characters to logic (typed-against) ones, skipping the ۝
 * decorative ayah separator and unkeyable rasm annotations.
 *
 * @returns {{ logicText: string, vToL: number[], lToVStart: number[] }}
 */
export function buildVisualMapping(displayText) {
    const visual = displayText || '';
    let logicText = '';
    const vToL = new Array(visual.length).fill(-1);
    const lToVStart = [];

    let i = 0;
    while (i < visual.length) {
        const match = visual.substring(i).match(/^ ?۝[٠-٩]+ ?/);

        if (match) {
            const matchLen = match[0].length;
            lToVStart.push(i);
            logicText += ' ';
            vToL[i] = logicText.length - 1;
            for (let j = 1; j < matchLen; j++) {
                vToL[i + j] = -1;
            }
            i += matchLen;
        } else if (UNKEYABLE_MARK.test(visual[i])) {
            i++;
        } else {
            lToVStart.push(i);
            logicText += visual[i];
            vToL[i] = logicText.length - 1;
            i++;
        }
    }
    return { logicText, vToL, lToVStart };
}

/**
 * Fold a character to its keyable comparison form. With Tashkeel on we keep the
 * harakat but strip decorative/stop signs; with it off we strip every mark.
 */
export function normalizeForComparison(text, usePunctuation) {
    if (!text) return '';
    let result = text.normalize('NFC')
        .replace(/[أإآٱٰ]/g, 'ا') // ٱ wasla + ٰ dagger alif → plain alif (no key on standard layouts)
        .replace(/[ۀة]/g, 'ه')
        .replace(/[ىي]/g, 'ي');

    if (usePunctuation) {
        result = result.replace(/[ؐ-ؚـۖ-ۭ۝]/g, '');
    } else {
        result = result.replace(/[ؐ-ؚـً-ٰٟۖ-ۭ۝]/g, '');
    }

    return result.trim();
}
