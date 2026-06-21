import { createI18n } from 'vue-i18n';
import ru from './locales/ru.json';
import en from './locales/en.json';
import az from './locales/az.json';

export const SUPPORTED_LOCALES = [
    { code: 'ru', label: 'Русский', short: 'RU' },
    { code: 'en', label: 'English', short: 'EN' },
    { code: 'az', label: 'Azərbaycan', short: 'AZ' },
];

const STORAGE_KEY = 'app_locale';

export function getStoredLocale() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved && SUPPORTED_LOCALES.some((l) => l.code === saved)) return saved;
    return 'ru';
}

export function setLocale(code) {
    if (!SUPPORTED_LOCALES.some((l) => l.code === code)) return;
    i18n.global.locale.value = code;
    localStorage.setItem(STORAGE_KEY, code);
    document.documentElement.setAttribute('lang', code);
}

const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: getStoredLocale(),
    fallbackLocale: 'ru',
    messages: { ru, en, az },
});

document.documentElement.setAttribute('lang', getStoredLocale());

export default i18n;
