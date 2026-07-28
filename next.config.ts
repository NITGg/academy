import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const isProd = process.env.NODE_ENV === "production";
const basePath =
  process.env.NEXT_PUBLIC_BASE_PATH ||
  (isProd ? "/nextjs-frontend-student" : "");

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
    contentSecurityPolicy: "default-src 'self'; script-src 'none'; sandbox;",
    remotePatterns: [
      {
        protocol: "https",
        hostname: "academy2026.nitg-eg.com",
      },
      {
        protocol: "http",
        hostname: "academy2026.nitg-eg.com",
      },
    ],
  },
};

export default withNextIntl(nextConfig);
