import { createI18n } from 'vue-i18n'
import { watch } from 'vue'
import { messages } from '~/locales/messages'

type AppLocale = 'en' | 'ru'

export default defineNuxtPlugin((nuxtApp) => {
  const localeCookie = useCookie<AppLocale>('locale', {
    default: () => 'en',
  })

  const initialLocale: AppLocale = localeCookie.value === 'ru' ? 'ru' : 'en'

  const i18n = createI18n({
    legacy: false,
    locale: initialLocale,
    fallbackLocale: 'en',
    globalInjection: true,
    messages,
  })

  nuxtApp.vueApp.use(i18n)

  const syncDocumentLocale = (locale: AppLocale) => {
    if (import.meta.client) {
      document.documentElement.lang = locale
    }
  }

  syncDocumentLocale(initialLocale)

  watch(i18n.global.locale, (value) => {
    const nextLocale: AppLocale = value === 'ru' ? 'ru' : 'en'
    localeCookie.value = nextLocale
    syncDocumentLocale(nextLocale)
  }, { immediate: true })
})
