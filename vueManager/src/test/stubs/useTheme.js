let themeName = 'aura'

export function __setThemeNameForTests(name) {
  themeName = name
}

export function getThemeName() {
  return themeName
}

export function getActiveTheme() {
  return { theme: { preset: {}, options: {} } }
}
