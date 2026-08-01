import "server-only";
import { callAcademyApiPublicGet } from "@/lib/moodle-server";

/** Brand & section colour tokens mirrored from the Edumy Moodle theme (Appearance → Edumy settings → Color). */
export interface ThemeTokens {
  // Main & Gradients
  primary?: string;
  primaryAlternate?: string;
  secondary?: string;
  tertiary?: string;
  accent?: string;
  accent2?: string;
  accent3?: string;
  accent4?: string;
  parallax?: string;
  gradientStart?: string;
  gradientEnd?: string;

  // Header Styles
  headerStyle2Top?: string;
  headerStyle2Bottom?: string;
  headerStyle3Top?: string;
  headerStyle4Top?: string;
  headerStyle5?: string;
  headerStyle6Top?: string;

  // Footer Styles
  footerStyle1Top?: string;
  footerStyle1Bottom?: string;
  footerStyle2Top?: string;
  footerStyle2Bottom?: string;
  footerStyle3Top?: string;
  footerStyle3Middle?: string;
  footerStyle3Bottom?: string;
  footerStyle5Top?: string;
  footerStyle5Bottom?: string;
  footerStyle6All?: string;
  footerStyle7Top?: string;
  footerStyle7Bottom?: string;
}

/**
 * Fetch the site's brand colours from Moodle so the frontend can mirror them. Public/no-token.
 * Resilient: returns {} on any failure so the app falls back to its built-in palette.
 */
export async function getThemeTokens(): Promise<ThemeTokens> {
  try {
    const res = await callAcademyApiPublicGet<{ colors: ThemeTokens }>(
      "get_theme_tokens",
    );
    return res?.colors ?? {};
  } catch {
    return {};
  }
}

/**
 * Build the `:root { --edumy-*: … }` CSS injected into <head> so the mapped brand colours in
 * globals.css (which reference these vars with fallbacks) take effect. Returns "" when no tokens,
 * leaving the frontend's default palette untouched.
 */
export function themeTokensToCss(tokens: ThemeTokens): string {
  const decls: string[] = [];
  const push = (name: string, val?: string) => {
    if (val) decls.push(`--edumy-${name}:${val}`);
  };

  // Main & Gradients
  push("primary", tokens.primary);
  push("primary-alternate", tokens.primaryAlternate);
  push("secondary", tokens.secondary);
  push("tertiary", tokens.tertiary);
  push("accent", tokens.accent);
  push("accent-2", tokens.accent2);
  push("accent-3", tokens.accent3);
  push("accent-4", tokens.accent4);
  push("parallax", tokens.parallax);
  push("gradient-start", tokens.gradientStart);
  push("gradient-end", tokens.gradientEnd);

  // Header Styles
  push("header-style-2-top", tokens.headerStyle2Top);
  push("header-style-2-bottom", tokens.headerStyle2Bottom);
  push("header-style-3-top", tokens.headerStyle3Top);
  push("header-style-4-top", tokens.headerStyle4Top);
  push("header-style-5", tokens.headerStyle5);
  push("header-style-6-top", tokens.headerStyle6Top);

  // Footer Styles
  push("footer-style-1-top", tokens.footerStyle1Top);
  push("footer-style-1-bottom", tokens.footerStyle1Bottom);
  push("footer-style-2-top", tokens.footerStyle2Top);
  push("footer-style-2-bottom", tokens.footerStyle2Bottom);
  push("footer-style-3-top", tokens.footerStyle3Top);
  push("footer-style-3-middle", tokens.footerStyle3Middle);
  push("footer-style-3-bottom", tokens.footerStyle3Bottom);
  push("footer-style-5-top", tokens.footerStyle5Top);
  push("footer-style-5-bottom", tokens.footerStyle5Bottom);
  push("footer-style-6-all", tokens.footerStyle6All);
  push("footer-style-7-top", tokens.footerStyle7Top);
  push("footer-style-7-bottom", tokens.footerStyle7Bottom);

  if (!decls.length) return "";
  return `:root{${decls.join(";")}}`;
}
