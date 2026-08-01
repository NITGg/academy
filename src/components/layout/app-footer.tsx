"use client";

import { useEffect } from "react";
import Link from "next/link";
import Image from "next/image";
import logoW from "@/../public/assets/logoW.svg";
import { useTranslations, useLocale } from "next-intl";
import { cn } from "@/lib/utils";

import { useThemeLogoStore } from "@/store/useThemeLogoStore";
import { useThemeFooterStore } from "@/store/useThemeFooterStore";

export function AppFooter() {
  const tApp = useTranslations("app");
  const locale = useLocale();

  const logoSettings = useThemeLogoStore((state) => state.logo);
  const footer = useThemeFooterStore((state) => state.footer);
  const fetchFooterSettings = useThemeFooterStore(
    (state) => state.fetchFooterSettings,
  );

  // Footer content is language-dependent ({mlang}); refetch when the locale changes.
  useEffect(() => {
    fetchFooterSettings(locale);
  }, [locale, fetchFooterSettings]);

  const footerLogoSrc =
    logoSettings.footerlogo1 || logoSettings.headerlogo1 || logoW;
  const showFooterImage = logoSettings.logo_image_footer !== "1";
  const logoWidth = logoSettings.logo_image_width_footer
    ? Number(logoSettings.logo_image_width_footer)
    : 22;
  const logoHeight = logoSettings.logo_image_height_footer
    ? Number(logoSettings.logo_image_height_footer)
    : 22;

  const columns = (footer.columns ?? []).filter((c) => c.active);

  // Shared styling for admin-authored HTML (links, images, headings) injected below.
  // Footer 8 is authored for a dark background (admin HTML uses white text), so the
  // footer is always dark here to match production regardless of the site theme.
  const htmlProse =
    "[&_a]:transition-colors [&_a:hover]:text-primary [&_img]:inline-block [&_img]:max-w-full [&_h4]:text-white [&_h4]:font-semibold [&_h5]:text-slate-300";

  return (
    <footer className="mt-auto border-t border-white/10 bg-[#080f1d] text-slate-300">
      {(showFooterImage || columns.length > 0) && (
        <section className="container mx-auto px-4 lg:px-6 py-10">
          <div className="flex flex-col lg:flex-row items-center justify-between w-full gap-4 lg:gap-6 lg:flex-nowrap">
            {/* ── Logo column – far right in RTL, far left in LTR ── */}
            {showFooterImage && (
              <div className="shrink-0 w-48 flex justify-start">
                <Link
                  href="/"
                  className="inline-flex items-center justify-start w-full"
                >
                  <Image
                    src={footerLogoSrc}
                    alt={tApp("name")}
                    width={logoWidth > 40 ? logoWidth : 160}
                    height={logoHeight > 40 ? logoHeight : 60}
                    className="w-full h-auto max-h-20 object-contain"
                  />
                </Link>
              </div>
            )}

            {/* ── Admin-configured columns (social icons, description, etc.) ── */}
            {columns.map((col, idx) => {
              const isLast = idx === columns.length - 1 && columns.length > 1;
              return (
                <div
                  key={col.index}
                  className={cn(
                    isLast
                      ? "shrink-0 w-52 flex justify-end"
                      : "flex-1 w-full text-center px-4 lg:px-6",
                  )}
                >
                  {col.title && (
                    <h4 className="text-body-strong mb-3 text-white">
                      {col.title}
                    </h4>
                  )}
                  {col.body && (
                    <div
                      className={cn(
                        "text-sm text-slate-300 leading-relaxed space-y-2",
                        htmlProse,
                      )}
                      dangerouslySetInnerHTML={{ __html: col.body }}
                    />
                  )}
                </div>
              );
            })}
          </div>
        </section>
      )}
    </footer>
  );
}
