"use client";

import { useState, useRef, useEffect } from "react";
import Link from "next/link";
import { Bell, CheckCheck, Loader2, ExternalLink } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { AppNotification } from "@/features/notifications/types";

function formatTime(ts: number): string {
  if (!ts) return "";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const hours = String(d.getHours()).padStart(2, "0");
  const minutes = String(d.getMinutes()).padStart(2, "0");
  return `${hours}:${minutes} ${d.getFullYear()}/${month}/${day}`;
}

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, "").replace(/&nbsp;/g, " ").trim();
}

export function NotificationPopover() {
  const locale = useLocale();
  const tNav = useTranslations("nav");
  const isAr = locale === "ar";

  const [open, setOpen] = useState(false);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [fetched, setFetched] = useState(false);

  const containerRef = useRef<HTMLDivElement>(null);

  /* Fetch notifications on mount and whenever popover opens */
  async function fetchNotifications() {
    setLoading(true);
    try {
      const res = await fetch("/api/notifications");
      if (res.ok) {
        const data = await res.json();
        setNotifications(data.notifications ?? []);
        setUnreadCount(data.unreadCount ?? 0);
      }
    } catch {
      // ignore error
    } finally {
      setLoading(false);
      setFetched(true);
    }
  }

  useEffect(() => {
    fetchNotifications();
  }, []);

  const handleToggle = () => {
    if (!open && !fetched) {
      fetchNotifications();
    }
    setOpen((v) => !v);
  };

  /* Close on outside click */
  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (
        containerRef.current &&
        !containerRef.current.contains(e.target as Node)
      ) {
        setOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  async function handleMarkAllAsRead() {
    try {
      await fetch("/api/notifications", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "mark_all_read" }),
      });
      setNotifications((prev) => prev.map((n) => ({ ...n, isRead: true })));
      setUnreadCount(0);
    } catch {
      // ignore error
    }
  }

  async function handleMarkAsRead(id: number) {
    try {
      await fetch("/api/notifications", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "mark_read", notificationId: id }),
      });
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, isRead: true } : n))
      );
      setUnreadCount((prev) => Math.max(0, prev - 1));
    } catch {
      // ignore error
    }
  }

  return (
    <div className="relative" ref={containerRef}>
      {/* Bell Trigger Button */}
      <button
        onClick={handleToggle}
        aria-label={tNav("notifications")}
        className={cn(
          buttonVariants({ variant: "ghost", size: "icon" }),
          "relative"
        )}
      >
        <Bell className="size-5" />
        {unreadCount > 0 && (
          <span className="absolute end-1 top-1 flex size-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-destructive-foreground ring-2 ring-background">
            {unreadCount > 9 ? "9+" : unreadCount}
          </span>
        )}
      </button>

      {/* Popover Dropdown */}
      {open && (
        <div className="absolute end-0 top-full mt-2 w-80 sm:w-96 rounded-2xl border border-border bg-popover p-3 shadow-xl z-50">
          {/* Popover Header */}
          <div className="flex items-center justify-between pb-2.5 border-b border-border px-1">
            <div className="flex items-center gap-2">
              <span className="text-body-strong font-bold text-popover-foreground">
                {isAr ? "الإشعارات" : "Notifications"}
              </span>
              {unreadCount > 0 && (
                <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">
                  {unreadCount}
                </span>
              )}
            </div>

            {unreadCount > 0 && (
              <button
                onClick={handleMarkAllAsRead}
                className="flex items-center gap-1 text-[11px] font-medium text-primary hover:underline"
              >
                <CheckCheck className="size-3.5" />
                <span>{isAr ? "تحديد الكل كمقروء" : "Mark all as read"}</span>
              </button>
            )}
          </div>

          {/* Notifications List */}
          <div className="my-2 max-h-80 overflow-y-auto space-y-1.5 pe-1">
            {loading && notifications.length === 0 ? (
              <div className="flex min-h-[160px] items-center justify-center text-muted-foreground">
                <Loader2 className="size-5 animate-spin" />
              </div>
            ) : notifications.length === 0 ? (
              <div className="flex min-h-[160px] flex-col items-center justify-center gap-1.5 text-muted-foreground py-6">
                <Bell className="size-8 opacity-20" />
                <p className="text-caption">
                  {isAr ? "لا توجد إشعارات حالية" : "No notifications"}
                </p>
              </div>
            ) : (
              notifications.slice(0, 10).map((n) => {
                const body = n.smallmessage
                  ? stripHtml(n.smallmessage)
                  : stripHtml(n.text ?? "");

                return (
                  <div
                    key={n.id}
                    onClick={() => !n.isRead && handleMarkAsRead(n.id)}
                    className={cn(
                      "group flex items-start gap-2.5 rounded-xl p-2.5 text-start transition-colors",
                      !n.isRead
                        ? "bg-primary/5 hover:bg-primary/10 cursor-pointer"
                        : "hover:bg-muted/60"
                    )}
                  >
                    {/* Status Dot */}
                    <div className="mt-1.5 shrink-0">
                      <span
                        className={cn(
                          "block size-2 rounded-full",
                          n.isRead ? "bg-muted-foreground/30" : "bg-primary"
                        )}
                      />
                    </div>

                    {/* Content */}
                    <div className="min-w-0 flex-1">
                      <p className="text-caption font-semibold leading-tight text-popover-foreground line-clamp-1">
                        {n.subject}
                      </p>
                      {body && (
                        <p className="mt-1 text-[11px] leading-relaxed text-muted-foreground line-clamp-2">
                          {body}
                        </p>
                      )}
                      <p className="mt-1 text-[10px] text-muted-foreground/60">
                        {formatTime(n.timeCreated)}
                      </p>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          {/* Footer View All link */}
          <div className="pt-2 border-t border-border text-center">
            <Link
              href="/notifications"
              onClick={() => setOpen(false)}
              className="inline-flex items-center gap-1.5 text-caption font-semibold text-primary hover:underline py-1"
            >
              <span>{isAr ? "عرض جميع الإشعارات" : "View all notifications"}</span>
              <ExternalLink className="size-3.5" />
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}
