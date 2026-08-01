import { NextRequest, NextResponse } from "next/server";
import { getThemeFooterSettings } from "@/lib/theme-footer";

export async function GET(req: NextRequest) {
  try {
    const lang = req.nextUrl.searchParams.get("lang") === "en" ? "en" : "ar";
    const footer = await getThemeFooterSettings(lang);
    return NextResponse.json({ footer });
  } catch (err) {
    const message =
      err instanceof Error ? err.message : "Failed to fetch footer settings";
    return NextResponse.json({ error: message, footer: {} }, { status: 500 });
  }
}
