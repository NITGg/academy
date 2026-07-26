import type { Metadata } from "next";
import { Cairo, Baloo_Bhaijaan_2 } from "next/font/google";
import { getLocale, getMessages } from "next-intl/server";
import { NextIntlClientProvider } from "next-intl";
import "./globals.css";
import { Providers } from "./providers";

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

export const metadata: Metadata = {
  title: {
    default: "Excellence Academy | أكاديمية التميز",
    template: "%s | Excellence Academy",
  },
  description: "Learn with the best teachers — تعلم مع أفضل المدرسين",
};

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const locale = await getLocale();
  const messages = await getMessages();
  const dir = locale === "ar" ? "rtl" : "ltr";

  return (
    <html
      lang={locale}
      dir={dir}
      className={`${cairo.variable} ${balooBhaijaan.variable} h-full`}
      suppressHydrationWarning
    >
      <head>
        {/* Inline script: read stored theme before first paint to avoid FOUC */}
        <script
          dangerouslySetInnerHTML={{
            __html: `
(function(){try{
  var s=localStorage.getItem('ea-theme');
  if(s){var v=JSON.parse(s).state?.variant;
    var r=v==='dark'?'dark':v==='kids'?'kids':v==='system'?
      (window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light'):'light';
    document.documentElement.setAttribute('data-theme',r);
    if(r==='dark')document.documentElement.classList.add('dark');
  }
}catch(e){}})();
            `.trim(),
          }}
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
