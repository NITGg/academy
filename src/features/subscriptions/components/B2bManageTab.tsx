"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Users,
  Armchair,
  CheckCircle2,
  Clock,
  CalendarClock,
  Link2,
  Copy,
  Check,
  Loader2,
  RefreshCw,
  Trash2,
  X,
  AlertTriangle,
} from "lucide-react";
import { toast } from "sonner";
import { Pagination } from "@/components/ui/Pagination";
import { cn, getAppUrl } from "@/lib/utils";
import {
  getB2bDashboard,
  generateB2bInvite,
  revokeB2bInvite,
  approveB2bMember,
  rejectB2bMember,
  removeB2bMember,
} from "../b2b-actions";
import { buildFrontendInviteLink } from "../b2b-utils";
import type { B2BSubscription, B2BDashboard, B2BMember } from "../types";

function formatDate(ts: number): string {
  if (!ts) return "بدون انتهاء";
  const d = new Date(ts * 1000);
  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day}`;
}

const MEMBER_FILTERS = [
  { id: "", label: "الكل" },
  { id: "pending", label: "قيد الانتظار" },
  { id: "approved", label: "مقبول" },
  { id: "rejected", label: "مرفوض" },
  { id: "removed", label: "مُزال" },
] as const;

const STATUS_LABELS: Record<string, { label: string; className: string }> = {
  pending: { label: "قيد الانتظار", className: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" },
  approved: { label: "مقبول", className: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" },
  rejected: { label: "مرفوض", className: "bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400" },
  removed: { label: "مُزال", className: "bg-muted text-muted-foreground" },
  expired: { label: "منتهي", className: "bg-muted text-muted-foreground" },
};

function StatCard({
  icon,
  value,
  label,
  tone = "default",
}: {
  icon: React.ReactNode;
  value: React.ReactNode;
  label: string;
  tone?: "default" | "primary" | "emerald" | "amber";
}) {
  const toneMap = {
    default: "text-foreground",
    primary: "text-primary",
    emerald: "text-emerald-600",
    amber: "text-amber-600",
  };
  return (
    <div className="flex items-center gap-3 rounded-2xl border border-border bg-card p-4 shadow-sm">
      <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted/60 text-muted-foreground">
        {icon}
      </div>
      <div>
        <div className={cn("text-lg font-extrabold leading-tight", toneMap[tone])}>{value}</div>
        <div className="text-[11px] text-muted-foreground">{label}</div>
      </div>
    </div>
  );
}

interface B2bManageTabProps {
  subscriptions: B2BSubscription[];
  initialSubscriptionId?: number;
}

export function B2bManageTab({ subscriptions, initialSubscriptionId }: B2bManageTabProps) {
  const initialPurchaseId = useMemo(() => {
    if (initialSubscriptionId) {
      const match = subscriptions.find((s) => s.subscriptionid === initialSubscriptionId);
      if (match) return match.purchaseid;
    }
    return subscriptions[0]?.purchaseid ?? null;
  }, [subscriptions, initialSubscriptionId]);

  const [currentPurchaseId, setCurrentPurchaseId] = useState<number | null>(initialPurchaseId);
  const [dashboard, setDashboard] = useState<B2BDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [filter, setFilter] = useState<string>("");
  const [copied, setCopied] = useState(false);
  const [rejectTarget, setRejectTarget] = useState<B2BMember | null>(null);
  const [rejectReason, setRejectReason] = useState("");
  const [removeTarget, setRemoveTarget] = useState<B2BMember | null>(null);
  const [revokeTargetId, setRevokeTargetId] = useState<number | null>(null);
  const [memberPage, setMemberPage] = useState(1);

  const loadDashboard = useCallback(async (purchaseId: number) => {
    // Note: `loading` starts true and is only cleared after the fetch resolves, so the
    // mount effect never calls setState synchronously (avoids cascading-render lint).
    const res = await getB2bDashboard(purchaseId);
    setLoading(false);
    if (res.needsAuth) {
      window.location.assign(getAppUrl("/login"));
      return;
    }
    if (res.error) {
      toast.error(res.error);
      return;
    }
    setDashboard(res.data ?? null);
  }, []);

  useEffect(() => {
    // Fetch-on-mount / on-subscription-change: the API is the external system we sync from.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (currentPurchaseId) loadDashboard(currentPurchaseId);
  }, [currentPurchaseId, loadDashboard]);

  const activeInvite = useMemo(
    () => dashboard?.invitations.find((i) => i.status === "active"),
    [dashboard],
  );

  const shareLink = activeInvite?.url ? buildFrontendInviteLink(activeInvite.url) : null;

  const filteredMembers = useMemo(() => {
    const rows = dashboard?.members ?? [];
    return filter ? rows.filter((m) => m.status === filter) : rows;
  }, [dashboard, filter]);

  const pageSize = 8;
  const totalPages = Math.ceil(filteredMembers.length / pageSize);
  const pagedMembers = filteredMembers.slice((memberPage - 1) * pageSize, memberPage * pageSize);

  if (subscriptions.length === 0 || !currentPurchaseId) {
    return (
      <div className="flex min-h-[220px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
        <Users className="size-8 opacity-30" />
        <p className="text-caption">لا تملك أي اشتراك B2B لإدارته</p>
      </div>
    );
  }

  async function handleGenerate() {
    if (!currentPurchaseId) return;
    setBusy(true);
    const res = await generateB2bInvite(currentPurchaseId);
    setBusy(false);
    if (res.needsAuth) return void window.location.assign(getAppUrl("/login"));
    if (res.error) return void toast.error(res.error);
    toast.success("تم توليد رابط الدعوة");
    loadDashboard(currentPurchaseId);
  }

  async function handleCopy() {
    if (!shareLink) return;
    try {
      await navigator.clipboard.writeText(shareLink);
    } catch {
      const ta = document.createElement("textarea");
      ta.value = shareLink;
      ta.style.position = "fixed";
      ta.style.opacity = "0";
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      document.body.removeChild(ta);
    }
    setCopied(true);
    toast.success("تم نسخ الرابط");
    setTimeout(() => setCopied(false), 1600);
  }

  async function runAction(fn: () => Promise<{ error?: string; needsAuth?: boolean }>, okMsg: string) {
    if (!currentPurchaseId) return;
    setBusy(true);
    const res = await fn();
    setBusy(false);
    if (res.needsAuth) return void window.location.assign(getAppUrl("/login"));
    if (res.error) return void toast.error(res.error);
    toast.success(okMsg);
    loadDashboard(currentPurchaseId);
  }

  const cap = dashboard?.capacity;
  // Management (generate/revoke link, approve/reject/remove) is allowed only while the subscription
  // is active. Expired/cancelled subscriptions are read-only history. The backend enforces this too.
  const canManage = cap ? (cap.can_manage ?? cap.status === "active") : false;

  return (
    <div className="space-y-5">
      {/* Subscription selector (only when the user administers more than one) */}
      {subscriptions.length > 1 && (
        <div className="space-y-1.5">
          <label className="text-xs font-bold text-muted-foreground">اختر الاشتراك:</label>
          <select
            value={currentPurchaseId}
            onChange={(e) => {
              setCurrentPurchaseId(Number(e.target.value));
              setMemberPage(1);
            }}
            className="w-full max-w-md rounded-xl border border-border bg-background p-2.5 text-sm text-foreground outline-none focus:ring-1 focus:ring-primary"
          >
            {subscriptions.map((s) => {
              const active = s.can_manage ?? s.status === "active";
              return (
                <option key={s.purchaseid} value={s.purchaseid}>
                  {s.name} ({s.available}/{s.seats}){active ? "" : " — منتهي"}
                </option>
              );
            })}
          </select>
        </div>
      )}

      {loading && !dashboard ? (
        <div className="flex min-h-[220px] items-center justify-center text-muted-foreground">
          <Loader2 className="size-6 animate-spin" />
        </div>
      ) : cap ? (
        <>
          {/* Read-only history notice — shown when the subscription is expired/cancelled. */}
          {!canManage && (
            <div className="flex items-start gap-2.5 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-800 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-300">
              <AlertTriangle className="mt-0.5 size-5 shrink-0" />
              <p className="text-xs leading-relaxed">
                انتهى هذا الاشتراك B2B. يُعرض للاطّلاع فقط (كسجلّ) — لم يعد بإمكانك توليد روابط أو إدارة الأعضاء.
              </p>
            </div>
          )}

          {/* Capacity stats */}
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard icon={<Armchair className="size-5" />} value={cap.capacity} label="إجمالي المقاعد" />
            <StatCard icon={<Users className="size-5" />} value={cap.consumed} label="مقاعد مستهلكة" />
            <StatCard icon={<CheckCircle2 className="size-5" />} value={cap.available} label="مقاعد متاحة" tone="emerald" />
            <StatCard icon={<Clock className="size-5" />} value={cap.pending} label="طلبات قيد الانتظار" tone="amber" />
            <StatCard icon={<CheckCircle2 className="size-5" />} value={cap.approved} label="أعضاء مقبولون" tone="primary" />
            <StatCard icon={<CalendarClock className="size-5" />} value={formatDate(cap.expires_at)} label="تاريخ الانتهاء" />
          </div>

          {/* Invitation link — hidden entirely for read-only (expired) subscriptions:
              an ended subscription has no shareable link, so no copy/generate/revoke at all. */}
          {canManage && (
            <div className="space-y-3 rounded-2xl border border-border bg-card p-5 shadow-sm">
              <div className="flex items-center gap-2">
                <Link2 className="size-5 text-primary" />
                <h3 className="text-sm font-bold text-foreground">رابط الدعوة</h3>
              </div>
              <p className="text-xs text-muted-foreground">
                شارك هذا الرابط لدعوة الأعضاء للانضمام لاشتراكك. الرابط لا يمنح صلاحية مباشرة — يُنشئ طلب
                عضوية يظهر لك هنا للموافقة عليه.
              </p>

              {shareLink ? (
                <div className="space-y-2">
                  <div className="flex items-center gap-2 rounded-xl border border-primary/20 bg-primary/5 p-2">
                    <code className="flex-1 min-w-0 truncate text-[12px] text-primary" dir="ltr">
                      {shareLink}
                    </code>
                    <button
                      type="button"
                      onClick={handleCopy}
                      className={cn(
                        "flex shrink-0 items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-bold transition",
                        copied
                          ? "border-emerald-300 bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20"
                          : "border-border bg-background text-foreground hover:bg-muted",
                      )}
                    >
                      {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                      {copied ? "تم النسخ" : "نسخ"}
                    </button>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      onClick={handleGenerate}
                      disabled={busy}
                      className="flex items-center gap-1.5 rounded-xl border border-border bg-background px-3 py-1.5 text-xs font-bold text-foreground transition hover:bg-muted disabled:opacity-50"
                    >
                      <RefreshCw className="size-3.5" />
                      توليد رابط جديد
                    </button>
                    <button
                      type="button"
                      onClick={() => setRevokeTargetId(activeInvite!.id)}
                      disabled={busy}
                      className="flex items-center gap-1.5 rounded-xl border border-destructive/30 bg-destructive/5 px-3 py-1.5 text-xs font-bold text-destructive transition hover:bg-destructive/10 disabled:opacity-50"
                    >
                      <Trash2 className="size-3.5" />
                      إلغاء الرابط
                    </button>
                  </div>
                </div>
              ) : activeInvite ? (
                // Active link exists but its raw token was not stored (legacy) — only allow revoke.
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-xs text-muted-foreground">يوجد رابط دعوة نشط (لا يمكن إعادة عرضه).</span>
                  <button
                    type="button"
                    onClick={() => setRevokeTargetId(activeInvite.id)}
                    disabled={busy}
                    className="flex items-center gap-1.5 rounded-xl border border-destructive/30 bg-destructive/5 px-3 py-1.5 text-xs font-bold text-destructive transition hover:bg-destructive/10 disabled:opacity-50"
                  >
                    <Trash2 className="size-3.5" />
                    إلغاء الرابط
                  </button>
                  <button
                    type="button"
                    onClick={handleGenerate}
                    disabled={busy}
                    className="flex items-center gap-1.5 rounded-xl border border-border bg-background px-3 py-1.5 text-xs font-bold text-foreground transition hover:bg-muted disabled:opacity-50"
                  >
                    <RefreshCw className="size-3.5" />
                    توليد رابط جديد
                  </button>
                </div>
              ) : (
                <button
                  type="button"
                  onClick={handleGenerate}
                  disabled={busy}
                  className="flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                >
                  {busy ? <Loader2 className="size-4 animate-spin" /> : <Link2 className="size-4" />}
                  توليد رابط دعوة
                </button>
              )}
            </div>
          )}

          {/* Members */}
          <div className="space-y-3">
            <h3 className="text-sm font-bold text-foreground">الأعضاء</h3>

            {/* Filter tabs */}
            <div className="flex flex-wrap gap-1.5 border-b border-border">
              {MEMBER_FILTERS.map((f) => (
                <button
                  key={f.id}
                  onClick={() => {
                    setFilter(f.id);
                    setMemberPage(1);
                  }}
                  className={cn(
                    "px-3 py-2 text-xs font-medium transition-colors",
                    filter === f.id
                      ? "border-b-2 border-primary text-primary"
                      : "text-muted-foreground hover:text-foreground",
                  )}
                >
                  {f.label}
                </button>
              ))}
            </div>

            {pagedMembers.length === 0 ? (
              <div className="flex min-h-[160px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border text-muted-foreground">
                <Users className="size-7 opacity-30" />
                <p className="text-caption">لا يوجد أعضاء في هذه الحالة</p>
              </div>
            ) : (
              <div className="space-y-2">
                {pagedMembers.map((m) => {
                  const s = STATUS_LABELS[m.status] ?? { label: m.status, className: "bg-muted text-muted-foreground" };
                  return (
                    <div
                      key={m.id}
                      className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-bold text-foreground truncate">{m.user_fullname}</span>
                          <span className={cn("rounded-full px-2 py-0.5 text-[10px] font-semibold", s.className)}>
                            {s.label}
                          </span>
                          {m.consumes_seat === 1 && (
                            <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-600 dark:bg-blue-900/30">
                              يشغل مقعد
                            </span>
                          )}
                        </div>
                        <p className="text-[11px] text-muted-foreground truncate" dir="ltr">{m.user_email}</p>
                        {m.status === "rejected" && m.reject_reason && (
                          <p className="mt-0.5 text-[11px] text-red-500">سبب الرفض: {m.reject_reason}</p>
                        )}
                      </div>

                      {/* Member actions only while the subscription is active (read-only history otherwise). */}
                      {canManage && (
                        <div className="flex shrink-0 items-center gap-2">
                          {m.status === "pending" && (
                            <>
                              <button
                                type="button"
                                disabled={busy}
                                onClick={() => runAction(() => approveB2bMember(m.id), "تم قبول العضو")}
                                className="rounded-lg bg-emerald-500/10 px-3 py-1.5 text-xs font-bold text-emerald-600 transition hover:bg-emerald-500/20 disabled:opacity-50"
                              >
                                قبول
                              </button>
                              <button
                                type="button"
                                disabled={busy}
                                onClick={() => {
                                  setRejectReason("");
                                  setRejectTarget(m);
                                }}
                                className="rounded-lg bg-amber-500/10 px-3 py-1.5 text-xs font-bold text-amber-600 transition hover:bg-amber-500/20 disabled:opacity-50"
                              >
                                رفض
                              </button>
                            </>
                          )}
                          {m.status === "approved" && (
                            <button
                              type="button"
                              disabled={busy}
                              onClick={() => setRemoveTarget(m)}
                              className="rounded-lg bg-destructive/10 px-3 py-1.5 text-xs font-bold text-destructive transition hover:bg-destructive/20 disabled:opacity-50"
                            >
                              إزالة
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}

            <Pagination
              currentPage={memberPage}
              totalPages={totalPages}
              totalItems={filteredMembers.length}
              pageSize={pageSize}
              onPageChange={setMemberPage}
            />
          </div>
        </>
      ) : null}

      {/* ── Reject modal (with reason) ── */}
      {rejectTarget && (
        <ConfirmDialog
          tone="amber"
          icon={<AlertTriangle className="size-5" />}
          title="رفض طلب العضوية"
          onCancel={() => setRejectTarget(null)}
          confirmLabel="رفض"
          onConfirm={() => {
            const target = rejectTarget;
            setRejectTarget(null);
            runAction(() => rejectB2bMember(target.id, rejectReason), "تم رفض العضو");
          }}
        >
          <p className="text-sm text-foreground">
            هل تريد رفض طلب <span className="font-bold">{rejectTarget.user_fullname}</span>؟
          </p>
          <label className="mt-3 block text-xs text-muted-foreground">سبب الرفض (اختياري):</label>
          <textarea
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
            rows={3}
            className="mt-1 w-full rounded-xl border border-border bg-background p-2.5 text-sm text-foreground outline-none focus:ring-1 focus:ring-primary"
          />
        </ConfirmDialog>
      )}

      {/* ── Remove member modal ── */}
      {removeTarget && (
        <ConfirmDialog
          tone="destructive"
          icon={<Trash2 className="size-5" />}
          title="إزالة العضو"
          onCancel={() => setRemoveTarget(null)}
          confirmLabel="إزالة"
          onConfirm={() => {
            const target = removeTarget;
            setRemoveTarget(null);
            runAction(() => removeB2bMember(target.id), "تمت إزالة العضو");
          }}
        >
          <p className="text-sm text-foreground">
            سيتم سحب صلاحية الوصول للكورسات من{" "}
            <span className="font-bold">{removeTarget.user_fullname}</span>. هل أنت متأكد؟
          </p>
        </ConfirmDialog>
      )}

      {/* ── Revoke invite modal ── */}
      {revokeTargetId != null && (
        <ConfirmDialog
          tone="destructive"
          icon={<Trash2 className="size-5" />}
          title="إلغاء رابط الدعوة"
          onCancel={() => setRevokeTargetId(null)}
          confirmLabel="إلغاء الرابط"
          onConfirm={() => {
            const id = revokeTargetId;
            setRevokeTargetId(null);
            runAction(() => revokeB2bInvite(id), "تم إلغاء الرابط");
          }}
        >
          <p className="text-sm text-foreground">
            لن يصلح هذا الرابط لأي انضمام جديد بعد الإلغاء. هل أنت متأكد؟
          </p>
        </ConfirmDialog>
      )}
    </div>
  );
}

// ── Reusable confirm dialog ───────────────────────────────────────────────────

function ConfirmDialog({
  tone,
  icon,
  title,
  children,
  confirmLabel,
  onConfirm,
  onCancel,
}: {
  tone: "amber" | "destructive";
  icon: React.ReactNode;
  title: string;
  children: React.ReactNode;
  confirmLabel: string;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  const toneClasses =
    tone === "destructive"
      ? { iconBg: "bg-destructive/10 text-destructive", btn: "bg-destructive text-white hover:bg-destructive/90" }
      : { iconBg: "bg-amber-500/10 text-amber-600", btn: "bg-amber-500 text-white hover:bg-amber-600" };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-md rounded-2xl bg-card p-6 shadow-xl border border-border space-y-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <span className={cn("flex size-10 items-center justify-center rounded-full", toneClasses.iconBg)}>
              {icon}
            </span>
            <h3 className="text-base font-bold text-foreground">{title}</h3>
          </div>
          <button onClick={onCancel} className="text-muted-foreground hover:text-foreground">
            <X className="size-5" />
          </button>
        </div>

        <div>{children}</div>

        <div className="flex justify-end gap-2 pt-1">
          <button
            type="button"
            onClick={onCancel}
            className="rounded-xl px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-muted"
          >
            إلغاء
          </button>
          <button
            type="button"
            onClick={onConfirm}
            className={cn("rounded-xl px-5 py-2 text-xs font-bold transition", toneClasses.btn)}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
