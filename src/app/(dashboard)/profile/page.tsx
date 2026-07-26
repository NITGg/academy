import type { Metadata } from "next";
import Image from "next/image";
import { User, Mail, GraduationCap, Phone, Pencil, Lock } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { Button } from "@/components/ui/button";

export const metadata: Metadata = { title: "الملف الشخصي" };

interface InfoRowProps {
  icon: React.ReactNode;
  label: string;
  value: string;
  valueClassName?: string;
}

function InfoRow({ icon, label, value, valueClassName }: InfoRowProps) {
  return (
    <div className="flex flex-col border-b border-border pb-4 last:border-0 last:pb-0">
      <div className="flex items-center justify-end gap-1.5 text-[11px] text-muted-foreground">
        {label}
        {icon}
      </div>
      <p className={`mt-1 text-end text-caption font-medium text-foreground ${valueClassName ?? ""}`}>
        {value || "—"}
      </p>
    </div>
  );
}

export default async function ProfilePage() {
  const session = await getSessionFromCookie();
  const user = session?.user;

  const fullname = user ? `${user.firstname} ${user.lastname}` : "—";
  const initials = user ? user.firstname.charAt(0) : "؟";

  return (
    <div className="mx-auto max-w-lg space-y-6">
      {/* Avatar + name */}
      <div className="flex flex-col items-center gap-3 rounded-2xl bg-primary px-6 py-8 text-primary-foreground">
        <div className="relative size-24 overflow-hidden rounded-full border-4 border-primary-foreground/30 bg-primary-foreground/20">
          {user?.pictureUrl ? (
            <Image src={user.pictureUrl} alt={fullname} fill sizes="96px" className="object-cover" />
          ) : (
            <span className="flex h-full w-full items-center justify-center text-3xl font-bold text-primary">
              {initials}
            </span>
          )}
        </div>
        <h1 className="text-h1 font-bold">{fullname}</h1>
      </div>

      {/* Info card */}
      <div className="space-y-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
        <InfoRow
          icon={<User className="size-3.5" />}
          label="الاسم"
          value={fullname}
        />
        <InfoRow
          icon={<Mail className="size-3.5" />}
          label="البريد الإلكتروني"
          value={user?.email || user?.username || "—"}
        />
        <InfoRow
          icon={<GraduationCap className="size-3.5" />}
          label="العام الدراسي"
          value={user?.year || "—"}
        />
        <InfoRow
          icon={<Phone className="size-3.5" />}
          label="رقم الهاتف"
          value={user?.phone || "—"}
          valueClassName="font-bold"
        />
      </div>

      {/* Action buttons */}
      <div className="space-y-3">
        <Button variant="default" size="lg" className="w-full rounded-xl gap-2">
          <Pencil className="size-4" />
          تعديل الملف الشخصي
        </Button>
        <Button variant="outline" size="lg" className="w-full rounded-xl gap-2">
          <Lock className="size-4" />
          تغيير كلمة المرور
        </Button>
      </div>
    </div>
  );
}
