import type { Metadata } from "next";
import { Cairo, Baloo_Bhaijaan_2 } from "next/font/google";
import { getLocale, getMessages } from "next-intl/server";
import { NextIntlClientProvider } from "next-intl";
import "./globals.css";
import { Providers } from "./providers";
import { getThemeTokens, themeTokensToCss } from "@/lib/theme-tokens";
import { getThemeLogoSettings } from "@/lib/theme-logo";

const cairo = Cairo({
  subsets: ["arabic", "latin"],
  variable: "--font-cairo",
  display: "swap",
  weight: ["300", "400", "500", "600", "700", "800"],
});

// Kids mode font — playful, bubbly, covers Latin + Arabic
const balooBhaijaan = Baloo_Bhaijaan_2({
  subsets: ["arabic", "latin"],
  variable: "--font-baloo",
  display: "swap",
  weight: ["400", "500", "600", "700", "800"],
});

const basePath = process.env.NEXT_PUBLIC_BASE_PATH || "";

// Built-in fallback icons used when the Moodle theme provides no logo.
const fallbackIcons: Metadata["icons"] = {
  icon: [
    { url: `${basePath}/assets/logo.svg`, type: "image/svg+xml" },
    { url: `${basePath}/icon.svg`, type: "image/svg+xml" },
  ],
  shortcut: `${basePath}/assets/logo.svg`,
  apple: `${basePath}/assets/logo.svg`,
};

export async function generateMetadata(): Promise<Metadata> {
  // Mirror the navbar logo (app-header.tsx): prefer the mobile logo, then the
  // primary/secondary header logos, so the browser-tab icon matches the brand.
  const logo = await getThemeLogoSettings();
  const rawBrandLogo =
    logo.headerlogo_mobile || logo.headerlogo1 || logo.headerlogo2;

  // The favicon <link> is fetched directly by the browser, so its protocol must
  // match the page. getThemeLogoSettings normalizes protocol-relative URLs to
  // http:, which the browser blocks as mixed content on an https page. Strip the
  // protocol back to protocol-relative ("//host/…") so it follows the page (https
  // in production, http on localhost). next/image handles this for the navbar,
  // but a favicon has no such proxy.
  const brandLogo = rawBrandLogo?.replace(/^https?:/, "");

  return {
    title: {
      default: "Excellence Academy | أكاديمية التميز",
      template: "%s | Excellence Academy",
    },
    description: "Learn with the best teachers — تعلم مع أفضل المدرسين",
    icons: brandLogo
      ? { icon: brandLogo, shortcut: brandLogo, apple: brandLogo }
      : fallbackIcons,
  };
}

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const locale = await getLocale();
  const messages = await getMessages();
  const dir = locale === "ar" ? "rtl" : "ltr";

  // Mirror the Moodle (edumy) brand colours: inject them as CSS vars that globals.css maps onto
  // --primary/--accent/etc. When an admin changes a theme colour, the frontend reflects it.
  const themeTokensCss = themeTokensToCss(await getThemeTokens());

  const themeInitScript = `
(function(){try{
  var s=localStorage.getItem('ea-theme');
  if(s){var v=JSON.parse(s).state?.variant;
    var r=v==='dark'?'dark':v==='kids'?'kids':v==='system'?
      (window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light'):'light';
    document.documentElement.setAttribute('data-theme',r);
    if(r==='dark')document.documentElement.classList.add('dark');
  }
}catch(e){}})();
  `.trim();

  return (
    <html
      lang={locale}
      dir={dir}
      className={`${cairo.variable} ${balooBhaijaan.variable} h-full`}
      suppressHydrationWarning
    >
      <head>
        {themeTokensCss && (
          <style
            id="edumy-theme-tokens"
            suppressHydrationWarning
            dangerouslySetInnerHTML={{ __html: themeTokensCss }}
          />
        )}
        <script
          id="theme-init"
          dangerouslySetInnerHTML={{ __html: themeInitScript }}
        />
      </head>
      <body className="min-h-full font-sans antialiased bg-background text-foreground">
        <NextIntlClientProvider messages={messages} locale={locale}>
          <Providers>{children}</Providers>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
