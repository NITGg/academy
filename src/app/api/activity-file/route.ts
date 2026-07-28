import { NextRequest } from "next/server";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { resolveActivityFileUrl } from "@/features/activity/server";

export const dynamic = "force-dynamic";

/**
 * Streams an activity's file (PDF, video, …) to the browser WITHOUT ever exposing
 * the Moodle token. The token-bearing pluginfile URL is resolved server-side, scoped
 * to the current user (a file they cannot access is 404, not a token leak).
 *
 * Supports HTTP Range requests so <video> seeking / streaming works.
 *
 * Usage: /api/activity-file?courseId=62&cmid=2044
 */
export async function GET(req: NextRequest) {
  const { searchParams } = new URL(req.url);
  const courseId = Number(searchParams.get("courseId"));
  const cmid = Number(searchParams.get("cmid"));

  if (!courseId || !cmid) {
    return new Response("Missing courseId or cmid", { status: 400 });
  }

  const session = await getSessionFromCookie();
  if (!session?.wstoken) {
    return new Response("Unauthorized", { status: 401 });
  }

  const locale = await getLocale();
  const resolved = await resolveActivityFileUrl(
    courseId,
    cmid,
    session.wstoken,
    locale === "ar" ? "ar" : "en",
  );

  if (!resolved) {
    return new Response("Not found or no access", { status: 404 });
  }

  // Forward Range so the browser can seek within videos and stream large files.
  const range = req.headers.get("range");
  const upstream = await fetch(resolved.url, {
    headers: range ? { Range: range } : {},
    cache: "no-store",
  });

  if (!upstream.ok && upstream.status !== 206) {
    return new Response("Upstream error", { status: 502 });
  }

  const headers = new Headers();
  const upstreamType = upstream.headers.get("content-type");
  const contentType =
    (upstreamType && !upstreamType.startsWith("text/html") ? upstreamType : null) ||
    (resolved.mime?.includes("/") ? resolved.mime : null) ||
    "application/octet-stream";
  headers.set("Content-Type", contentType);
  headers.set("Accept-Ranges", "bytes");

  const passthrough = ["content-length", "content-range", "last-modified", "etag"];
  for (const h of passthrough) {
    const v = upstream.headers.get(h);
    if (v) headers.set(h, v);
  }

  const safeName = (resolved.filename ?? "file").replace(/["\\\r\n]/g, "_");
  headers.set(
    "Content-Disposition",
    `inline; filename="${safeName}"; filename*=UTF-8''${encodeURIComponent(resolved.filename ?? "file")}`,
  );
  headers.set("Cache-Control", "private, no-store");

  return new Response(upstream.body, {
    status: upstream.status,
    headers,
  });
}


