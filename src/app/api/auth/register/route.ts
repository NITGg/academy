import { NextResponse } from "next/server";
import {
  callMoodleRest,
  fetchMoodleToken,
} from "@/lib/moodle-server";
import { createSessionCookie } from "@/lib/session";
import type { AuthSession, User } from "@/types";

interface CreatedUserResponse {
  id: number;
  username: string;
}

interface SiteInfo {
  userid: number;
  username: string;
  fullname: string;
  userpictureurl: string;
}

interface MoodleUser {
  id: number;
  phone1?: string;
  customfields?: Array<{ shortname: string; value: string }>;
}

export async function POST(request: Request) {
  try {
    const body = await request.json();
    const { email, password, year, parentPhone, firstname: reqFirst, lastname: reqLast } = body;

    if (!email || !password) {
      return NextResponse.json(
        { error: "Email and password are required" },
        { status: 400 },
      );
    }

    const emailPrefix = email.split("@")[0] ?? "user";
    const firstname = reqFirst || emailPrefix;
    const lastname = reqLast || "Student";
    const username = email.toLowerCase().trim();

    // Prepare custom fields array for Moodle
    const customfields: Array<{ type: string; value: string }> = [];
    if (year) {
      customfields.push({ type: "year", value: year });
    }
    if (parentPhone) {
      customfields.push({ type: "ParentPhone", value: parentPhone });
    }

    // Step 0: Check if user already exists with this email/username in Moodle
    try {
      const existingUsers = await callMoodleRest<MoodleUser[]>({
        functionName: "core_user_get_users_by_field",
        useAdminToken: true,
        params: { field: "email", "values[0]": username },
      });

      if (Array.isArray(existingUsers) && existingUsers.length > 0) {
        return NextResponse.json(
          { error: "هذا البريد الإلكتروني مسجل بالفعل. يرجى تسجيل الدخول بدلاً من ذلك." },
          { status: 400 },
        );
      }
    } catch {
      // Ignore pre-check failures
    }

    // Step 1: Create user via Moodle core_user_create_users (admin token)
    const createUserParams: Record<string, string | number | boolean | undefined> = {
      "users[0][username]": username,
      "users[0][password]": password,
      "users[0][firstname]": firstname,
      "users[0][lastname]": lastname,
      "users[0][email]": username,
    };

    customfields.forEach((cf, idx) => {
      createUserParams[`users[0][customfields][${idx}][type]`] = cf.type;
      createUserParams[`users[0][customfields][${idx}][value]`] = cf.value;
    });

    let createdUsers: CreatedUserResponse[];
    try {
      createdUsers = await callMoodleRest<CreatedUserResponse[]>({
        functionName: "core_user_create_users",
        useAdminToken: true,
        params: createUserParams,
      });
    } catch (err) {
      const message = err instanceof Error ? err.message : "User registration failed";
      const userFriendlyMessage =
        message.includes("معامل غير صالحة") || message.includes("invalidparameter")
          ? "تعذر إنشاء الحساب. قد يكون البريد الإلكتروني مسجل بالفعل أو كلمة المرور غير مستوفية للشروط."
          : message;
      return NextResponse.json({ error: userFriendlyMessage }, { status: 400 });
    }

    const createdId = createdUsers?.[0]?.id;
    if (!createdId) {
      return NextResponse.json(
        { error: "Failed to obtain created user details" },
        { status: 500 },
      );
    }

    // Step 2: Login immediately with the new user's credentials to obtain wstoken
    let wstoken: string;
    try {
      wstoken = await fetchMoodleToken(username, password);
    } catch (err) {
      const message = err instanceof Error ? err.message : "Auto-login failed after registration";
      return NextResponse.json({ error: message }, { status: 500 });
    }

    // Step 3: Fetch site info & extended profile details
    const siteInfo = await callMoodleRest<SiteInfo>({
      functionName: "core_webservice_get_site_info",
      token: wstoken,
    });

    let extPhone: string | undefined;
    let extYear: string | undefined = year;
    let extParentPhone: string | undefined = parentPhone;

    try {
      const extUsers = await callMoodleRest<MoodleUser[]>({
        functionName: "core_user_get_users_by_field",
        token: wstoken,
        params: { field: "id", "values[0]": siteInfo.userid },
      });

      const ext = extUsers[0];
      if (ext) {
        extPhone = ext.phone1;
        const cf = ext.customfields ?? [];
        extYear = cf.find((f) => f.shortname === "year")?.value ?? extYear;
        extParentPhone = cf.find((f) => f.shortname === "ParentPhone")?.value ?? extParentPhone;
      }
    } catch {
      // Non-fatal
    }

    const nameParts = siteInfo.fullname.trim().split(" ");
    const user: User = {
      id: siteInfo.userid,
      username: siteInfo.username,
      firstname: nameParts[0] ?? firstname,
      lastname: nameParts.slice(1).join(" ") || lastname,
      email: username,
      pictureUrl: siteInfo.userpictureurl ?? "",
      phone: extPhone,
      year: extYear,
      parentPhone: extParentPhone,
    };

    const session: AuthSession = { user, wstoken };

    // Step 4: Persist httpOnly session cookie
    await createSessionCookie(session);

    return NextResponse.json({ user });
  } catch (err) {
    console.error("[Register API Error]:", err);
    const message = err instanceof Error ? err.message : "Registration failed";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
