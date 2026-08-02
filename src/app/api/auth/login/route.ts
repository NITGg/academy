import { NextResponse } from "next/server";
import {
  fetchMoodleToken,
  callMoodleRest,
} from "@/lib/moodle-server";
import { createSessionCookie } from "@/lib/session";
import type { AuthSession, User } from "@/types";

interface SiteInfo {
  userid: number;
  username: string;
  fullname: string;
  userpictureurl: string;
  // 1 = site administrator, 0 = regular user. Returned by core_webservice_get_site_info.
  userissiteadmin?: boolean | number;
}

interface MoodleUser {
  id: number;
  phone1?: string;
  customfields?: Array<{ shortname: string; value: string }>;
}

export async function POST(request: Request) {
  try {
    const { username, password } = await request.json();

    if (!username || !password) {
      return NextResponse.json(
        { error: "Username and password are required" },
        { status: 400 },
      );
    }

    // Step 1: Exchange credentials for wstoken
    let wstoken: string;
    try {
      wstoken = await fetchMoodleToken(username, password);
    } catch (err) {
      const message = err instanceof Error ? err.message : "Invalid credentials";
      return NextResponse.json({ error: message }, { status: 401 });
    }

    // Step 2: Fetch basic site info (userid, fullname, picture)
    const siteInfo = await callMoodleRest<SiteInfo>({
      functionName: "core_webservice_get_site_info",
      token: wstoken,
    });

    // Step 2b: Restrict this frontend to students only — reject site admins.
    if (siteInfo.userissiteadmin) {
      return NextResponse.json(
        {
          error: "This portal is for students only. Administrator accounts cannot sign in here.",
          code: "not_student",
        },
        { status: 403 },
      );
    }

    // Step 3: Fetch extended profile — phone1 + custom fields (year, ParentPhone)
    // Uses core_user_get_users_by_field, not a custom Academy endpoint.
    let phone: string | undefined;
    let year: string | undefined;
    let parentPhone: string | undefined;

    try {
      const extUsers = await callMoodleRest<MoodleUser[]>({
        functionName: "core_user_get_users_by_field",
        token: wstoken,
        params: { field: "id", "values[0]": siteInfo.userid },
      });

      const ext = extUsers[0];
      if (ext) {
        phone = ext.phone1;
        const cf = ext.customfields ?? [];
        year = cf.find((f) => f.shortname === "year")?.value;
        parentPhone = cf.find((f) => f.shortname === "ParentPhone")?.value;
      }
    } catch {
      // Non-fatal — proceed with partial profile
    }

    const nameParts = siteInfo.fullname.trim().split(" ");
    const user: User = {
      id: siteInfo.userid,
      username: siteInfo.username,
      firstname: nameParts[0] ?? siteInfo.username,
      lastname: nameParts.slice(1).join(" "),
      email: username,
      pictureUrl: siteInfo.userpictureurl ?? "",
      phone: phone || parentPhone,
      year,
      parentPhone,
    };

    const session: AuthSession = { user, wstoken };

    // Step 4: Persist wstoken in httpOnly cookie (never sent to browser JS)
    await createSessionCookie(session);

    return NextResponse.json({ user });
  } catch (err) {
    const message = err instanceof Error ? err.message : "Authentication failed";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
