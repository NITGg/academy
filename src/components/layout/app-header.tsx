"use client";

import { useState, useRef, useEffect } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useTranslations, useLocale } from "next-intl";
import { useLocaleStore } from "@/store/useLocaleStore";
import { useThemeStore, type ThemeVariant } from "@/store/useThemeStore";
import { useAuthStore } from "@/store/useAuthStore";
import Image from "next/image";
import logoW from "@/../public/assets/logoW.svg";
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
  Search,
  Menu,
  X,
  ChevronDown,
  Globe,
  LogOut,
  User,
  Sun,
  Moon,
  Monitor,
  Baby,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { buttonVariants } from "@/components/ui/button";
import { NotificationPopover } from "./notification-popover";

/* ── Nav data ─────────────────────────────────────────────────────────── */

const navItems = [
  { href: "/", icon: Home, key: "home" },
  { href: "/courses", icon: BookOpen, key: "courses" },
  { href: "/teachers", icon: Users, key: "teachers" },
  { href: "/calendar", icon: Calendar, key: "calendar" },
  { href: "/lessons", icon: Video, key: "lessons" },
  // { href: "/messages", icon: MessageCircle, key: "messages" },
  { href: "/packages", icon: Package, key: "packages" },
  { href: "/subscriptions", icon: CreditCard, key: "subscriptions" },
  { href: "/programs", icon: GraduationCap, key: "programs" },
  { href: "/payments", icon: Receipt, key: "payments" },
] as const;

const secondaryNavItems: Array<{
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  key: string;
}> = [];

const themeOptions: {
  value: ThemeVariant;
  icon: typeof Sun;
  labelAr: string;
  labelEn: string;
}[] = [
  { value: "system", icon: Monitor, labelAr: "النظام", labelEn: "System" },
  { value: "light", icon: Sun, labelAr: "فاتح", labelEn: "Light" },
  { value: "dark", icon: Moon, labelAr: "داكن", labelEn: "Dark" },
  { value: "kids", icon: Baby, labelAr: "الأطفال", labelEn: "Kids" },
];

/* ── Component ────────────────────────────────────────────────────────── */

