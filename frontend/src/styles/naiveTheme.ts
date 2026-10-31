import type { GlobalThemeOverrides } from 'naive-ui'

// Naive UI computes colors in JavaScript (CSS var() is not a valid input to
// its color math). Keep these integration values aligned with tokens.css;
// scripts/design-system/design-system.test.cjs checks that invariant.
export const naiveLightOverrides: GlobalThemeOverrides = {
  common: {
    primaryColor: '#075c99',
    primaryColorHover: '#064b7c',
    primaryColorPressed: '#043a60',
    primaryColorSuppl: '#075c99',
    infoColor: '#0c659a',
    successColor: '#166a46',
    warningColor: '#885000',
    errorColor: '#b32734',
    bodyColor: '#f4f7fb',
    cardColor: '#ffffff',
    modalColor: '#ffffff',
    popoverColor: '#ffffff',
    textColor1: '#172b3d',
    textColor2: '#40566d',
    textColor3: '#596f83',
    borderColor: '#7c91a4',
    borderRadius: '8px',
    fontSize: '14px',
  },
  Button: {
    heightSmall: '32px',
    heightMedium: '36px',
    heightLarge: '40px',
  },
}

export const naiveDarkOverrides: GlobalThemeOverrides = {
  common: {
    primaryColor: '#75bfff',
    primaryColorHover: '#a0d4ff',
    primaryColorPressed: '#c4e5ff',
    primaryColorSuppl: '#75bfff',
    infoColor: '#88ccff',
    successColor: '#69d4a4',
    warningColor: '#f6c87b',
    errorColor: '#ff8f96',
    bodyColor: '#0f1721',
    cardColor: '#182532',
    modalColor: '#1c2b39',
    popoverColor: '#1c2b39',
    textColor1: '#e8f0f7',
    textColor2: '#c0d0df',
    textColor3: '#a7bdcf',
    borderColor: '#698395',
    borderRadius: '8px',
    fontSize: '14px',
  },
  Button: {
    heightSmall: '32px',
    heightMedium: '36px',
    heightLarge: '40px',
  },
}
