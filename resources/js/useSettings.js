import { ref, watch } from 'vue';
import { translations } from './translations';

const currentLang = ref(localStorage.getItem('lang') || 'en');
const currentTheme = ref(localStorage.getItem('theme') || 'dark');
const currentKeyboardLayout = ref(localStorage.getItem('keyboardLayout') || 'qwerty');
const usePunctuation = ref(localStorage.getItem('usePunctuation') === 'true');

export function useSettings() {
    const setPunctuation = (value) => {
        usePunctuation.value = value;
        localStorage.setItem('usePunctuation', value);
    };

    const setKeyboardLayout = (layout) => {
        currentKeyboardLayout.value = layout;
        localStorage.setItem('keyboardLayout', layout);
    };

    const setLang = (lang) => {
        currentLang.value = lang;
        localStorage.setItem('lang', lang);

        // Set cookie for backend locale synchronization
        document.cookie = `lang=${lang}; path=/; max-age=31536000; SameSite=Lax`;

        document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
        document.documentElement.lang = lang;
    };

    const setTheme = (theme) => {
        currentTheme.value = theme;
        localStorage.setItem('theme', theme);
        if (theme === 'light') {
            document.documentElement.classList.add('lite-mode');
        } else {
            document.documentElement.classList.remove('lite-mode');
        }
    };

    // Initialize
    setLang(currentLang.value);
    setTheme(currentTheme.value);

    const t = (key) => {
        const keys = key.split('.');
        let value = translations[currentLang.value];
        for (const k of keys) {
            value = value?.[k];
        }
        return value || key;
    };

    return {
        currentLang,
        currentTheme,
        currentKeyboardLayout,
        usePunctuation,
        setLang,
        setTheme,
        setKeyboardLayout,
        setPunctuation,
        t
    };
}
