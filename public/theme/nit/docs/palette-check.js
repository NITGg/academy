// Brand Colors groups 4 and 5 — generator and contrast audit.
//
//   node public/theme/nit/docs/palette-check.js
//
// Groups 1-3 were picked by eye. Groups 4 and 5 are the light/dark pair behind
// the navbar switch, and they were not: this builds them from two OKLCH ramps
// (a near-neutral and an azure accent) sampled at fixed lightness steps, maps
// the ramp positions onto the 23 brand roles, and prints every text/background
// pair with its WCAG contrast. The hexes it prints are the ones pasted into
// theme_nit_brand_group_defaults() in lib.php.
//
// Why OKLCH: lightness in OKLCH matches what the eye sees, so a step from L60 to
// L50 is the same visual step at any hue — which is what lets light and dark be
// the same palette read from opposite ends instead of two guesses. Out-of-gamut
// colours are folded by reducing CHROMA only (never lightness or hue), so a
// colour keeps its identity instead of shifting hue on the way into sRGB.
//
// Change a ramp stop or a role assignment, re-run, and read the contrast column
// before pasting: anything below 4.5 for text is a regression.
//
// OKLCH -> sRGB with chroma-only gamut mapping, plus WCAG contrast checking.
// Used to build theme_nit Brand Colors groups 4 (light) and 5 (dark) as a
// perceptually even pair rather than hand-picked hexes.

const f = (x) => (x <= 0.0031308 ? 12.92 * x : 1.055 * Math.pow(x, 1 / 2.4) - 0.055);
const fInv = (x) => (x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4));

function oklabToLinearSrgb(L, a, b) {
  const l_ = L + 0.3963377774 * a + 0.2158037573 * b;
  const m_ = L - 0.1055613458 * a - 0.0638541728 * b;
  const s_ = L - 0.0894841775 * a - 1.2914855480 * b;
  const l = l_ ** 3, m = m_ ** 3, s = s_ ** 3;
  return [
    +4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
    -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
    -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s,
  ];
}

function oklchToRgb(Lp, C, Hdeg) {
  const L = Lp / 100, h = (Hdeg * Math.PI) / 180;
  return oklabToLinearSrgb(L, C * Math.cos(h), C * Math.sin(h));
}

const inGamut = ([r, g, b]) => [r, g, b].every((v) => v >= -1e-4 && v <= 1 + 1e-4);

// Reduce chroma (never L or H) until the colour fits sRGB — the skill's rule.
function toHex(Lp, C, H) {
  let lo = 0, hi = C, rgb = oklchToRgb(Lp, C, H);
  if (!inGamut(rgb)) {
    for (let i = 0; i < 40; i++) {
      const mid = (lo + hi) / 2;
      if (inGamut(oklchToRgb(Lp, mid, H))) lo = mid; else hi = mid;
    }
    rgb = oklchToRgb(Lp, lo, H);
  }
  return '#' + rgb.map((v) => {
    const n = Math.round(Math.min(1, Math.max(0, f(v))) * 255);
    return n.toString(16).padStart(2, '0');
  }).join('');
}

