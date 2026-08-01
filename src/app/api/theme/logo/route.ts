import { NextResponse } from "next/server";
import { getThemeLogoSettings } from "@/lib/theme-logo";

export async function GET() {
  try {
    const logo = await getThemeLogoSettings();
    return NextResponse.json({ logo });
  } catch (err) {
    const message = err instanceof Error ? err.message : "Failed to fetch logo settings";
    return NextResponse.json({ error: message, logo: {} }, { status: 500 });
  }
}
