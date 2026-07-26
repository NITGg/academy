import { NextResponse } from "next/server";
import { callMoodleRest } from "@/lib/moodle-server";
import { getSessionFromCookie } from "@/lib/session";
import type { Course, CourseSection } from "@/features/courses/types";

export async function GET(
  request: Request,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const courseId = parseInt(id, 10);

    if (isNaN(courseId)) {
      return NextResponse.json({ error: "Invalid course ID" }, { status: 400 });
    }

    const session = await getSessionFromCookie();
    const token = session?.wstoken;

    // Fetch course details
    const courses = await callMoodleRest<Course[]>({
      functionName: "core_course_get_courses_by_field",
      useAdminToken: !token,
      token,
      params: { field: "id", value: courseId },
    });

    const course = courses[0];
    if (!course) {
      return NextResponse.json({ error: "Course not found" }, { status: 404 });
    }

    // Fetch topics/sections tree
    let contents: CourseSection[] = [];
    try {
      contents = await callMoodleRest<CourseSection[]>({
        functionName: "core_course_get_contents",
        useAdminToken: !token,
        token,
        params: { courseid: courseId },
      });
    } catch {
      // Fallback if user is not yet enrolled in course
    }

    return NextResponse.json({
      course,
      contents,
    });
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : "Failed to fetch course details";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}