<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<style>
:root {
  --color-bg: #EAF6FF;
  --color-bg-soft: #F5FBFF;
  --color-surface: #FFFFFF;
  --color-surface-hover: #DFF1FF;
  --color-border: #C8E1F5;
  --color-border-strong: rgba(0, 106, 202, 0.35);
  --color-shell-border: transparent;
  --color-primary: #006ACA;
  --color-primary-hover: #0059B3;
  --color-accent: #00A3FF;
  --color-link: #006ACA;
  --color-text-main: #0E1A2A;
  --color-text-muted: #52687A;
  --color-text-soft: #7B91A5;
  --color-text-disabled: rgba(123, 145, 165, 0.75);
  --color-success: #22C55E;
  --color-warning: #F59E0B;
  --color-error: #EF4444;
  --color-success-text: #166534;
  --color-warning-text: #92400E;
  --color-error-text: #991B1B;
  --color-info-text: #0059B3;
  --color-shell-surface: rgba(255, 255, 255, 0.9);
  --color-sidebar-surface: rgba(255, 255, 255, 0.94);
  --color-player-surface: rgba(255, 255, 255, 0.96);
  --color-topbar-surface: rgba(245, 251, 255, 0.85);
  --color-topbar-border: transparent;
  --color-content-bg: transparent;
  --color-library-item-hover: #DFF1FF;
  --color-library-item-active: #DFF1FF;
  --color-library-item-active-border: rgba(0, 106, 202, 0.18);
  --color-row-hover: #E8F4FF;
  --color-row-active: #CFE7FB;
  --color-row-active-border: rgba(0, 106, 202, 0.22);
  --color-card-surface: #FFFFFF;
  --color-card-surface-hover: #FFFFFF;
  --color-card-border: #C8E1F5;
  --color-card-shadow: 0 12px 32px rgba(14, 26, 42, 0.06);
  --color-card-shadow-hover: 0 16px 40px rgba(0, 106, 202, 0.10);
  --color-hero-bg:
    radial-gradient(circle at top right, rgba(0, 163, 255, 0.16), transparent 35%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(245, 251, 255, 0.96) 100%);
  --color-hero-border: #C8E1F5;
  --color-hero-shadow: 0 12px 32px rgba(14, 26, 42, 0.06);
  --color-artwork-default-bg: linear-gradient(145deg, #B9E5FF, #7EC8FF);
  --color-artwork-default-text: #006ACA;
  --color-artwork-playlist-bg: linear-gradient(145deg, #DFF1FF, #9FD8FF);
  --color-artwork-playlist-text: #006ACA;
  --color-artwork-liked-bg: linear-gradient(145deg, #5BB8FF, #B8EAFF);
  --color-artwork-liked-text: #FFFFFF;
  --color-input-bg: #FFFFFF;
  --color-input-border: #C8E1F5;
  --color-input-text: #0E1A2A;
  --color-input-placeholder: #7B91A5;
  --color-input-focus-border: #006ACA;
  --color-input-focus-ring: rgba(0, 106, 202, 0.16);
  --button-primary-bg: #006ACA;
  --button-primary-hover: #0059B3;
  --button-primary-text: #FFFFFF;
  --button-primary-border: transparent;
  --button-secondary-bg: #FFFFFF;
  --button-secondary-hover: #DFF1FF;
  --button-secondary-text: #006ACA;
  --button-secondary-border: #C8E1F5;
  --button-danger-bg: rgba(239, 68, 68, 0.10);
  --button-danger-hover: rgba(239, 68, 68, 0.16);
  --button-danger-text: #991B1B;
  --button-danger-border: rgba(239, 68, 68, 0.30);
  --button-control-bg: rgba(255, 255, 255, 0.96);
  --button-control-hover: #DFF1FF;
  --button-control-text: #52687A;
  --button-control-border: rgba(0, 106, 202, 0.18);
  --button-control-active-bg: #006ACA;
  --button-control-active-hover: #0059B3;
  --button-control-active-text: #FFFFFF;
  --button-control-active-border: rgba(0, 106, 202, 0.18);
  --progress-track-bg: rgba(0, 106, 202, 0.14);
  --progress-buffered-bg: rgba(0, 106, 202, 0.28);
  --progress-fill-bg: #006ACA;
  --color-overlay-strong: rgba(14, 26, 42, 0.32);
  --scrollbar-thumb: rgba(82, 104, 122, 0.28);
  --scrollbar-track: rgba(82, 104, 122, 0.08);
  --shadow-card: 0 12px 32px rgba(14, 26, 42, 0.06);
  --shadow-card-hover: 0 16px 40px rgba(0, 106, 202, 0.10);
  --shadow-primary: 0 8px 24px rgba(0, 106, 202, 0.18);
  --shadow-soft: 0 10px 30px rgba(14, 26, 42, 0.08);
  --shadow-menu: 0 18px 44px rgba(14, 26, 42, 0.14);
  --overlay-soft: rgba(14, 26, 42, 0.32);
  --gradient-page: linear-gradient(180deg, #EAF6FF 0%, #F5FBFF 100%);
  --gradient-glow: radial-gradient(circle at top right, rgba(0, 163, 255, 0.22), transparent 36%);
  --color-player-control-border: rgba(0, 106, 202, 0.18);
  --color-avatar-bg: linear-gradient(145deg, #79C8FF, #006ACA);
  --color-avatar-text: #FFFFFF;
  --radius-card: 14px;
  --radius-control: 12px;
}

:root[data-theme='dark'] {
  --color-bg: #041a32;
  --color-bg-soft: #072445;
  --color-surface: #0b2f56;
  --color-surface-hover: #123d6c;
  --color-border: rgba(226, 242, 255, 0.12);
  --color-border-strong: rgba(226, 242, 255, 0.22);
  --color-shell-border: rgba(226, 242, 255, 0.14);
  --color-primary: #8ED8FF;
  --color-primary-hover: #BFEAFF;
  --color-accent: #8ED8FF;
  --color-link: #BFE7FF;
  --color-text-main: #FFFFFF;
  --color-text-muted: #d1e4f7;
  --color-text-soft: #97b5d0;
  --color-text-disabled: rgba(209, 228, 247, 0.5);
  --color-success: #73E2A7;
  --color-warning: #FFD166;
  --color-error: #FF6B7C;
  --color-success-text: #DDF9EA;
  --color-warning-text: #FFF0C1;
  --color-error-text: #FFD5DB;
  --color-info-text: #EAF6FF;
  --color-shell-surface: #07203d;
  --color-sidebar-surface: rgba(8, 32, 61, 0.98);
  --color-player-surface: rgba(7, 28, 53, 0.98);
  --color-topbar-surface: rgba(8, 33, 63, 0.94);
  --color-topbar-border: rgba(226, 242, 255, 0.12);
  --color-content-bg: #051d38;
  --color-library-item-hover: rgba(255, 255, 255, 0.06);
  --color-library-item-active: rgba(142, 216, 255, 0.12);
  --color-library-item-active-border: rgba(142, 216, 255, 0.2);
  --color-row-hover: rgba(255, 255, 255, 0.06);
  --color-row-active: rgba(142, 216, 255, 0.14);
  --color-row-active-border: rgba(142, 216, 255, 0.2);
  --color-card-surface: rgba(9, 38, 70, 0.94);
  --color-card-surface-hover: #103762;
  --color-card-border: rgba(226, 242, 255, 0.14);
  --color-card-shadow: 0 18px 42px rgba(0, 0, 0, 0.28);
  --color-card-shadow-hover: 0 20px 46px rgba(0, 0, 0, 0.34);
  --color-hero-bg:
    radial-gradient(circle at top right, rgba(142, 216, 255, 0.1), transparent 34%),
    linear-gradient(135deg, #0c3159 0%, #071f3b 100%);
  --color-hero-border: rgba(226, 242, 255, 0.14);
  --color-hero-shadow: 0 18px 40px rgba(0, 0, 0, 0.32);
  --color-artwork-default-bg: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(142, 216, 255, 0.08));
  --color-artwork-default-text: #FFFFFF;
  --color-artwork-playlist-bg: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(142, 216, 255, 0.08));
  --color-artwork-playlist-text: #8ED8FF;
  --color-artwork-liked-bg: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(142, 216, 255, 0.12));
  --color-artwork-liked-text: #FFFFFF;
  --color-input-bg: rgba(255, 255, 255, 0.06);
  --color-input-border: rgba(226, 242, 255, 0.16);
  --color-input-text: #FFFFFF;
  --color-input-placeholder: rgba(209, 228, 247, 0.55);
  --color-input-focus-border: rgba(142, 216, 255, 0.75);
  --color-input-focus-ring: rgba(142, 216, 255, 0.14);
  --button-primary-bg: #FFFFFF;
  --button-primary-hover: #EAF6FF;
  --button-primary-text: #0c3159;
  --button-primary-border: rgba(255,255,255,0.14);
  --button-secondary-bg: rgba(255, 255, 255, 0.07);
  --button-secondary-hover: rgba(255, 255, 255, 0.12);
  --button-secondary-text: #FFFFFF;
  --button-secondary-border: rgba(226, 242, 255, 0.16);
  --button-danger-bg: rgba(255, 107, 124, 0.18);
  --button-danger-hover: rgba(255, 107, 124, 0.26);
  --button-danger-text: #FFD5DB;
  --button-danger-border: rgba(255, 107, 124, 0.4);
  --button-control-bg: rgba(255, 255, 255, 0.08);
  --button-control-hover: rgba(255, 255, 255, 0.12);
  --button-control-text: #FFFFFF;
  --button-control-border: rgba(226, 242, 255, 0.14);
  --button-control-active-bg: #FFFFFF;
  --button-control-active-hover: #EAF6FF;
  --button-control-active-text: #0c3159;
  --button-control-active-border: rgba(255, 255, 255, 0.14);
  --progress-track-bg: rgba(255, 255, 255, 0.14);
  --progress-buffered-bg: rgba(255, 255, 255, 0.2);
  --progress-fill-bg: #8ED8FF;
  --color-overlay-strong: rgba(1, 10, 20, 0.72);
  --scrollbar-thumb: rgba(255, 255, 255, 0.18);
  --scrollbar-track: rgba(255, 255, 255, 0.04);
  --shadow-card: 0 18px 42px rgba(0, 0, 0, 0.28);
  --shadow-card-hover: 0 20px 48px rgba(0, 0, 0, 0.34);
  --shadow-primary: 0 10px 28px rgba(0, 0, 0, 0.3);
  --shadow-soft: 0 10px 26px rgba(0, 0, 0, 0.24);
  --shadow-menu: 0 22px 50px rgba(0, 0, 0, 0.4);
  --overlay-soft: rgba(1, 10, 20, 0.72);
  --gradient-page: linear-gradient(180deg, #041a32 0%, #062241 56%, #072a4e 100%);
  --gradient-glow: radial-gradient(circle at top right, rgba(142, 216, 255, 0.08), transparent 32%);
  --color-player-control-border: rgba(226, 242, 255, 0.14);
  --color-avatar-bg: linear-gradient(145deg, rgba(255, 255, 255, 0.1), rgba(142, 216, 255, 0.14));
  --color-avatar-text: #FFFFFF;
}

html,
body,
#__nuxt {
  min-height: 100%;
  margin: 0;
  background: var(--color-bg);
  color: var(--color-text-main);
}

body {
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

* {
  box-sizing: border-box;
}

a {
  color: var(--color-link);
}

button,
input,
select,
textarea {
  font: inherit;
}

* {
  scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
}

*::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}

*::-webkit-scrollbar-track {
  background: var(--scrollbar-track);
}

*::-webkit-scrollbar-thumb {
  background: var(--scrollbar-thumb);
  border-radius: 999px;
}
</style>
