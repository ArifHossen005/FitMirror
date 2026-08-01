import i18next, { type i18n as I18nInstance } from 'i18next';
import { initReactI18next } from 'react-i18next';

import bnCommon from './locales/bn/common.json';
import enCommon from './locales/en/common.json';

const STORAGE_KEY = 'fitmirror.locale';

/**
 * bn is the product default (APP_LOCALE=bn on the backend); en is the
 * fallback, never the other way round — see PROGRESS.md Decision Log and
 * DOCUMENTATION.md §5.1.
 */
export function createI18n(): I18nInstance {
  const storedLocale = typeof window !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null;

  const instance = i18next.createInstance();

  void instance.use(initReactI18next).init({
    resources: {
      bn: { common: bnCommon },
      en: { common: enCommon },
    },
    lng: storedLocale ?? 'bn',
    fallbackLng: 'en',
    defaultNS: 'common',
    interpolation: { escapeValue: false }, // React already escapes
  });

  return instance;
}

export function persistLocale(locale: 'bn' | 'en'): void {
  localStorage.setItem(STORAGE_KEY, locale);
}
