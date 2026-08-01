import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const isProd = process.env.NODE_ENV === "production";
const basePath =
  process.env.NEXT_PUBLIC_BASE_PATH ||
  (isProd ? "/nextjs-frontend-student" : "");

const remotePatterns: Array<{ protocol?: "http" | "https"; hostname: string; port?: string; pathname?: string }> = [
  { protocol: "https", hostname: "academy2026.nitg-eg.com" },
  { protocol: "http", hostname: "academy2026.nitg-eg.com" },
  { protocol: "http", hostname: "localhost" },
  { protocol: "https", hostname: "localhost" },
  { protocol: "http", hostname: "127.0.0.1" },
  { protocol: "https", hostname: "127.0.0.1" },
];

if (process.env.MOODLE_BASE_URL) {
  try {
    const url = new URL(process.env.MOODLE_BASE_URL);
    const protocol = url.protocol.replace(":", "") as "http" | "https";
    // Compare port too: a portless localhost entry does NOT cover localhost:8081,
    // so dedup must be host + protocol + port or the ported entry is wrongly skipped.
    const port = url.port || undefined;
    if (!remotePatterns.some((p) => p.hostname === url.hostname && p.protocol === protocol && p.port === port)) {
      remotePatterns.push({
        protocol,
        hostname: url.hostname,
        ...(url.port ? { port: url.port } : {}),
      });
    }
  } catch {
    // Ignore invalid URL
  }
}

const nextConfig: NextConfig = {
  env: {
    NEXT_PUBLIC_BASE_PATH: basePath,
  },
  ...(basePath && {
    basePath,
    assetPrefix: basePath,
  }),

  images: {
    dangerouslyAllowSVG: true,
    // Next 16 blocks the image optimizer from fetching URLs that resolve to a
    // private/loopback IP (SSRF protection). In local dev the Moodle host is
    // localhost (127.0.0.1/::1), so allow it there only — prod keeps protection.
    ...(isProd ? {} : { dangerouslyAllowLocalIP: true }),
    contentSecurityPolicy: "default-src 'self'; script-src 'none'; sandbox;",
    remotePatterns,
  },
};

export default withNextIntl(nextConfig);
