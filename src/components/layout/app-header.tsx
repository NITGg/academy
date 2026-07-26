"use client";

import { useTranslations, useLocale } from "next-intl";
import { Bell, MessageCircle, Search, Menu } from "lucide-react";
import { Button } from "@/components/ui/button";
import { buttonVariants } from "@/components/ui/button";
import Link from "next/link";
import Image from "next/image";
import { cn } from "@/lib/utils";

interface AppHeaderProps {
  onMenuClick?: () => void;
}

export function AppHeader({ onMenuClick }: AppHeaderProps) {
  const t = useTranslations();
  const locale = useLocale();

  return (
    <header className="sticky top-0 z-30 flex h-[var(--header-height)] items-center gap-3 border-b border-border bg-background/80 px-4 backdrop-blur-sm lg:px-6">
      {/* Mobile menu trigger */}
      <Button
        variant="ghost"
        size="icon"
        className="lg:hidden"
        onClick={onMenuClick}
        aria-label="Open menu"
      >
        <Menu className="size-5" />
      </Button>

      {/* Mobile logo */}
      <div className="flex items-center gap-2 lg:hidden">
        <div className="flex size-8 items-center justify-center rounded-lg bg-primary">
          <Image
            src="/assets/logoW.svg"
            alt="EA"
            width={18}
            height={18}
            priority
          />
        </div>
      </div>

      {/* Search bar — desktop */}
      <div className="hidden flex-1 max-w-md lg:flex">
        <div className="relative w-full">
          <Search className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <input
            type="search"
            placeholder={t("common.searchCourse")}
            className="h-9 w-full rounded-lg border border-input bg-muted/50 ps-9 pe-4 text-caption text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring/50 transition-shadow"
            dir={locale === "ar" ? "rtl" : "ltr"}
          />
        </div>
      </div>

      <div className="ms-auto flex items-center gap-1">
        {/* Search — mobile */}
        <Button variant="ghost" size="icon" className="lg:hidden" aria-label={t("common.search")}>
          <Search className="size-5" />
        </Button>

        {/* Messages */}
        <Link
          href="/messages"
          aria-label={t("nav.messages")}
          className={cn(buttonVariants({ variant: "ghost", size: "icon" }))}
        >
          <MessageCircle className="size-5" />
        </Link>

        {/* Notifications */}
        <Link
          href="/notifications"
          aria-label={t("nav.notifications")}
          className={cn(buttonVariants({ variant: "ghost", size: "icon" }), "relative")}
        >
          <Bell className="size-5" />
          <span className="absolute end-1.5 top-1.5 size-2 rounded-full bg-destructive ring-2 ring-background" />
        </Link>

        {/* User avatar — placeholder until auth is wired */}
        <button
          className="ms-1 flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary text-caption font-semibold hover:bg-primary/20 transition-colors"
          aria-label="User menu"
        >
          م
        </button>
      </div>
    </header>
  );
}
