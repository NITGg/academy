import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getCourseDetail } from "@/features/courses/server-detail";
import { CourseHero } from "@/features/courses/components/CourseHero";
import { ContentTree } from "@/features/courses/components/ContentTree";

interface Props {
  params: Promise<{ id: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { id } = await params;
  try {
    const { course } = await getCourseDetail(parseInt(id, 10));
    return { title: course.fullname };
  } catch {
    return { title: "الكورس" };
  }
}

export default async function CourseDetailPage({ params }: Props) {
  const { id } = await params;
  const courseId = parseInt(id, 10);

  if (isNaN(courseId)) notFound();

  let data;
  try {
    data = await getCourseDetail(courseId);
  } catch {
    notFound();
  }

  const { course, pricing, contents } = data;

  return (
    <div className="mx-auto max-w-3xl space-y-6 pb-10">
      <CourseHero course={course} pricing={pricing} />
      <ContentTree sections={contents} />
    </div>
  );
}
