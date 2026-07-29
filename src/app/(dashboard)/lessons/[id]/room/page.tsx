import type { Metadata } from "next";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { ArrowRight, Clock, Video } from "lucide-react";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { getLesson } from "@/features/lessons/server";
import { JitsiRoom } from "@/features/lessons/components/JitsiRoom";

interface Props {
  params: Promise<{ id: string }>;
}

export const metadata: Metadata = { title: "الحصة المرئية" };

export default async function LessonRoomPage({ params }: Props) {
  const { id } = await params;
  const lessonId = parseInt(id, 10);
  if (isNaN(lessonId)) notFound();

  const locale = await getLocale();
  const isArabic = locale === "ar";

  const session = await getSessionFromCookie();
  if (!session?.wstoken) {
    redirect(`/login?from=/lessons/${lessonId}/room`);
  }

  const lesson = await getLesson(lessonId, session.wstoken);
  if (!lesson) notFound();

  const js = lesson.jitsi_session;
  const canJoin =
    lesson.can_join && js && js.server_url && js.room;

  return (
    <div className="mx-auto max-w-5xl space-y-4">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <Video className="size-5 text-primary" />
          <h1 className="text-h2 font-bold text-foreground">
            {lesson.subject}
            {lesson.teacher_name ? ` — ${lesson.teacher_name}` : ""}
          </h1>
        </div>
        <Link
          href="/lessons"
          className="inline-flex items-center gap-1.5 rounded-xl border border-border bg-card px-3.5 py-2 text-xs font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
          <ArrowRight className="size-4" />
          {isArabic ? "العودة إلى حصصي" : "Back to my lessons"}
        </Link>
      </div>

      {canJoin && js ? (
        js.available ? (
          <JitsiRoom session={js} isArabic={isArabic} />
        ) : (
          <WaitingState
            info={js.available_info}
            isArabic={isArabic}
          />
        )
      ) : (
        <UnavailableState isArabic={isArabic} />
      )}
    </div>
  );
}

function WaitingState({
  info,
  isArabic,
}: {
  info?: string;
  isArabic: boolean;
}) {
  return (
    <div className="flex h-[calc(100dvh-11rem)] min-h-[480px] flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-border bg-card text-center">
      <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
        <Clock className="size-7" />
      </span>
      <p className="max-w-sm px-6 text-sm text-muted-foreground">
        {info ||
          (isArabic
            ? "في انتظار انضمام المدرس إلى الحصة. سيبدأ اللقاء تلقائياً بمجرد دخول المدرس — حدّث الصفحة بعد قليل."
            : "Waiting for the teacher to join. The session starts automatically once they do — refresh in a moment.")}
      </p>
    </div>
  );
}

function UnavailableState({ isArabic }: { isArabic: boolean }) {
  return (
    <div className="flex h-[calc(100dvh-11rem)] min-h-[480px] flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-border bg-card text-center">
      <span className="flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground">
        <Video className="size-7" />
      </span>
      <p className="max-w-sm px-6 text-sm text-muted-foreground">
        {isArabic
          ? "هذه الحصة غير متاحة للانضمام حالياً."
          : "This lesson is not open to join right now."}
      </p>
      <Link
        href="/lessons"
        className="text-small font-medium text-primary hover:underline"
      >
        {isArabic ? "العودة إلى حصصي" : "Back to my lessons"}
      </Link>
    </div>
  );
}