function relLum(hex) {
  const [r, g, b] = [1, 3, 5].map((i) => fInv(parseInt(hex.slice(i, i + 2), 16) / 255));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
const contrast = (a, b) => {
  const [x, y] = [relLum(a), relLum(b)].sort((p, q) => q - p);
  return (x + 0.05) / (y + 0.05);
};

// ---------------------------------------------------------------------------
// The system: one neutral ramp (cool, near-achromatic) + one azure accent ramp,
// both sampled at fixed OKLCH lightness steps so light and dark are the same
// palette read from opposite ends.
// ---------------------------------------------------------------------------
const NH = 258;
const AH = 256;               // accent hue

const N = {};
[[0, 99.2], [50, 97.8], [100, 96.3], [200, 93], [300, 88.5], [350, 84], [400, 74],
 [500, 60], [600, 50], [700, 40], [800, 30], [850, 25.5], [900, 21], [950, 17.5]]
  .forEach(([k, L]) => { N[k] = toHex(L, L > 90 ? 0.005 : L > 60 ? 0.009 : 0.014, NH); });

const A = {};
[[200, 88, 0.055], [300, 80, 0.09], [400, 72, 0.12], [500, 62, 0.145],
 [600, 52, 0.15], [700, 44, 0.14], [800, 36, 0.115]]
  .forEach(([k, L, C]) => { A[k] = toHex(L, C, AH); });

// Semantic hues, sampled at a light-safe and a dark-safe lightness.
const SEM = { danger: 40, success: 155, caution: 85, note: 230 };
const S = { light: {}, dark: {} };
Object.entries(SEM).forEach(([k, h]) => {
  S.light[k] = toHex(48, 0.135, h);
  S.dark[k] = toHex(72, 0.125, h);
});

const g4 = { onprimary: '#ffffff',
  primary: A[600], secondary: N[200], accent: A[600], accenttext: A[700],
  background: N[50], background2: N[100], surface: '#ffffff',
  navbarbackground1: N[950], navbarbackground2: N[900],
  footerbackground1: N[950], footerbackground2: N[900],
  textprimary: N[900], navbariconcolor: N[50], navbariconbg: N[850],
  textsecondary: N[600], borderprimary: N[300], bordersecondary: N[400],
  hoverbackground: N[100], hovertext: A[800],
  error: S.light.danger, success: S.light.success,
  warning: S.light.caution, info: S.light.note,
};

const g5 = { onprimary: '#0d1117',
  primary: A[400], secondary: N[800], accent: A[400], accenttext: A[300],
  background: N[950], background2: N[900], surface: N[850],
  navbarbackground1: N[950], navbarbackground2: N[900],
  footerbackground1: N[950], footerbackground2: N[900],
  textprimary: N[50], navbariconcolor: N[50], navbariconbg: N[850],
  textsecondary: N[400], borderprimary: N[800], bordersecondary: N[700],
  hoverbackground: N[900], hovertext: A[200],
  error: S.dark.danger, success: S.dark.success,
  warning: S.dark.caution, info: S.dark.note,
};

function report(name, g) {
  console.log('\n--- ' + name + ' ---');
  const order = ['primary', 'secondary', 'accent', 'accenttext', 'background', 'background2',
    'navbarbackground1', 'navbarbackground2', 'footerbackground1', 'footerbackground2',
    'surface', 'textprimary', 'navbariconcolor', 'navbariconbg', 'textsecondary',
    'borderprimary', 'bordersecondary', 'hoverbackground', 'hovertext',
    'error', 'success', 'warning', 'info'];
  order.forEach((k) => console.log("            '" + k + "'" + ' '.repeat(19 - k.length) + "=> '" + g[k] + "',"));

  const checks = [
    ['ink on page', g.textprimary, g.background],
    ['ink on surface', g.textprimary, g.surface],
    ['muted on page', g.textsecondary, g.background],
    ['muted on surface', g.textsecondary, g.surface],
    ['link on page', g.accenttext, g.background],
    ['link on surface', g.accenttext, g.surface],
    ['link hover on surface', g.hovertext, g.surface],
    ['on-primary on primary', g.onprimary, g.primary],
    ['error on surface', g.error, g.surface],
    ['success on surface', g.success, g.surface],
    ['warning on surface', g.warning, g.surface],
    ['info on surface', g.info, g.surface],
    ['navbar text on bar', g.navbariconcolor, g.navbarbackground1],
    ['border on surface', g.borderprimary, g.surface],
    ['primary fill on page', g.primary, g.background],
  ];
  console.log('  contrast:');
  checks.forEach(([label, fg, bg]) => {
    const c = contrast(fg, bg);
    const need = label === 'border on page' ? 1.5 : (label.startsWith('muted') || label.includes('on surface') ? 4.5 : 4.5);
    const tag = c >= 7 ? 'AAA' : c >= 4.5 ? 'AA ' : c >= 3 ? 'AA-large' : 'FAIL';
    console.log('    ' + (c.toFixed(2) + '').padStart(6) + '  ' + tag.padEnd(9) + label + '  (' + fg + ' on ' + bg + ')');
  });
}

report('Group 4 (light)', g4);
report('Group 5 (dark)', g5);
console.log('\nneutral ramp:', JSON.stringify(N));
console.log('accent ramp:', JSON.stringify(A));
