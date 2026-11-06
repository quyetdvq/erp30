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
    heightMedium: '36px',
    fontSize: '14px',
    placeholderColor: '#596f83',
    inputColor: '#ffffff',
    inputColorDisabled: '#f1f5fa',
  },
  // Naive UI owns its own input/select chrome, including hover/focus/errors.
  // These medium control values mirror --ds-control-* in tokens.css; never
  // replace them with CSS var() (Naive performs theme color calculations in JS).
  Input: {
    heightMedium: '36px',
    fontSizeMedium: '14px',
    paddingMedium: '6px 12px',
    color: '#ffffff',
    colorDisabled: '#f1f5fa',
    textColor: '#172b3d',
    textColorDisabled: '#40566d',
    placeholderColor: '#596f83',
    borderRadius: '6px',
    border: '1px solid #7c91a4',
    borderHover: '1px solid #698197',
    borderFocus: '1px solid #075c99',
  },
  Select: {
    peers: {
      InternalSelection: {
        heightMedium: '36px',
        fontSizeMedium: '14px',
        textColor: '#172b3d',
        placeholderColor: '#596f83',
        borderRadius: '6px',
        border: '1px solid #7c91a4',
        borderHover: '1px solid #698197',
        borderFocus: '1px solid #075c99',
      },
    },
  },
  Checkbox: { sizeMedium: '18px', fontSizeMedium: '14px' },
  Radio: { radioSizeMedium: '18px', fontSizeMedium: '14px' },
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
    heightMedium: '36px',
    fontSize: '14px',
    placeholderColor: '#a7bdcf',
    inputColor: '#182532',
    inputColorDisabled: '#223242',
  },
  // Naive UI owns its own input/select chrome, including hover/focus/errors.
  // These medium control values mirror --ds-control-* in tokens.css; never
  // replace them with CSS var() (Naive performs theme color calculations in JS).
  Input: {
    heightMedium: '36px',
    fontSizeMedium: '14px',
    paddingMedium: '6px 12px',
    color: '#182532',
    colorDisabled: '#223242',
    textColor: '#e8f0f7',
    textColorDisabled: '#c0d0df',
    placeholderColor: '#a7bdcf',
    borderRadius: '6px',
    border: '1px solid #698395',
    borderHover: '1px solid #8ba6b9',
    borderFocus: '1px solid #7ecaff',
  },
  Select: {
    peers: {
      InternalSelection: {
        heightMedium: '36px',
        fontSizeMedium: '14px',
        textColor: '#e8f0f7',
        placeholderColor: '#a7bdcf',
        borderRadius: '6px',
        border: '1px solid #698395',
        borderHover: '1px solid #8ba6b9',
        borderFocus: '1px solid #7ecaff',
      },
    },
  },
  Checkbox: { sizeMedium: '18px', fontSizeMedium: '14px' },
  Radio: { radioSizeMedium: '18px', fontSizeMedium: '14px' },
  Button: {
    heightSmall: '32px',
    heightMedium: '36px',
    heightLarge: '40px',
  },
}
