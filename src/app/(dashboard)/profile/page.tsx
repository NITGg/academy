import type { Metadata } from "next";
import { User, Mail, GraduationCap, Phone } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { ProfileActions } from "@/components/profile/profile-actions";
import { ProfileHeader } from "@/components/profile/profile-header";

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
  const displayPhone = user?.phone || user?.parentPhone || "—";

  return (
    <div className="mx-auto max-w-lg space-y-6">
      {/* Avatar + name */}
      {user && <ProfileHeader user={user} />}

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
          value={displayPhone}
          valueClassName="font-bold"
        />
      </div>

      {/* Interactive Action buttons */}
      {user && <ProfileActions user={user} />}
    </div>
  );
}
