import type { Metadata } from "next";
import { User } from "lucide-react";

export const metadata: Metadata = { title: "الملف الشخصي" };

export default function ProfilePage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <User className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">الملف الشخصي</h1>
      </div>
      <p className="text-muted-foreground text-caption">إعدادات الحساب والبيانات الشخصية — قريباً.</p>
    </div>
  );
}
