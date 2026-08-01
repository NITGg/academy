import "server-only";
import { callAcademyApiPublicGet } from "@/lib/moodle-server";

/** Brand colour tokens mirrored from the edumy Moodle theme (Appearance → edumy settings). */
export interface ThemeTokens {
  primary?: string;
  primaryAlternate?: string;
  secondary?: string;
  tertiary?: string;
  accent?: string;
  accent2?: string;
  accent3?: string;
  accent4?: string;
  gradientStart?: string;
  gradientEnd?: string;
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
  push("primary", tokens.primary);
  push("primary-alternate", tokens.primaryAlternate);
  push("secondary", tokens.secondary);
  push("tertiary", tokens.tertiary);
  push("accent", tokens.accent);
  push("accent-2", tokens.accent2);
  push("accent-3", tokens.accent3);
  push("accent-4", tokens.accent4);
  push("gradient-start", tokens.gradientStart);
  push("gradient-end", tokens.gradientEnd);

  if (!decls.length) return "";
  return `:root{${decls.join(";")}}`;
}
