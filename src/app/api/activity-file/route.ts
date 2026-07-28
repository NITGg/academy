import { NextRequest } from "next/server";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import {
  resolveActivityFileUrl,
  resolveCertificateResponse,
} from "@/features/activity/server";

export const dynamic = "force-dynamic";

/**
 * Streams an activity's file (PDF, video, …) or certificate PDF to the browser
 * WITHOUT ever exposing the Moodle token.
 *
 * Usage: /api/activity-file?courseId=62&cmid=2044
 */
export async function GET(req: NextRequest) {
  const { searchParams } = new URL(req.url);
  const courseId = Number(searchParams.get("courseId"));
  const cmid = Number(searchParams.get("cmid"));
  const download = searchParams.get("download") === "1";

  if (!cmid) {
    return new Response("Missing cmid", { status: 400 });
  }

  const session = await getSessionFromCookie();
  if (!session?.wstoken) {
    return new Response("Unauthorized", { status: 401 });
  }

  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";

  let resolved = courseId
    ? await resolveActivityFileUrl(courseId, cmid, session.wstoken, lang)
    : null;

  let upstream: Response | null = null;
  let filename = resolved?.filename ?? "certificate.pdf";

  if (resolved?.url) {
    const range = req.headers.get("range");
    try {
      const res = await fetch(resolved.url, {
        headers: range ? { Range: range } : {},
        cache: "no-store",
      });
      if (res.ok || res.status === 206) {
        upstream = res;
      }
    } catch {
      /* fallback */
    }
  }

  // If activity file was not found (e.g. customcert/coursecertificate), try certificate PDF resolution
  if (!upstream) {
    const certRes = await resolveCertificateResponse(cmid, session.wstoken, lang);
    if (certRes && certRes.ok) {
      upstream = certRes;
    }
  }

  if (!upstream) {
    return new Response("Not found or no access", { status: 404 });
  }

  const headers = new Headers();
  const upstreamType = upstream.headers.get("content-type");
  const contentType =
    (upstreamType && !upstreamType.startsWith("text/html") ? upstreamType : null) ||
    (resolved?.mime?.includes("/") ? resolved.mime : null) ||
    "application/pdf";
  headers.set("Content-Type", contentType);
  headers.set("Accept-Ranges", "bytes");

  const passthrough = ["content-length", "content-range", "last-modified", "etag"];
  for (const h of passthrough) {
    const v = upstream.headers.get(h);
    if (v) headers.set(h, v);
  }

  const safeName = filename.replace(/["\\\r\n]/g, "_");
  const dispositionType = download ? "attachment" : "inline";
  headers.set(
    "Content-Disposition",
    `${dispositionType}; filename="${safeName}"; filename*=UTF-8''${encodeURIComponent(filename)}`,
  );
  headers.set("Cache-Control", "private, no-store");

  return new Response(upstream.body, {
    status: upstream.status,
    headers,
  });
}