export function AppHeader() {
  const t = useTranslations();
  const tNav = useTranslations("nav");
  const locale = useLocale();
  const pathname = usePathname();

  const router = useRouter();
  const { setLocale } = useLocaleStore();
  const { variant, setTheme } = useThemeStore();
  const logout = useAuthStore((state) => state.logout);

  const isRtl = locale === "ar";

  const handleSearch = (e: React.FormEvent, ref: React.RefObject<HTMLInputElement | null>) => {
    e.preventDefault();
    const q = ref.current?.value.trim() ?? "";
    if (q) router.push(`/courses?search=${encodeURIComponent(q)}`);
    setMobileSearchOpen(false);
    setMobileOpen(false);
  };

  /* Local UI state */
  const [mobileOpen, setMobileOpen] = useState(false);
  const [moreOpen, setMoreOpen] = useState(false);
  const [userOpen, setUserOpen] = useState(false);
  const [mobileSearchOpen, setMobileSearchOpen] = useState(false);

  const moreRef = useRef<HTMLDivElement>(null);
  const userRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);
  const mobileSearchRef = useRef<HTMLInputElement>(null);

  const isActive = (href: string) =>
    href === "/" ? pathname === "/" : pathname.startsWith(href);

  /* Close dropdowns on outside click */
  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (moreRef.current && !moreRef.current.contains(e.target as Node))
        setMoreOpen(false);
      if (userRef.current && !userRef.current.contains(e.target as Node))
        setUserOpen(false);
    }
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  /* Lock body scroll when mobile drawer is open */
  useEffect(() => {
    if (mobileOpen) document.body.style.overflow = "hidden";
    else document.body.style.overflow = "";
    return () => {
      document.body.style.overflow = "";
    };
  }, [mobileOpen]);

  return (
    <>
      {/* ═══════════════════  Sticky Header  ═══════════════════ */}
      <header className="sticky top-0 z-30 border-b border-border bg-background/80 backdrop-blur-sm">
        {/* ── Row 1: Logo | Desktop Nav | Actions ── */}
        <div className="flex h-[var(--header-height)] items-center gap-3 px-4 lg:px-6">
          {/* Mobile hamburger */}
          <Button
            variant="ghost"
            size="icon"
            className="lg:hidden"
            onClick={() => setMobileOpen(true)}
            aria-label="Open menu"
          >
            <Menu className="size-5" />
          </Button>

          {/* Logo */}
          <Link href="/" className="flex items-center gap-2 shrink-0">
            <div className="flex size-8 items-center justify-center rounded-lg bg-primary">
              <Image
                src={logoW}
                alt="EA"
                width={18}
                height={18}
                priority
              />
            </div>
            <span className="text-body-strong hidden sm:inline">
              {isRtl ? "أكاديمية التميز" : "Excellence Academy"}
            </span>
          </Link>

          {/* Desktop primary nav */}
          <nav className="hidden lg:flex items-center gap-0.5 ms-3">
            {navItems.map(({ href, key }) => {
              const active = isActive(href);
              return (
                <Link
                  key={key}
                  href={href}
                  className={cn(
                    "whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                    active
                      ? "bg-primary/10 text-primary"
                      : "text-muted-foreground hover:bg-muted hover:text-foreground",
                  )}
                >
                  {tNav(key)}
                </Link>
              );
            })}

            {/* More dropdown (secondary nav) */}
            {/* <div className="relative" ref={moreRef}>
              <button
                onClick={() => {
                  setMoreOpen((v) => !v);
                  setUserOpen(false);
                }}
                className="flex items-center gap-1 whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
              >
                <span>{isRtl ? "المزيد" : "More"}</span>
                <ChevronDown
                  className={cn(
                    "size-3 transition-transform",
                    moreOpen && "rotate-180",
                  )}
                />
              </button>

              {moreOpen && (
                <div className="absolute top-full start-0 mt-1.5 w-52 rounded-xl border border-border bg-popover p-1.5 shadow-lg z-50">
                  {secondaryNavItems.map(({ href, icon: Icon, key }) => (
                    <Link
                      key={key}
                      href={href}
                      onClick={() => setMoreOpen(false)}
                      className={cn(
                        "flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
                        isActive(href)
                          ? "bg-primary/10 text-primary"
                          : "text-popover-foreground hover:bg-muted",
                      )}
                    >
                      <Icon className="size-4 shrink-0" />
                      <span>{tNav(key)}</span>
                    </Link>
                  ))}
                </div>
              )}
            </div> */}
          </nav>

          {/* Right actions */}
          <div className="ms-auto flex items-center gap-1">
            {/* Search — desktop */}
            <form
              onSubmit={(e) => handleSearch(e, searchRef)}
              className="hidden md:flex relative max-w-xs"
            >
              <Search className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
              <input
                ref={searchRef}
                type="search"
                placeholder={t("common.searchCourse")}
                className="h-9 w-full rounded-lg border border-input bg-muted/50 ps-9 pe-4 text-caption text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/50 transition-shadow"
                dir={isRtl ? "rtl" : "ltr"}
              />
            </form>

            {/* Search — mobile toggle */}
            <Button
              variant="ghost"
              size="icon"
              className="md:hidden"
              aria-label={t("common.search")}
              onClick={() => setMobileSearchOpen((v) => !v)}
            >
              <Search className="size-5" />
            </Button>

            {/* Messages */}
            <Link
              href="/messages"
              aria-label={tNav("messages")}
              className={cn(buttonVariants({ variant: "ghost", size: "icon" }))}
            >
              <MessageCircle className="size-5" />
            </Link>

            {/* Notifications Popover */}
            <NotificationPopover />

            {/* User dropdown */}
            <div className="relative" ref={userRef}>
              <button
                onClick={() => {
                  setUserOpen((v) => !v);
                  setMoreOpen(false);
                }}
                className="ms-1 flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary text-caption font-semibold hover:bg-primary/20 transition-colors"
                aria-label="User menu"
              >
                م
              </button>

              {userOpen && (
                <div className="absolute end-0 top-full mt-2 w-64 rounded-xl border border-border bg-popover p-2 shadow-lg z-50">
                  {/* Theme switcher */}
                  <div className="px-2 py-2">
                    <span className="text-small font-medium text-muted-foreground">
                      {isRtl ? "السمة" : "Theme"}
                    </span>
                    <div className="mt-1.5 grid grid-cols-4 gap-1">
                      {themeOptions.map(
                        ({ value, icon: Icon, labelAr, labelEn }) => (
                          <button
                            key={value}
                            onClick={() => setTheme(value)}
                            className={cn(
                              "flex flex-col items-center gap-1 rounded-lg py-1.5 text-[11px] font-medium transition-colors",
                              variant === value
                                ? "bg-primary/10 text-primary"
                                : "text-muted-foreground hover:bg-muted",
                            )}
                            title={isRtl ? labelAr : labelEn}
                          >
                            <Icon className="size-4" />
                            <span className="truncate">
                              {isRtl ? labelAr : labelEn}
                            </span>
                          </button>
                        ),
                      )}
                    </div>
                  </div>

                  <div className="my-1.5 border-t border-border" />

                  {/* Language toggle */}
                  <button
                    onClick={() => {
                      setLocale(isRtl ? "en" : "ar");
                      setUserOpen(false);
                    }}
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-caption font-medium text-popover-foreground transition-colors hover:bg-muted"
                  >
                    <Globe className="size-4 text-muted-foreground" />
                    <span>{isRtl ? "English" : "العربية"}</span>
                  </button>

                  {/* Profile */}
                  <Link
                    href="/profile"
                    onClick={() => setUserOpen(false)}
                    className={cn(
                      "flex items-center gap-3 rounded-lg px-3 py-2 text-caption font-medium transition-colors",
                      isActive("/profile")
                        ? "bg-primary/10 text-primary"
                        : "text-popover-foreground hover:bg-muted",
                    )}
                  >
                    <User className="size-4 text-muted-foreground" />
                    <span>{tNav("profile")}</span>
                  </Link>

                  <div className="my-1.5 border-t border-border" />

                  {/* Logout */}
                  <button
                    onClick={() => {
                      logout();
                      setUserOpen(false);
                    }}
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-caption font-medium text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                  >
                    <LogOut className="size-4" />
                    <span>{isRtl ? "تسجيل الخروج" : "Sign Out"}</span>
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Mobile search bar — slides in below the header row */}
        {mobileSearchOpen && (
          <form
            onSubmit={(e) => handleSearch(e, mobileSearchRef)}
            className="md:hidden border-t border-border px-4 py-2"
          >
            <div className="relative">
              <Search className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
              <input
                ref={mobileSearchRef}
                type="search"
                autoFocus
                placeholder={t("common.searchCourse")}
                className="h-9 w-full rounded-lg border border-input bg-muted/50 ps-9 pe-4 text-caption text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/50 transition-shadow"
                dir={isRtl ? "rtl" : "ltr"}
              />
            </div>
          </form>
        )}
      </header>

      {/* ═══════════════════  Mobile Full Drawer  ═══════════════════ */}
      {mobileOpen && (
        <>
          {/* Overlay */}
          <div
            className="fixed inset-0 z-40 bg-black/40 lg:hidden"
            onClick={() => setMobileOpen(false)}
          />

          {/* Drawer */}
          <div
            className={cn(
              "fixed inset-y-0 start-0 z-50 w-80 max-w-[85vw] transform transition-transform duration-200 ease-out bg-background border-e border-border overflow-y-auto lg:hidden",
              mobileOpen
                ? "translate-x-0"
                : isRtl
                  ? "translate-x-full"
                  : "-translate-x-full",
            )}
          >
            {/* Drawer header */}
            <div className="flex h-[var(--header-height)] items-center gap-3 border-b border-border px-4">
              <div className="flex size-8 items-center justify-center rounded-lg bg-primary">
                <Image
                  src={logoW}
                  alt="EA"
                  width={18}
                  height={18}
                  priority
                />
              </div>
              <span className="text-body-strong">
                {isRtl ? "أكاديمية التميز" : "Excellence Academy"}
              </span>
              <button
                onClick={() => setMobileOpen(false)}
                className="ms-auto p-2 rounded-lg hover:bg-muted transition-colors"
              >
                <X className="size-5" />
              </button>
            </div>

            <div className="p-4 space-y-5">
              {/* Primary */}
              <ul className="space-y-0.5">
                {navItems.map(({ href, icon: Icon, key }) => (
                  <li key={key}>
                    <Link
                      href={href}
                      onClick={() => setMobileOpen(false)}
                      className={cn(
                        "flex items-center gap-3 rounded-lg px-3 py-2.5 text-caption font-medium transition-colors",
                        isActive(href)
                          ? "bg-primary/10 text-primary"
                          : "text-foreground hover:bg-muted",
                      )}
                    >
                      <Icon className="size-5 shrink-0" />
                      <span>{tNav(key)}</span>
                    </Link>
                  </li>
                ))}
              </ul>

              <div className="border-t border-border" />

              {/* Secondary */}
              <ul className="space-y-0.5">
                {secondaryNavItems.map(({ href, icon: Icon, key }) => (
                  <li key={key}>
                    <Link
                      href={href}
                      onClick={() => setMobileOpen(false)}
                      className={cn(
                        "flex items-center gap-3 rounded-lg px-3 py-2.5 text-caption font-medium transition-colors",
                        isActive(href)
                          ? "bg-primary/10 text-primary"
                          : "text-foreground hover:bg-muted",
                      )}
                    >
                      <Icon className="size-5 shrink-0" />
                      <span>{tNav(key)}</span>
                    </Link>
                  </li>
                ))}
              </ul>

              <div className="border-t border-border" />

              {/* Theme */}
              <div>
                <span className="text-small font-medium text-muted-foreground px-3 block">
                  {isRtl ? "السمة" : "Theme"}
                </span>
                <div className="mt-2 grid grid-cols-4 gap-1 px-3">
                  {themeOptions.map(
                    ({ value, icon: Icon, labelAr, labelEn }) => (
                      <button
                        key={value}
                        onClick={() => setTheme(value)}
                        className={cn(
                          "flex flex-col items-center gap-1 rounded-lg py-2 text-[11px] font-medium transition-colors",
                          variant === value
                            ? "bg-primary/10 text-primary"
                            : "text-muted-foreground hover:bg-muted",
                        )}
                      >
                        <Icon className="size-4" />
                        <span>{isRtl ? labelAr : labelEn}</span>
                      </button>
                    ),
                  )}
                </div>
              </div>

              <div className="border-t border-border" />

              {/* Lang | Profile | Logout */}
              <ul className="space-y-0.5">
                <li>
                  <button
                    onClick={() => {
                      setLocale(isRtl ? "en" : "ar");
                      setMobileOpen(false);
                    }}
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-caption font-medium text-foreground transition-colors hover:bg-muted"
                  >
                    <Globe className="size-5 shrink-0 text-muted-foreground" />
                    <span>{isRtl ? "English" : "العربية"}</span>
                  </button>
                </li>
                <li>
                  <Link
                    href="/profile"
                    onClick={() => setMobileOpen(false)}
                    className={cn(
                      "flex items-center gap-3 rounded-lg px-3 py-2.5 text-caption font-medium transition-colors",
                      isActive("/profile")
                        ? "bg-primary/10 text-primary"
                        : "text-foreground hover:bg-muted",
                    )}
                  >
                    <User className="size-5 shrink-0 text-muted-foreground" />
                    <span>{tNav("profile")}</span>
                  </Link>
                </li>
                <li>
                  <button
                    onClick={() => {
                      logout();
                      setMobileOpen(false);
                    }}
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-caption font-medium text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                  >
                    <LogOut className="size-5 shrink-0" />
                    <span>{isRtl ? "تسجيل الخروج" : "Sign Out"}</span>
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </>
      )}
    </>
  );
}
