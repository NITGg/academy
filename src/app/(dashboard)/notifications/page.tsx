import type { Metadata } from "next";
import { getSessionFromCookie } from "@/lib/session";
import { getNotifications } from "@/features/notifications/server";
import { NotificationsPageClient } from "@/features/notifications/components/NotificationsPageClient";

export const metadata: Metadata = { title: "الإشعارات" };

export default async function NotificationsPage() {
  const session = await getSessionFromCookie();

  const notifications = session
    ? await getNotifications(session.wstoken, session.user.id)
    : [];

  return <NotificationsPageClient initialNotifications={notifications} />;
}
