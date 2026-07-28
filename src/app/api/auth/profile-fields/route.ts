import { NextResponse } from "next/server";
import { callMoodleRest } from "@/lib/moodle-server";

export interface ProfileField {
  id: number;
  shortname: string;
  name: string;
  datatype: string;
  required: number;
  options?: string[];
}

export async function GET() {
  try {
    const fields = await callMoodleRest<ProfileField[]>({
      functionName: "local_profilefields_get_profile_fields",
      useAdminToken: true,
    });

    return NextResponse.json({ fields });
  } catch (err) {
    console.error("[Profile Fields API Error]:", err);
    return NextResponse.json({ fields: [] });
  }
}
