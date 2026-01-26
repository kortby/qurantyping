import { ref, watch } from 'vue';
import { translations } from './translations';

const currentLang = ref(localStorage.getItem('lang') || 'en');
const currentTheme = ref(localStorage.getItem('theme') || 'dark');

export function useSettings() {
    const setLang = (lang) => {
        currentLang.value = lang;
        localStorage.setItem('lang', lang);
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
        setLang,
        setTheme,
        t
    };
}
