import "server-only";
import { cookies } from "next/headers";
import { SESSION_COOKIE } from "@/config/constants";
import type { AuthSession } from "@/types";

export async function createSessionCookie(session: AuthSession): Promise<void> {
  const cookieStore = await cookies();
  const serialized = JSON.stringify(session);
  const encoded = Buffer.from(serialized).toString("base64");

  cookieStore.set(SESSION_COOKIE, encoded, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: 30 * 24 * 60 * 60, // 30 days
  });
}

export async function getSessionFromCookie(): Promise<AuthSession | null> {
  const cookieStore = await cookies();
  const raw = cookieStore.get(SESSION_COOKIE)?.value;

  if (!raw) return null;

  try {
    const decoded = Buffer.from(raw, "base64").toString("utf-8");
    return JSON.parse(decoded) as AuthSession;
  } catch {
    return null;
  }
}

export async function deleteSessionCookie(): Promise<void> {
  const cookieStore = await cookies();
  cookieStore.delete(SESSION_COOKIE);
}