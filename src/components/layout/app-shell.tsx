"use client";

import { AppHeader } from "./app-header";
import { MessageCircle } from "lucide-react";
import { PaymentResultToast } from "./PaymentResultToast";

interface AppShellProps {
  children: React.ReactNode;
}

export function AppShell({ children }: AppShellProps) {
  return (
    <div className="relative flex h-screen flex-col overflow-hidden bg-background">
      {/* Surfaces a toast when returning from the payment gateway (?payment=success|failed) */}
      <PaymentResultToast />

      {/* Sticky header + navbar combined */}
      <AppHeader />

      {/* Main area — full width, no sidebar offset */}
      <main className="flex-1 overflow-y-auto">
        <div className="container mx-auto px-4 py-6 lg:px-6 lg:py-8">
          {children}
        </div>
      </main>

      {/* WhatsApp floating button */}
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