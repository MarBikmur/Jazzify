// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  app: {
    head: {
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
      ],
    },
  },
  
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://backend.test/public/api'
    }
  },

  vite: {
    server: {
      allowedHosts: [
        'audaciously-abundant-dragon.cloudpub.ru',
        'jazzifydev.tunnel.kicshikxo.ru',
      ]
    }
  },
  
  nitro: {
    devProxy: {
      '/api': {
        target: 'http://backend.test/public/api',
        changeOrigin: true,
        prependPath: true
      }
    }
  }
})
