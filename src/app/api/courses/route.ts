import { NextResponse } from 'next/server';
import { callMoodleRest } from '@/lib/moodle-server';
import type { Course, CourseCategory } from '@/features/courses/types';

interface MoodleCoursesResponse {
  courses?: Course[];
  warnings?: unknown[];
}

export async function GET(request: Request) {
  try {
    const adminToken = process.env.MOODLE_ADMIN_TOKEN;
    if (!adminToken) {
      return NextResponse.json(
        { error: 'Admin token not configured' },
        { status: 500 }
      );
    }

    // 1. Fetch Categories
    const categories = await callMoodleRest<CourseCategory[]>({
      functionName: 'core_course_get_categories',
      token: adminToken,
      params: {
        'criteria[0][key]': 'parent',
        'criteria[0][value]': 0,
        addsubcategories: 1,
      },
    });

    // 2. Fetch Raw Courses
    const coursesResult = await callMoodleRest<MoodleCoursesResponse | Course[]>({
      functionName: 'core_course_get_courses_by_field',
      token: adminToken,
    });

    // Safely extract array regardless of whether Moodle returned an object wrapper or raw array
    const rawCourses: Course[] = Array.isArray(coursesResult)
      ? coursesResult
      : coursesResult?.courses ?? [];

    // 3. Filter out Moodle site frontpage course (always id=1)
    let filteredCourses = rawCourses.filter((c) => c.id !== 1);

    // 4. Query Params Filtering (categoryId & search)
    const { searchParams } = new URL(request.url);
    const categoryId = searchParams.get('categoryId');
    const search = searchParams.get('search');

    if (categoryId) {
      filteredCourses = filteredCourses.filter(
        (c) => String(c.categoryid) === categoryId
      );
    }

    if (search) {
      const query = search.toLowerCase().trim();
      filteredCourses = filteredCourses.filter(
        (c) =>
          c.fullname?.toLowerCase().includes(query) ||
          c.shortname?.toLowerCase().includes(query)
      );
    }

    return NextResponse.json({
      categories: Array.isArray(categories) ? categories : [],
      courses: filteredCourses,
    });
  } catch (err) {
    const message = err instanceof Error ? err.message : 'Failed to fetch courses';
    return NextResponse.json({ error: message }, { status: 500 });
  }
}