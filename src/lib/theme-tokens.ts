import "server-only";
import { callAcademyApiPublicGet } from "@/lib/moodle-server";

/** Brand & section colour tokens mirrored from the Edumy Moodle theme (Appearance → Edumy settings → Color). */
/** Brand & section colour tokens mirrored from the Edumy Moodle theme (Appearance → Edumy settings → Color). */
export interface ThemeTokens {
  // Main & Gradients
  color_gradient_start?: string;
  color_gradient_end?: string;
  color_primary?: string;
  color_primary_alternate?: string;
  color_secondary?: string;
  color_tertiary?: string;
  color_accent?: string;
  color_accent_2?: string;
  color_accent_3?: string;
  color_accent_4?: string;
  color_parallax?: string;

  // Header Styles
  color_header_style_2_top?: string;
  color_header_style_2_bottom?: string;
  color_header_style_3_top?: string;
  color_header_style_4_top?: string;
  color_header_style_5?: string;
  color_header_style_6_top?: string;

  // Footer Styles
  color_footer_style_1_top?: string;
  color_footer_style_1_bottom?: string;
  color_footer_style_2_top?: string;
  color_footer_style_2_bottom?: string;
  color_footer_style_3_top?: string;
  color_footer_style_3_middle?: string;
  color_footer_style_3_bottom?: string;
  color_footer_style_5_top?: string;
  color_footer_style_5_bottom?: string;
  color_footer_style_6_all?: string;
  color_footer_style_7_top?: string;
  color_footer_style_7_bottom?: string;
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
    // Converts e.g. "color_primary" to "--edumy-color-primary"
    const cssVarName = name.replace(/_/g, "-");
    if (val) decls.push(`--edumy-${cssVarName}:${val}`);
  };

  // Main & Gradients
  push("color_gradient_start", tokens.color_gradient_start);
  push("color_gradient_end", tokens.color_gradient_end);
  push("color_primary", tokens.color_primary);
  push("color_primary_alternate", tokens.color_primary_alternate);
  push("color_secondary", tokens.color_secondary);
  push("color_tertiary", tokens.color_tertiary);
  push("color_accent", tokens.color_accent);
  push("color_accent_2", tokens.color_accent_2);
  push("color_accent_3", tokens.color_accent_3);
  push("color_accent_4", tokens.color_accent_4);
  push("color_parallax", tokens.color_parallax);

  // Header Styles
  push("color_header_style_2_top", tokens.color_header_style_2_top);
  push("color_header_style_2_bottom", tokens.color_header_style_2_bottom);
  push("color_header_style_3_top", tokens.color_header_style_3_top);
  push("color_header_style_4_top", tokens.color_header_style_4_top);
  push("color_header_style_5", tokens.color_header_style_5);
  push("color_header_style_6_top", tokens.color_header_style_6_top);

  // Footer Styles
  push("color_footer_style_1_top", tokens.color_footer_style_1_top);
  push("color_footer_style_1_bottom", tokens.color_footer_style_1_bottom);
  push("color_footer_style_2_top", tokens.color_footer_style_2_top);
  push("color_footer_style_2_bottom", tokens.color_footer_style_2_bottom);
  push("color_footer_style_3_top", tokens.color_footer_style_3_top);
  push("color_footer_style_3_middle", tokens.color_footer_style_3_middle);
  push("color_footer_style_3_bottom", tokens.color_footer_style_3_bottom);
  push("color_footer_style_5_top", tokens.color_footer_style_5_top);
  push("color_footer_style_5_bottom", tokens.color_footer_style_5_bottom);
  push("color_footer_style_6_all", tokens.color_footer_style_6_all);
  push("color_footer_style_7_top", tokens.color_footer_style_7_top);
  push("color_footer_style_7_bottom", tokens.color_footer_style_7_bottom);

  if (!decls.length) return "";
  return `:root{${decls.join(";")}}`;
}
