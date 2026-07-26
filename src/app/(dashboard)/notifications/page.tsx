import type { Metadata } from "next";
import { Bell } from "lucide-react";

export const metadata: Metadata = { title: "الإشعارات" };

export default function NotificationsPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Bell className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الإشعارات</h1>
      </div>
      <p className="text-muted-foreground text-caption">إشعاراتك وتنبيهاتك — قريباً.</p>
    </div>
  );
}
