import { NextResponse } from "next/server";
import { MOODLE_BASE_URL } from "@/config/constants";
import { callMoodleRest } from "@/lib/moodle-server";
import { getSessionFromCookie, createSessionCookie } from "@/lib/session";
import type { AuthSession, User } from "@/types";

export async function POST(request: Request) {
  try {
    const session = await getSessionFromCookie();
    if (!session || !session.user) {
      return NextResponse.json({ error: "غير مصرح بالوصول" }, { status: 401 });
    }

    const formData = await request.formData();
    const file = formData.get("file") as File | null;

    if (!file) {
      return NextResponse.json({ error: "الرجاء اختيار صورة" }, { status: 400 });
    }

    // Validate image MIME type
    if (!file.type.startsWith("image/")) {
      return NextResponse.json({ error: "يجب اختيار ملف صورة صالحة (PNG, JPG, WEBP)" }, { status: 400 });
    }

    // Validate size (max 10MB)
    if (file.size > 10 * 1024 * 1024) {
      return NextResponse.json({ error: "حجم الصورة يجب ألا يتجاوز 10 ميجابايت" }, { status: 400 });
    }

    const activeToken = session.wstoken || process.env.MOODLE_ADMIN_TOKEN;
    if (!activeToken) {
      return NextResponse.json({ error: "رمز المصادقة مفقود" }, { status: 401 });
    }

    // Step 1: Upload photo to Moodle draft area (/webservice/upload.php)
    const moodleUploadUrl = `${MOODLE_BASE_URL}/webservice/upload.php?token=${encodeURIComponent(activeToken)}`;

    const uploadBody = new FormData();
    uploadBody.append("file", file, file.name || "profile.jpg");
    uploadBody.append("itemid", "0");
    uploadBody.append("filearea", "draft");

    const uploadResponse = await fetch(moodleUploadUrl, {
      method: "POST",
      body: uploadBody,
      cache: "no-store",
    });

    if (!uploadResponse.ok) {
      throw new Error(`فشل رفع الصورة إلى خادم مودل: ${uploadResponse.status}`);
    }

    const uploadResultText = await uploadResponse.text();
    let uploadData: any;
    try {
      uploadData = JSON.parse(uploadResultText);
    } catch {
      throw new Error("استجابة غير صالحة من خادم الرفع");
    }

    if (uploadData?.error || uploadData?.exception) {
      throw new Error(uploadData.message || uploadData.error || uploadData.exception);
    }

    let draftitemid: number | undefined;
    if (Array.isArray(uploadData) && uploadData.length > 0) {
      draftitemid = uploadData[0].itemid;
    } else if (uploadData?.itemid) {
      draftitemid = uploadData.itemid;
    }

    if (!draftitemid) {
      throw new Error("لم يتم الحصول على معرف ملف الرفع من الخادم");
    }

    // Step 2: Associate draftitemid with user profile using core_user_update_users
    await callMoodleRest({
      functionName: "core_user_update_users",
      useAdminToken: true,
      params: {
        "users[0][id]": session.user.id,
        "users[0][draftitemid]": draftitemid,
      },
    });

    // Step 3: Fetch updated picture URL
    let newPictureUrl = "";
    if (session.wstoken) {
      try {
        const siteInfo = await callMoodleRest<{ userpictureurl?: string }>({
          functionName: "core_webservice_get_site_info",
          token: session.wstoken,
        });
        if (siteInfo?.userpictureurl) {
          newPictureUrl = siteInfo.userpictureurl;
        }
      } catch (e) {
        console.error("Failed to fetch siteInfo after picture update:", e);
      }
    }

    if (!newPictureUrl) {
      try {
        const users = await callMoodleRest<Array<{ profileimageurl?: string; profileimageurlsmall?: string }>>({
          functionName: "core_user_get_users_by_field",
          useAdminToken: true,
          params: { field: "id", "values[0]": session.user.id },
        });
        newPictureUrl = users[0]?.profileimageurl || users[0]?.profileimageurlsmall || "";
      } catch (e) {
        console.error("Failed to fetch user by field after picture update:", e);
      }
    }

    // Append cache-buster timestamp if URL is available
    if (newPictureUrl) {
      const separator = newPictureUrl.includes("?") ? "&" : "?";
      newPictureUrl = `${newPictureUrl}${separator}t=${Date.now()}`;
    }

    // Update user in session
    const updatedUser: User = {
      ...session.user,
      pictureUrl: newPictureUrl || session.user.pictureUrl,
    };

    const updatedSession: AuthSession = {
      ...session,
      user: updatedUser,
    };

    await createSessionCookie(updatedSession);

    return NextResponse.json({
      user: updatedUser,
      pictureUrl: updatedUser.pictureUrl,
    });
  } catch (err) {
    console.error("[Profile Image Upload Error]:", err);
    const message = err instanceof Error ? err.message : "فشل تحديث صورة الملف الشخصي";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
