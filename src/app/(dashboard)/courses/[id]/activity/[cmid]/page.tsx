import type { Metadata } from "next";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { ArrowRight, FileText, Lock } from "lucide-react";
import { getLocale } from "next-intl/server";
import { getSessionFromCookie } from "@/lib/session";
import { getActivityForView } from "@/features/activity/server";
import { getQuiz, getAssignmentData } from "@/features/activity/actions";
import { ViewTracker } from "@/features/activity/components/ViewTracker";
import { PdfViewer } from "@/features/activity/components/PdfViewer";
import { VideoViewer } from "@/features/activity/components/VideoViewer";
import { QuizViewer } from "@/features/activity/components/QuizViewer";
import { AssignmentViewer } from "@/features/activity/components/AssignmentViewer";
import { CertificateViewer } from "@/features/activity/components/CertificateViewer";
import { CompletionPanel } from "@/features/activity/components/CompletionPanel";

interface Props {
  params: Promise<{ id: string; cmid: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { id, cmid } = await params;
  const session = await getSessionFromCookie();
  if (!session?.wstoken) return { title: "النشاط" };
  const locale = await getLocale();
  const act = await getActivityForView(
    parseInt(id, 10),
    parseInt(cmid, 10),
    session.wstoken,
    locale === "ar" ? "ar" : "en",
  );
  return { title: act?.name ?? "النشاط" };
}

export default async function ActivityPage({ params }: Props) {
  const { id, cmid: cmidStr } = await params;
  const courseId = parseInt(id, 10);
  const cmid = parseInt(cmidStr, 10);
  if (isNaN(courseId) || isNaN(cmid)) notFound();

  const locale = await getLocale();
  const isArabic = locale === "ar";

  const session = await getSessionFromCookie();
  if (!session?.wstoken) {
    redirect(`/login?from=/courses/${courseId}/activity/${cmid}`);
  }

  const activity = await getActivityForView(
    courseId,
    cmid,
    session.wstoken,
    isArabic ? "ar" : "en",
  );

  // Not found OR not accessible for this user (not enrolled / restricted / hidden).
  if (!activity) {
    return (
      <div className="mx-auto max-w-3xl py-16">
        <div className="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-border py-16 text-center">
          <Lock className="size-10 text-muted-foreground/40" />
          <p className="text-caption text-muted-foreground">
            {isArabic
              ? "هذا النشاط غير متاح لك، أو أنك غير مسجّل في الكورس."
              : "This activity is not available to you, or you are not enrolled."}
          </p>
          <Link
            href={`/courses/${courseId}`}
            className="text-small font-medium text-primary hover:underline"
          >
            {isArabic ? "العودة إلى الكورس" : "Back to course"}
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl space-y-5 pb-12">
      {/* Fire the "viewed" event so on-view automatic completion triggers. */}
      <ViewTracker modname={activity.modname} instance={activity.instance} />

      <div className="space-y-2">
        <Link
          href={`/courses/${courseId}`}
          className="inline-flex items-center gap-1.5 text-small text-muted-foreground hover:text-primary transition-colors"
        >
          <ArrowRight className="size-4" />
          {isArabic ? "العودة إلى الكورس" : "Back to course"}
        </Link>
        <h1 className="text-h1 font-bold text-foreground">{activity.name}</h1>
      </div>

      <ActivityBody activity={activity} isArabic={isArabic} />

      <CompletionPanel
        cmid={cmid}
        courseId={courseId}
        completion={activity.completion}
        completionstate={activity.completionstate}
        completiondetails={activity.completiondetails}
        isArabic={isArabic}
      />
    </div>
  );
}

async function ActivityBody({
  activity,
  isArabic,
}: {
  activity: NonNullable<Awaited<ReturnType<typeof getActivityForView>>>;
  isArabic: boolean;
}) {
  const { modname, mime, courseId, cmid, name, hasFile } = activity;

  // Quiz — full in-site attempt flow.
  if (modname === "quiz") {
    const res = await getQuiz(cmid);
    if (!res.data) {
      return (
        <ErrorBox
          text={
            res.error ??
            (isArabic ? "تعذّر تحميل الاختبار" : "Could not load the quiz")
          }
        />
      );
    }
    return (
      <QuizViewer
        quiz={res.data}
        courseId={courseId}
        cmid={cmid}
        isArabic={isArabic}
      />
    );
  }

  // Assignment — full in-site view with submission status and grade.
  if (modname === "assign") {
    const res = await getAssignmentData(cmid, courseId, activity.instance ?? 0);
    if (!res.data) {
      return (
        <ErrorBox
          text={
            res.error ??
            (isArabic ? "تعذّر تحميل الواجب" : "Could not load assignment")
          }
        />
      );
    }
    return (
      <AssignmentViewer
        assignment={res.data}
        courseId={courseId}
        isArabic={isArabic}
      />
    );
  }

  // Certificate — check all certificate module types or titles containing "شهادة" / "certificate"
  const isCert =
    ["customcert", "certificate", "coursecertificate", "simplecertificate", "tool_certificate"].includes(modname) ||
    name.toLowerCase().includes("شهادة") ||
    name.toLowerCase().includes("certificate");

  if (isCert) {
    return (
      <CertificateViewer
        courseId={courseId}
        cmid={cmid}
        name={name}
        isArabic={isArabic}
      />
    );
  }

  // File-backed activities (resource / resource2 / testnew / …).
  if (hasFile) {
    if (mime?.includes("pdf")) {
      return (
        <PdfViewer courseId={courseId} cmid={cmid} name={name} isArabic={isArabic} />
      );
    }
    if (mime?.startsWith("video") || mime?.startsWith("audio")) {
      return (
        <VideoViewer courseId={courseId} cmid={cmid} mime={mime} isArabic={isArabic} />
      );
    }
    // Unknown file type — inline preview unavailable, no download allowed.
    return (
      <div className="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-border py-14 text-center">
        <FileText className="size-10 text-muted-foreground/40" />
        <p className="text-caption text-muted-foreground max-w-sm">
          {isArabic
            ? "هذا نوع الملفات لا يمكن عرضه مباشرة داخل الموقع."
            : "This file type cannot be previewed inline."}
        </p>
      </div>
    );
  }

  // No dedicated in-site viewer yet (forum, assign, lesson, scorm, …).
  return (
    <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border py-14 text-center">
      <FileText className="size-10 text-muted-foreground/30" />
      <p className="text-caption text-muted-foreground max-w-sm">
        {isArabic
          ? "هذا النوع من الأنشطة سيتوفّر عرضه داخل الموقع قريباً."
          : "An in-site view for this activity type is coming soon."}
      </p>
    </div>
  );
}

function ErrorBox({ text }: { text: string }) {
  return (
    <div className="rounded-xl border border-red-400/40 bg-red-500/10 px-4 py-3 text-caption text-red-600 dark:text-red-400">
      {text}
    </div>
  );
}
