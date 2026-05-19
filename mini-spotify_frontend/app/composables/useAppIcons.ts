import { icons as materialSymbolsIconSet } from '@iconify-json/material-symbols'
import { icons as solarIconSet } from '@iconify-json/solar'
import type { IconifyIcon, IconifyJSON } from '@iconify/types'

const iconSets: Record<string, IconifyJSON> = {
  solar: solarIconSet as IconifyJSON,
  'material-symbols': materialSymbolsIconSet as IconifyJSON,
}

const iconCache = new Map<string, IconifyIcon | null>()

export const useAppIcons = () => {
  const getIcon = (name: string): IconifyIcon | undefined => {
    if (iconCache.has(name)) {
      return iconCache.get(name) || undefined
    }

    const [prefix, ...rest] = name.split(':')
    const iconName = rest.join(':')
    const iconSet = prefix ? iconSets[prefix] : undefined
    const iconData = iconSet?.icons?.[iconName] as Partial<IconifyIcon> | undefined

    if (!iconSet || !iconData) {
      console.warn(`[icons] Missing icon: ${name}`)
      iconCache.set(name, null)
      return undefined
    }

    const resolvedIcon: IconifyIcon = {
      left: iconData.left ?? iconSet.left ?? 0,
      top: iconData.top ?? iconSet.top ?? 0,
      width: iconData.width ?? iconSet.width ?? 24,
      height: iconData.height ?? iconSet.height ?? 24,
      rotate: iconData.rotate ?? 0,
      vFlip: iconData.vFlip ?? false,
      hFlip: iconData.hFlip ?? false,
      body: iconData.body || '',
    }

    iconCache.set(name, resolvedIcon)
    return resolvedIcon
  }

  return {
    getIcon,
  }
}
