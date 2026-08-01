"use client";

import { useEffect } from "react";
import Link from "next/link";
import Image from "next/image";
import logoW from "@/../public/assets/logoW.svg";
import { useTranslations, useLocale } from "next-intl";
import { cn } from "@/lib/utils";

import { useThemeLogoStore } from "@/store/useThemeLogoStore";
import { useThemeFooterStore } from "@/store/useThemeFooterStore";

/**
 * Basis class per active-column count, mirroring the Edumy theme's Footer 8 grid
 * (ccn_themehandler.php): 4 cols → 1/4, 3 → 1/3, 2 → 1/2, 1 → centered, 5+ → 1/5.
 */
function columnBasis(count: number): string {
  switch (count) {
    case 1:
      return "lg:basis-1/2 lg:mx-auto text-center";
    case 2:
      return "lg:basis-1/2";
    case 3:
      return "lg:basis-1/3";
    case 4:
      return "lg:basis-1/4";
    default:
      return "lg:basis-1/5";
  }
}

export function AppFooter() {
  const tApp = useTranslations("app");
  const tFooter = useTranslations("footer");
  const locale = useLocale();

  const logoSettings = useThemeLogoStore((state) => state.logo);
  const footer = useThemeFooterStore((state) => state.footer);
  const fetchFooterSettings = useThemeFooterStore((state) => state.fetchFooterSettings);

  // Footer content is language-dependent ({mlang}); refetch when the locale changes.
  useEffect(() => {
    fetchFooterSettings(locale);
  }, [locale, fetchFooterSettings]);

  const footerLogoSrc = logoSettings.footerlogo1 || logoSettings.headerlogo1 || logoW;
  const showFooterImage = logoSettings.logo_image_footer !== "1";
  const logoWidth = logoSettings.logo_image_width_footer
    ? Number(logoSettings.logo_image_width_footer)
    : 22;
  const logoHeight = logoSettings.logo_image_height_footer
    ? Number(logoSettings.logo_image_height_footer)
    : 22;

  const columns = (footer.columns ?? []).filter((c) => c.active);
  const basis = columnBasis(columns.length);

  // Shared styling for admin-authored HTML (links, images, headings) injected below.
  // Footer 8 is authored for a dark background (admin HTML uses white text), so the
  // footer is always dark here to match production regardless of the site theme.
  const htmlProse =
    "[&_a]:transition-colors [&_a:hover]:text-primary [&_img]:inline-block [&_img]:max-w-full [&_h4]:text-white [&_h4]:font-semibold [&_h5]:text-slate-300";

  return (
    <footer className="mt-auto border-t border-white/10 bg-[#080f1d] text-slate-300">
      {/* ── Columns row (Footer 8: up to 5 admin-configured columns) ── */}
      {columns.length > 0 && (
        <section className="container mx-auto px-4 lg:px-6 py-10">
          <div className="flex flex-wrap -mx-4 gap-y-8">
            {columns.map((col) => (
              <div key={col.index} className={cn("px-4 basis-full sm:basis-1/2", basis)}>
                {/* Footer logo lives in column 1 (matches ccn_footer_8.mustache) */}
                {col.index === 1 && showFooterImage && (
                  <Link href="/" className="mb-4 inline-flex items-center gap-2">
                    <span className="flex items-center justify-center rounded-xl bg-primary p-1.5">
                      <Image
                        src={footerLogoSrc}
                        alt={tApp("name")}
                        width={logoWidth}
                        height={logoHeight}
                        className="object-contain"
                      />
                    </span>
                    <span className="text-base font-bold tracking-tight text-white">
                      {tApp("name")}
                    </span>
                  </Link>
                )}

                {col.title && (
                  <h4 className="text-body-strong mb-3 text-white">{col.title}</h4>
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
            ))}
          </div>
        </section>
      )}

      {/* ── Bottom bar: copyright | footer menu | social ── */}
      <section className={cn(columns.length > 0 && "border-t border-white/10")}>
        <div className="container mx-auto px-4 lg:px-6 py-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          {/* Copyright (from theme_edumy/cocoon_copyright) */}
          {footer.cocoon_copyright ? (
            <div
              dir="ltr"
              className="text-xs text-slate-400 text-center md:text-start"
              dangerouslySetInnerHTML={{ __html: footer.cocoon_copyright }}
            />
          ) : (
            <p className="text-xs text-slate-400 text-center md:text-start dir-ltr">
              {tFooter("copyright")}
            </p>
          )}

          {/* Footer menu (from theme_edumy/footer_menu), if any */}
          {footer.footer_menu && (
            <nav
              className={cn(
                "text-sm text-slate-300 flex flex-wrap justify-center gap-x-4 gap-y-1",
                htmlProse,
              )}
              dangerouslySetInnerHTML={{ __html: footer.footer_menu }}
            />
          )}

          {/* Social links (kept until the Social tab is wired to the theme) */}
          <div className="flex items-center gap-2.5 shrink-0 justify-center">
            <a
              href="https://www.linkedin.com/company/the-national-company-for-sw-engineering-and-information-technology---nit/about/"
              target="_blank"
              rel="noopener noreferrer"
              className="flex size-9 items-center justify-center rounded-lg bg-[#0A66C2] text-white shadow-xs hover:-translate-y-0.5 hover:bg-[#09519a] transition-all"
              aria-label="LinkedIn"
              title="LinkedIn"
            >
              <svg className="size-4 fill-current" viewBox="0 0 24 24">
                <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-0.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z" />
              </svg>
            </a>
            <a
              href="https://wa.me/201091568240"
              target="_blank"
              rel="noopener noreferrer"
              className="flex size-9 items-center justify-center rounded-lg bg-[#25D366] text-white shadow-xs hover:-translate-y-0.5 hover:bg-[#22c55e] transition-all"
              aria-label="WhatsApp"
              title="WhatsApp"
            >
              <svg className="size-4 fill-current" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.096 3.2 5.077 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.572-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.461c-1.926 0-3.71-.512-5.253-1.402l-.376-.217-3.905 1.024 1.042-3.805-.239-.38C2.398 15.5 1.796 13.567 1.796 11.53c0-5.38 4.379-9.759 9.76-9.759 2.605 0 5.054 1.015 6.897 2.859 1.843 1.844 2.857 4.293 2.857 6.898 0 5.381-4.379 9.759-9.759 9.759m0-20.985C5.467.858.005 6.32.005 13.083c0 2.16.56 4.269 1.624 6.133L.005 24l4.908-1.287a12.186 12.186 0 005.811 1.472c6.762 0 12.224-5.462 12.224-12.225C22.948 5.462 17.486.858 10.723.858z" />
              </svg>
            </a>
            <a
              href="https://www.facebook.com/successAcdmy"
              target="_blank"
              rel="noopener noreferrer"
              className="flex size-9 items-center justify-center rounded-lg bg-[#1877F2] text-white shadow-xs hover:-translate-y-0.5 hover:bg-[#166fe5] transition-all"
              aria-label="Facebook"
              title="Facebook"
            >
              <svg className="size-4 fill-current" viewBox="0 0 24 24">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
              </svg>
            </a>
            <a
              href="https://www.nitg-eg.com/ar"
              target="_blank"
              rel="noopener noreferrer"
              className="flex size-9 items-center justify-center rounded-lg bg-muted border border-border text-foreground font-extrabold text-[11px] tracking-tight shadow-xs hover:-translate-y-0.5 hover:bg-muted/80 transition-all"
              aria-label="N.I.T"
              title="N.I.T"
            >
              N.I.T
            </a>
          </div>
        </div>
      </section>
    </footer>
  );
}
