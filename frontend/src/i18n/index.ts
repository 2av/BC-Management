import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import en from './locales/en.json'
import hi from './locales/hi.json'

const saved = localStorage.getItem('mitra_lang') ?? 'en'

void i18n.use(initReactI18next).init({
  resources: { en: { translation: en }, hi: { translation: hi } },
  lng: saved,
  fallbackLng: 'en',
  interpolation: { escapeValue: false },
})

export function setLanguage(lang: 'en' | 'hi') {
  localStorage.setItem('mitra_lang', lang)
  void i18n.changeLanguage(lang)
}

export default i18n
