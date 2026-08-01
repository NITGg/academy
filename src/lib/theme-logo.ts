import "server-only";
import { callAcademyApiPublicGet } from "@/lib/moodle-server";

/** Logo settings mirrored from the Edumy Moodle theme (Appearance → Edumy settings → Logo). */
export interface ThemeLogoSettings {
  logotype?: string; // '0' = Visible, '1' = Hidden
  logo_image?: string; // '0' = Visible, '1' = Hidden
  logo_image_width?: string;
  logo_image_height?: string;
  headerlogo1?: string | null;
  headerlogo2?: string | null;
  headerlogo3?: string | null;
  headerlogo4?: string | null;
  headerlogo_mobile?: string | null;

  logotype_footer?: string; // '0' = Visible, '1' = Hidden
  logo_image_footer?: string; // '0' = Visible, '1' = Hidden
  logo_image_width_footer?: string;
  logo_image_height_footer?: string;
  footerlogo1?: string | null;
}

/**
 * Fetch the site's logo settings from Moodle. Public/no-token.
 * Resilient: returns {} on failure so the app falls back to standard defaults.
 */
export async function getThemeLogoSettings(): Promise<ThemeLogoSettings> {
  try {
    const res = await callAcademyApiPublicGet<{ logo: ThemeLogoSettings }>(
      "get_theme_logo_settings"
    );
    return res?.logo ?? {};
  } catch {
    return {};
  }
}
