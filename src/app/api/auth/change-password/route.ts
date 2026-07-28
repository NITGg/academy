import { NextResponse } from "next/server";
import { callMoodleRest, fetchMoodleToken } from "@/lib/moodle-server";
import { getSessionFromCookie } from "@/lib/session";

export async function POST(request: Request) {
  try {
    const session = await getSessionFromCookie();
    if (!session || !session.user) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const { currentPassword, newPassword } = await request.json();

    if (!currentPassword || !newPassword) {
      return NextResponse.json(
        { error: "Current password and new password are required" },
        { status: 400 },
      );
    }

    if (newPassword.length < 6) {
      return NextResponse.json(
        { error: "كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل" },
        { status: 400 },
      );
    }

    // Step 1: Verify candidate current password against login/token.php
    const username = session.user.username || session.user.email;
    try {
      await fetchMoodleToken(username, currentPassword);
    } catch {
      return NextResponse.json(
        { error: "كلمة المرور الحالية غير صحيحة" },
        { status: 400 },
      );
    }

    // Step 2: Update password in Moodle via core_user_update_users
    await callMoodleRest({
      functionName: "core_user_update_users",
      useAdminToken: true,
      params: {
        "users[0][id]": session.user.id,
        "users[0][password]": newPassword,
      },
    });

    return NextResponse.json({ success: true });
  } catch (err) {
    console.error("[Change Password API Error]:", err);
    const message = err instanceof Error ? err.message : "Failed to change password";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
