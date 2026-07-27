"use server";

import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { callAcademyApi, callAcademyApiGet } from "@/lib/moodle-server";
import type { B2BDashboard, B2BInvitation, B2BJoinResult } from "./types";

export interface B2BActionResult<T = undefined> {
  data?: T;
  error?: string;
  needsAuth?: boolean;
}

async function resolveLang(): Promise<"ar" | "en"> {
  const locale = await getLocale();
  return locale === "ar" ? "ar" : "en";
}

/** Read capacity + members + invitations for one B2B subscription the caller owns. */
export async function getB2bDashboard(
  purchaseId: number,
): Promise<B2BActionResult<B2BDashboard>> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };
  const lang = await resolveLang();
  try {
    const data = await callAcademyApiGet<B2BDashboard>(
      "get_b2b_dashboard",
      { purchaseid: purchaseId },
      session.wstoken,
      lang,
    );
    return { data };
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر تحميل لوحة إدارة B2B" };
  }
}

/** Generate a fresh invitation link. Returns the invitation (incl. shareable url). */
export async function generateB2bInvite(
  purchaseId: number,
  expiresAt = 0,
): Promise<B2BActionResult<B2BInvitation & { url: string }>> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };
  const lang = await resolveLang();
  try {
    const data = await callAcademyApi<B2BInvitation & { url: string }>(
      "b2b_generate_invite",
      { purchaseid: purchaseId, expires_at: expiresAt },
      session.wstoken,
      lang,
    );
    return { data };
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر توليد رابط الدعوة" };
  }
}

export async function revokeB2bInvite(invitationId: number): Promise<B2BActionResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };
  const lang = await resolveLang();
  try {
    await callAcademyApi("b2b_revoke_invite", { invitationid: invitationId }, session.wstoken, lang);
    return {};
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر إلغاء رابط الدعوة" };
  }
}

export async function approveB2bMember(membershipId: number): Promise<B2BActionResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };
  const lang = await resolveLang();
  try {
    await callAcademyApi("b2b_approve_member", { membershipid: membershipId }, session.wstoken, lang);
    return {};
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر قبول العضو" };
  }
}

export async function rejectB2bMember(
  membershipId: number,
  reason = "",
): Promise<B2BActionResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };
  const lang = await resolveLang();
  try {
    await callAcademyApi(
      "b2b_reject_member",
      { membershipid: membershipId, reason },
      session.wstoken,
      lang,
    );
    return {};
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر رفض العضو" };
  }
}

export async function removeB2bMember(membershipId: number): Promise<B2BActionResult> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };
  const lang = await resolveLang();
  try {
    await callAcademyApi("b2b_remove_member", { membershipid: membershipId }, session.wstoken, lang);
    return {};
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر إزالة العضو" };
  }
}

/** Join a B2B subscription through an invitation token (used by the /b2b/join page). */
export async function joinB2b(token: string): Promise<B2BActionResult<B2BJoinResult>> {
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { needsAuth: true };
  const lang = await resolveLang();
  try {
    const data = await callAcademyApi<B2BJoinResult>("b2b_join", { t: token }, session.wstoken, lang);
    return { data };
  } catch (err) {
    return { error: err instanceof Error ? err.message : "تعذّر الانضمام عبر رابط الدعوة" };
  }
}
