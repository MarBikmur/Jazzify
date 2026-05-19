export type RightPanelView = 'messenger' | 'trackComments' | null

export const useRightPanel = () => {
  const activeRightPanel = useState<RightPanelView>('active-right-panel', () => null)

  const isRightPanelOpen = computed(() => activeRightPanel.value !== null)

  const openRightPanel = (panel: Exclude<RightPanelView, null>) => {
    activeRightPanel.value = panel
  }

  const closeRightPanel = () => {
    activeRightPanel.value = null
  }

  const toggleRightPanel = (panel: Exclude<RightPanelView, null>) => {
    activeRightPanel.value = activeRightPanel.value === panel ? null : panel
  }

  return {
    activeRightPanel,
    isRightPanelOpen,
    openRightPanel,
    closeRightPanel,
    toggleRightPanel,
  }
}
