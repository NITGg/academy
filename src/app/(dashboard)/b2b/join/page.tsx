import type { Metadata } from "next";
import Link from "next/link";
import { redirect } from "next/navigation";
import { CheckCircle2, Clock, XCircle, Building2 } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { joinB2b } from "@/features/subscriptions/b2b-actions";

export const metadata: Metadata = { title: "دعوة اشتراك B2B" };

export default async function B2bJoinPage({
  searchParams,
}: {
  searchParams: Promise<{ t?: string }>;
}) {
  const { t: token } = await searchParams;
  const session = await getSessionFromCookie();

  // No token → invalid link.
  if (!token) {
    return (
      <JoinCard tone="error" icon={<XCircle className="size-7" />} title="رابط دعوة غير صالح">
        <p>الرابط الذي فتحته لا يحتوي على رمز دعوة صحيح.</p>
      </JoinCard>
    );
  }

  // Not logged in → send to login and come back to this exact link afterwards.
  if (!session?.wstoken) {
    const from = `/b2b/join?t=${encodeURIComponent(token)}`;
    redirect(`/login?from=${encodeURIComponent(from)}`);
  }

  const res = await joinB2b(token);

  if (res.needsAuth) {
    const from = `/b2b/join?t=${encodeURIComponent(token)}`;
    redirect(`/login?from=${encodeURIComponent(from)}`);
  }

  if (res.error || !res.data) {
    return (
      <JoinCard tone="error" icon={<XCircle className="size-7" />} title="تعذّر الانضمام">
        <p>{res.error ?? "رابط الدعوة غير صالح أو منتهي الصلاحية."}</p>
      </JoinCard>
    );
  }

  const { status, existing } = res.data;

  if (status === "approved") {
    return (
      <JoinCard
        tone="success"
        icon={<CheckCircle2 className="size-7" />}
        title={existing ? "أنت بالفعل عضو" : "تم قبولك في الاشتراك"}
      >
        <p>
          {existing
            ? "أنت عضو مقبول بالفعل في هذا الاشتراك. يمكنك الوصول إلى الكورسات المشمولة."
            : "تمت الموافقة على انضمامك تلقائياً. مرحباً بك! يمكنك الآن الوصول إلى الكورسات المشمولة."}
        </p>
      </JoinCard>
    );
  }

  if (status === "rejected") {
    return (
      <JoinCard tone="error" icon={<XCircle className="size-7" />} title="طلبك مرفوض">
        <p>تم رفض طلب انضمامك لهذا الاشتراك من قبل مسؤول الاشتراك.</p>
      </JoinCard>
    );
  }

  // pending
  return (
    <JoinCard
      tone="pending"
      icon={<Clock className="size-7" />}
      title={existing ? "طلبك قيد الانتظار" : "تم إرسال طلب الانضمام"}
    >
      <p>
        {existing
          ? "لديك طلب انضمام قيد المراجعة بالفعل. سيتم إشعارك عند موافقة المسؤول."
          : "تم إرسال طلب انضمامك لمسؤول الاشتراك. ستحصل على صلاحية الوصول بمجرد الموافقة عليه."}
      </p>
    </JoinCard>
  );
}

function JoinCard({
  tone,
  icon,
  title,
  children,
}: {
  tone: "success" | "pending" | "error";
  icon: React.ReactNode;
  title: string;
  children: React.ReactNode;
}) {
  const toneMap = {
    success: "bg-emerald-500/10 text-emerald-600",
    pending: "bg-amber-500/10 text-amber-600",
    error: "bg-destructive/10 text-destructive",
  };

  return (
    <div className="mx-auto flex min-h-[60vh] max-w-lg flex-col items-center justify-center">
      <div className="w-full space-y-4 rounded-2xl border border-border bg-card p-8 text-center shadow-sm">
        <div className="flex items-center justify-center gap-2 text-muted-foreground">
          <Building2 className="size-4" />
          <span className="text-xs font-bold">دعوة اشتراك B2B</span>
        </div>
        <div className={`mx-auto flex size-16 items-center justify-center rounded-full ${toneMap[tone]}`}>
          {icon}
        </div>
        <h1 className="text-lg font-bold text-foreground">{title}</h1>
        <div className="text-sm text-muted-foreground leading-relaxed">{children}</div>
        <div className="flex justify-center gap-2 pt-2">
          <Link
            href="/courses"
            className="rounded-xl bg-primary px-5 py-2 text-sm font-bold text-primary-foreground transition hover:opacity-90"
          >
            الذهاب إلى الكورسات
          </Link>
          <Link
            href="/"
            className="rounded-xl border border-border px-5 py-2 text-sm font-semibold text-foreground transition hover:bg-muted"
          >
            الصفحة الرئيسية
          </Link>
        </div>
      </div>
    </div>
  );
}
