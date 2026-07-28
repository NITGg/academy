import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const nextConfig: NextConfig = {
  basePath: "/nextjs-frontend-student",
  assetPrefix: "/nextjs-frontend-student",

  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "academy2026.nitg-eg.com",
      },
    ],
  },
};

export default withNextIntl(nextConfig);
