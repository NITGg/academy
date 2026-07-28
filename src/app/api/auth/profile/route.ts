import { NextResponse } from "next/server";
import { callMoodleRest } from "@/lib/moodle-server";
import { getSessionFromCookie, createSessionCookie } from "@/lib/session";
import type { AuthSession, User } from "@/types";

export async function POST(request: Request) {
  try {
    const session = await getSessionFromCookie();
    if (!session || !session.user) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const body = await request.json();
    const { firstname, lastname, phone, year, parentPhone } = body;

    const current = session.user;
    const newFirstname = firstname?.trim() || current.firstname;
    const newLastname = lastname?.trim() || current.lastname;
    const newPhone = phone?.trim() ?? current.phone ?? "";
    const newYear = year?.trim() ?? current.year ?? "";
    const newParentPhone = parentPhone?.trim() ?? current.parentPhone ?? newPhone;

    const updateParams: Record<string, string | number | boolean | undefined> = {
      "users[0][id]": current.id,
      "users[0][firstname]": newFirstname,
      "users[0][lastname]": newLastname,
      "users[0][phone1]": newPhone,
      "users[0][customfields][0][type]": "year",
      "users[0][customfields][0][value]": newYear,
      "users[0][customfields][1][type]": "ParentPhone",
      "users[0][customfields][1][value]": newParentPhone,
    };

    await callMoodleRest({
      functionName: "core_user_update_users",
      useAdminToken: true,
      params: updateParams,
    });

    const updatedUser: User = {
      ...current,
      firstname: newFirstname,
      lastname: newLastname,
      phone: newPhone || newParentPhone,
      year: newYear,
      parentPhone: newParentPhone,
    };

    const updatedSession: AuthSession = {
      ...session,
      user: updatedUser,
    };

    await createSessionCookie(updatedSession);

    return NextResponse.json({ user: updatedUser });
  } catch (err) {
    console.error("[Profile Update API Error]:", err);
    const message = err instanceof Error ? err.message : "Failed to update profile";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
