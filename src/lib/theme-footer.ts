import "server-only";
import { callAcademyApiPublicGet } from "@/lib/moodle-server";

/** One footer column from the Edumy theme (Appearance → Edumy → Footer). */
export interface ThemeFooterColumn {
  index: number;
  /** A column is active when its title OR body is set (mirrors the theme). */
  active: boolean;
  title: string;
  /** Raw HTML authored by admins, with {mlang} already resolved server-side. */
  body: string;
}

/** Footer settings mirrored from the Edumy theme's "Footer" tab. */
export interface ThemeFooterSettings {
  footertype?: string; // '1'..'9' — the "Footer style" (production uses '8')
  cocoon_copyright?: string;
  columns?: ThemeFooterColumn[];
  footer_menu?: string;
}

/**
 * Fetch the site's footer settings from Moodle. Public/no-token.
 * `lang` selects which language {mlang} content resolves to (?alang).
 * Resilient: returns {} on failure so the app can fall back to defaults.
 */
export async function getThemeFooterSettings(
  lang: "ar" | "en" = "ar",
): Promise<ThemeFooterSettings> {
  try {
    const res = await callAcademyApiPublicGet<{ footer: ThemeFooterSettings }>(
      "get_theme_footer_settings",
      {},
      lang,
    );
    return res?.footer ?? {};
  } catch {
    return {};
  }
}
