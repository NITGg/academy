import type { Metadata } from "next";
import { Cairo, Baloo_Bhaijaan_2 } from "next/font/google";
import { getLocale, getMessages } from "next-intl/server";
import { NextIntlClientProvider } from "next-intl";
import "./globals.css";
import { Providers } from "./providers";
import { getThemeTokens, themeTokensToCss } from "@/lib/theme-tokens";

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

export const metadata: Metadata = {
  title: {
    default: "Excellence Academy | أكاديمية التميز",
    template: "%s | Excellence Academy",
  },
  description: "Learn with the best teachers — تعلم مع أفضل المدرسين",
  icons: {
    icon: [
      { url: `${basePath}/assets/logo.svg`, type: "image/svg+xml" },
      { url: `${basePath}/icon.svg`, type: "image/svg+xml" },
    ],
    shortcut: `${basePath}/assets/logo.svg`,
    apple: `${basePath}/assets/logo.svg`,
  },
};

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