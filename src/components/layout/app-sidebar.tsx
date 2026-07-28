"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useTranslations, useLocale } from "next-intl";
import { useLocaleStore } from "@/store/useLocaleStore";
import { useThemeStore, type ThemeVariant } from "@/store/useThemeStore";
import { useAuthStore } from "@/store/useAuthStore";
import { cn } from "@/lib/utils";
import {
  Home,
  BookOpen,
  Users,
  Calendar,
  Video,
  MessageCircle,
  Bell,
  Package,
  CreditCard,
  GraduationCap,
  Receipt,
  User,
  ChevronRight,
  ChevronLeft,
  Globe,
  LogOut,
  Sun,
  Moon,
  Monitor,
  Baby,
} from "lucide-react";
import Image from "next/image";

const navItems = [
  { href: "/", icon: Home, key: "home" },
  { href: "/courses", icon: BookOpen, key: "courses" },
  { href: "/teachers", icon: Users, key: "teachers" },
  { href: "/calendar", icon: Calendar, key: "calendar" },
  { href: "/lessons", icon: Video, key: "lessons" },
  { href: "/messages", icon: MessageCircle, key: "messages" },
  { href: "/notifications", icon: Bell, key: "notifications" },
] as const;

const secondaryNavItems = [
  { href: "/packages", icon: Package, key: "packages" },
  { href: "/subscriptions", icon: CreditCard, key: "subscriptions" },
  { href: "/programs", icon: GraduationCap, key: "programs" },
  { href: "/payments", icon: Receipt, key: "payments" },
] as const;

interface AppSidebarProps {
  className?: string;
}

const themeOptions: { value: ThemeVariant; icon: typeof Sun; labelAr: string; labelEn: string }[] = [
  { value: "system", icon: Monitor, labelAr: "النظام", labelEn: "System" },
  { value: "light", icon: Sun, labelAr: "فاتح", labelEn: "Light" },
  { value: "dark", icon: Moon, labelAr: "داكن", labelEn: "Dark" },
  { value: "kids", icon: Baby, labelAr: "الأطفال", labelEn: "Kids" },
];

export function AppSidebar({ className }: AppSidebarProps) {
  const t = useTranslations("nav");
  const pathname = usePathname();
  const locale = useLocale();
  const { setLocale } = useLocaleStore();
  const { variant, setTheme } = useThemeStore();
  const logout = useAuthStore((state) => state.logout);
  const isRtl = locale === "ar";
  const Chevron = isRtl ? ChevronLeft : ChevronRight;

  const isActive = (href: string) =>
    href === "/" ? pathname === "/" : pathname.startsWith(href);

  return (
    <aside
      className={cn(
        "flex h-full w-[var(--sidebar-width)] flex-col border-e border-sidebar-border bg-sidebar",
        className
      )}
    >
      {/* Logo */}
      <div className="flex h-[var(--header-height)] items-center gap-3 border-b border-sidebar-border px-5">
        <div className="flex size-9 items-center justify-center rounded-xl bg-primary">
          <Image
            src="/assets/logoW.svg"
            alt="EA"
            width={24}
            height={24}
            priority
            unoptimized
          />
        </div>
        <span className="text-body-strong text-sidebar-foreground leading-tight">
          {locale === "ar" ? "أكاديمية التميز" : "Excellence Academy"}
        </span>
      </div>

      {/* Primary nav */}
      <nav className="flex-1 overflow-y-auto px-3 py-4">
        <ul className="space-y-1">
          {navItems.map(({ href, icon: Icon, key }) => {
            const active = isActive(href);
            return (
              <li key={key}>
                <Link
                  href={href}
                  className={cn(
                    "group flex items-center gap-3 rounded-lg px-3 py-2.5 text-caption font-medium transition-colors",
                    active
                      ? "bg-primary/10 text-primary"
                      : "text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                  )}
                >
                  <Icon
                    className={cn(
                      "size-5 shrink-0 transition-colors",
                      active ? "text-primary" : "text-muted-foreground group-hover:text-foreground"
                    )}
                  />
                  <span className="flex-1">{t(key)}</span>
                  {active && (
                    <Chevron className="size-4 text-primary opacity-60" />
                  )}
                </Link>
              </li>
            );
          })}
        </ul>

        {/* Divider */}
        <div className="my-3 border-t border-sidebar-border" />

        {/* Secondary nav */}
        <ul className="space-y-1">
          {secondaryNavItems.map(({ href, icon: Icon, key }) => {
            const active = isActive(href);
            return (
              <li key={key}>
                <Link
                  href={href}
                  className={cn(
                    "group flex items-center gap-3 rounded-lg px-3 py-2.5 text-caption font-medium transition-colors",
                    active
                      ? "bg-primary/10 text-primary"
                      : "text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                  )}
                >
                  <Icon
                    className={cn(
                      "size-5 shrink-0",
                      active ? "text-primary" : "text-muted-foreground group-hover:text-foreground"
                    )}
                  />
                  <span className="flex-1">{t(key)}</span>
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>

      {/* Footer */}
      <div className="border-t border-sidebar-border p-3 space-y-2">
        {/* Theme switcher */}
        <div className="px-3 py-2">
          <span className="text-small font-medium text-muted-foreground">
            {isRtl ? "السمة" : "Theme"}
          </span>
          <div className="mt-1.5 flex gap-1">
            {themeOptions.map(({ value, icon: Icon, labelAr, labelEn }) => (
              <button
                key={value}
                onClick={() => setTheme(value)}
                className={cn(
                  "flex flex-1 flex-col items-center gap-1 rounded-lg py-1.5 text-[11px] font-medium transition-colors",
                  variant === value
                    ? "bg-primary/10 text-primary"
                    : "text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                )}
                title={isRtl ? labelAr : labelEn}
              >
                <Icon className="size-4" />
                <span className="truncate">{isRtl ? labelAr : labelEn}</span>
              </button>
            ))}
          </div>
        </div>

        {/* Language toggle */}
        <button
          onClick={() => setLocale(isRtl ? "en" : "ar")}
          className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-caption font-medium text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
        >
          <Globe className="size-5 shrink-0 text-muted-foreground" />
          <span>{isRtl ? "English" : "العربية"}</span>
        </button>

        {/* Profile */}
        <Link
          href="/profile"
          className={cn(
            "flex items-center gap-3 rounded-lg px-3 py-2.5 text-caption font-medium transition-colors",
            isActive("/profile")
              ? "bg-primary/10 text-primary"
              : "text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
          )}
        >
          <User className="size-5 shrink-0 text-muted-foreground" />
          <span>{t("profile")}</span>
        </Link>

        {/* Logout */}
        <button
          onClick={() => logout()}
          className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-caption font-medium text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
        >
          <LogOut className="size-5 shrink-0" />
          <span>{locale === "ar" ? "تسجيل الخروج" : "Sign Out"}</span>
        </button>
      </div>
    </aside>
  );
}
