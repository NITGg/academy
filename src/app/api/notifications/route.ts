import { NextResponse } from "next/server";
import { getSessionFromCookie } from "@/lib/session";
import {
  getNotifications,
  getUnreadNotificationCount,
  markNotificationRead,
  markAllNotificationsRead,
} from "@/features/notifications/server";

export async function GET() {
  const session = await getSessionFromCookie();
  if (!session) {
    return NextResponse.json(
      { notifications: [], unreadCount: 0 },
      { status: 401 }
    );
  }

  const [notifications, unreadCount] = await Promise.all([
    getNotifications(session.wstoken, session.user.id),
    getUnreadNotificationCount(session.wstoken, session.user.id),
  ]);

  return NextResponse.json({ notifications, unreadCount });
}

export async function POST(req: Request) {
  const session = await getSessionFromCookie();
  if (!session) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const body = await req.json();
    if (body.action === "mark_all_read") {
      const ok = await markAllNotificationsRead(
        session.wstoken,
        session.user.id
      );
      return NextResponse.json({ success: ok });
    }

    if (body.action === "mark_read" && body.notificationId) {
      const ok = await markNotificationRead(
        session.wstoken,
        body.notificationId
      );
      return NextResponse.json({ success: ok });
    }

    return NextResponse.json({ error: "Invalid action" }, { status: 400 });
  } catch {
    return NextResponse.json({ error: "Server error" }, { status: 500 });
  }
}
