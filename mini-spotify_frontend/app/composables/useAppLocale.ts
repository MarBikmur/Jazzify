import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

export const useAppLocale = () => {
  const { locale, t } = useI18n()

  const currentLocale = computed<'en' | 'ru'>(() => locale.value === 'ru' ? 'ru' : 'en')
  const isRussian = computed(() => currentLocale.value === 'ru')

  const setLocale = (value: 'en' | 'ru') => {
    locale.value = value
  }

  const toggleLocale = () => {
    setLocale(isRussian.value ? 'en' : 'ru')
  }

  return {
    locale: currentLocale,
    isRussian,
    setLocale,
    toggleLocale,
    t,
  }
}
