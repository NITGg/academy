"use client";

import Link from "next/link";
import Image from "next/image";
import { useTranslations, useLocale } from "next-intl";

export function AppFooter() {
  const tApp = useTranslations("app");
  const tFooter = useTranslations("footer");
  const locale = useLocale();

  return (
    <footer className="mt-auto border-t border-border bg-card/80 backdrop-blur-sm py-6 lg:py-8">
      <div className="container mx-auto px-4 lg:px-6">
        <div className="flex flex-col items-center justify-between gap-6 md:flex-row md:gap-4">
          
          {/* Logo & Brand (Right side in RTL) */}
          <Link href="/" className="flex items-center gap-3 shrink-0 group">
            <div className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-xs transition-transform group-hover:scale-105">
              <Image
                src="/assets/logoW.svg"
                alt="EA"
                width={22}
                height={22}
              />
            </div>
            <div className="flex flex-col text-start">
              <span className="text-base font-bold tracking-tight text-foreground">
                {tApp("name")}
              </span>
              <span className="text-xs text-muted-foreground">
                {tApp("tagline")}
              </span>
            </div>
          </Link>

          {/* 2 Sentences (Middle) */}
          <div className="flex flex-col items-center text-center max-w-xl space-y-1.5 px-2">
            <p className="text-sm font-medium text-foreground/90 leading-relaxed">
              {tFooter("description")}
            </p>
            <p className="text-xs text-muted-foreground dir-ltr font-sans">
              {tFooter("copyright")}
            </p>
          </div>

          {/* Social Media Links (Left side in RTL) */}
          <div className="flex items-center gap-2.5 shrink-0">
            {/* N.I.T Link */}
            <a
              href="https://nit.com.eg"
              target="_blank"
              rel="noopener noreferrer"
              className="flex size-9 items-center justify-center rounded-lg bg-muted border border-border text-foreground font-extrabold text-[11px] tracking-tight shadow-xs hover:-translate-y-0.5 hover:bg-muted/80 transition-all"
              aria-label="N.I.T"
              title="N.I.T"
            >
              N.I.T
            </a>

            {/* Facebook */}
            <a
              href="https://facebook.com"
              target="_blank"
              rel="noopener noreferrer"
              className="flex size-9 items-center justify-center rounded-lg bg-[#1877F2] text-white shadow-xs hover:-translate-y-0.5 hover:bg-[#166fe5] transition-all"
              aria-label="Facebook"
              title="Facebook"
            >
              <svg className="size-4 fill-current" viewBox="0 0 24 24">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
            </a>

            {/* WhatsApp */}
            <a
              href="https://wa.me/201000000000"
              target="_blank"
              rel="noopener noreferrer"
              className="flex size-9 items-center justify-center rounded-lg bg-[#25D366] text-white shadow-xs hover:-translate-y-0.5 hover:bg-[#22c55e] transition-all"
              aria-label="WhatsApp"
              title="WhatsApp"
            >
              <svg className="size-4 fill-current" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.096 3.2 5.077 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.572-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.461c-1.926 0-3.71-.512-5.253-1.402l-.376-.217-3.905 1.024 1.042-3.805-.239-.38C2.398 15.5 1.796 13.567 1.796 11.53c0-5.38 4.379-9.759 9.76-9.759 2.605 0 5.054 1.015 6.897 2.859 1.843 1.844 2.857 4.293 2.857 6.898 0 5.381-4.379 9.759-9.759 9.759m0-20.985C5.467.858.005 6.32.005 13.083c0 2.16.56 4.269 1.624 6.133L.005 24l4.908-1.287a12.186 12.186 0 005.811 1.472c6.762 0 12.224-5.462 12.224-12.225C22.948 5.462 17.486.858 10.723.858z"/>
              </svg>
            </a>

            {/* LinkedIn */}
            <a
              href="https://linkedin.com"
              target="_blank"
              rel="noopener noreferrer"
              className="flex size-9 items-center justify-center rounded-lg bg-[#0A66C2] text-white shadow-xs hover:-translate-y-0.5 hover:bg-[#09519a] transition-all"
              aria-label="LinkedIn"
              title="LinkedIn"
            >
              <svg className="size-4 fill-current" viewBox="0 0 24 24">
                <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-0.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/>
              </svg>
            </a>
          </div>

        </div>
      </div>
    </footer>
  );
}
