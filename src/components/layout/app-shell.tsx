"use client";

import { useState } from "react";
import { AppSidebar } from "./app-sidebar";
import { AppHeader } from "./app-header";
import { cn } from "@/lib/utils";
import { useLocale } from "next-intl";
import { MessageCircle } from "lucide-react";

interface AppShellProps {
  children: React.ReactNode;
}

export function AppShell({ children }: AppShellProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const locale = useLocale();

  return (
    <div className="relative flex h-screen overflow-hidden bg-background">
      {/* Desktop sidebar — fixed on inline-start */}
      <div className="hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 lg:start-0 lg:z-40 lg:w-[var(--sidebar-width)]">
        <AppSidebar />
      </div>

      {/* Mobile sidebar overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Mobile sidebar drawer */}
      <div
        className={cn(
          "fixed inset-y-0 start-0 z-50 w-[var(--sidebar-width)] transform transition-transform duration-200 ease-out lg:hidden",
          sidebarOpen ? "translate-x-0" : locale === "ar" ? "translate-x-full" : "-translate-x-full"
        )}
      >
        <AppSidebar />
      </div>

      {/* Main area — offset by sidebar on desktop */}
      <div className="flex flex-1 flex-col overflow-hidden lg:ms-[var(--sidebar-width)]">
        <AppHeader onMenuClick={() => setSidebarOpen((v) => !v)} />

        <main className="flex-1 overflow-y-auto">
          <div className="container mx-auto px-4 py-6 lg:px-6 lg:py-8">
            {children}
          </div>
        </main>
      </div>

      {/* WhatsApp floating button — matches the mobile app */}
      <a
        href="https://wa.me/201000000000"
        target="_blank"
        rel="noopener noreferrer"
        className="fixed bottom-20 end-4 z-50 flex size-12 items-center justify-center rounded-full bg-[#25D366] text-white shadow-raised hover:bg-[#22c55e] transition-colors lg:bottom-6"
        aria-label="WhatsApp"
      >
        <MessageCircle className="size-6 fill-white stroke-none" />
      </a>
    </div>
  );
}
