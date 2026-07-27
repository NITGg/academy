import { notFound } from "next/navigation";
import { getSessionFromCookie } from "@/lib/session";
import { getTeacher, getLessonSettings } from "@/features/teachers/server";
import { TeacherProfileClient } from "@/features/teachers/components/TeacherProfileClient";

interface Props {
  params: Promise<{ id: string }>;
}

export default async function TeacherProfilePage({ params }: Props) {
  const { id } = await params;
  const teacherId = parseInt(id, 10);

  if (isNaN(teacherId)) notFound();

  const [session, teacher, settings] = await Promise.all([
    getSessionFromCookie(),
    getTeacher(teacherId),
    getLessonSettings(),
  ]);

  if (!teacher) notFound();

  return (
    <div className="mx-auto max-w-xl px-4 py-6">
      <TeacherProfileClient
        teacher={teacher}
        minBookingMinutes={settings.min_booking_minutes}
        currentUserId={session?.user.id}
      />
    </div>
  );
}

