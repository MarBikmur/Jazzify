export interface EditorPanelStat {
  label: string
  value: string | number
}

export interface EditorPanelSection {
  title: string
  items: string[]
}

export interface EditorPanelConfig {
  eyebrow?: string
  title: string
  description?: string
  imageUrl?: string
  imageShape?: 'circle' | 'square'
  imageFallback?: string
  stats?: EditorPanelStat[]
  sections?: EditorPanelSection[]
  note?: string
}

export const useEditorLayout = () => {
  const editorPanel = useState<EditorPanelConfig | null>('editor-layout-panel', () => null)
  const isSidebarCollapsed = useState<boolean>('editor-layout-sidebar-collapsed', () => false)

  const setEditorPanel = (config: EditorPanelConfig) => {
    editorPanel.value = config
  }

  const clearEditorPanel = () => {
    editorPanel.value = null
  }

  const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value
  }

  return {
    editorPanel,
    isSidebarCollapsed,
    setEditorPanel,
    clearEditorPanel,
    toggleSidebar,
  }
}
