import type { Metadata } from "next";
import { MessageCircle } from "lucide-react";

export const metadata: Metadata = { title: "الرسائل" };

export default function MessagesPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <MessageCircle className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الرسائل</h1>
      </div>
      <p className="text-muted-foreground text-caption">محادثاتك مع المدرسين — قريباً.</p>
    </div>
  );
}
