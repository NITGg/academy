import Link from "next/link";
import Image from "next/image";
import { BookOpen, CheckCircle2 } from "lucide-react";
import { useLocale } from "next-intl";
import { parseMlang } from "@/lib/mlang";
import type { EnrolledCourse } from "../server";

interface MyCourseCardProps {
  course: EnrolledCourse;
}

function moodlePublicUrl(url: string): string {
  return url.replace("/webservice/pluginfile.php", "/pluginfile.php");
}

export function MyCourseCard({ course }: MyCourseCardProps) {
  const locale = useLocale();
  const lang = locale === "ar" ? "ar" : "en";

  const fullname = parseMlang(course.fullname ?? "", lang);
  const imageUrl = course.overviewfiles?.[0]?.fileurl
    ? moodlePublicUrl(course.overviewfiles[0].fileurl)
    : null;
  const progress = typeof course.progress === "number" ? Math.round(course.progress) : 0;
  const completed = course.completed === true || progress >= 100;

  return (
    <Link
      href={`/courses/${course.id}`}
      className="group flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-shadow hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      {/* Cover image */}
      <div className="relative aspect-video w-full overflow-hidden bg-muted">
        {imageUrl ? (
          <Image
            src={imageUrl}
            alt={fullname}
            fill
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
            className="object-cover transition-transform duration-300 group-hover:scale-105"
            unoptimized
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-primary/10">
            <BookOpen className="size-10 text-primary/40" />
          </div>
        )}

        {/* Completion badge */}
        {completed && (
          <span className="absolute start-2 top-2 flex items-center gap-1 rounded-lg bg-emerald-500 px-2.5 py-1 text-xs font-bold text-white shadow-sm">
            <CheckCircle2 className="size-3" />
            مكتمل
          </span>
        )}
      </div>

      {/* Body */}
      <div className="flex flex-1 flex-col gap-3 p-4">
        <h3 className="line-clamp-2 text-sm font-bold leading-snug text-primary">
          {fullname}
        </h3>

        {/* Progress bar */}
        <div className="mt-auto space-y-1.5">
          <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
            <div
              className="h-full rounded-full bg-emerald-500 transition-all"
              style={{ width: `${progress}%` }}
            />
          </div>
          <div className="flex items-center justify-between text-[11px]">
            {completed ? (
              <div className="flex items-center gap-1 text-emerald-600">
                <CheckCircle2 className="size-3.5" />
                <span className="font-semibold">اكتمل 100%</span>
              </div>
            ) : (
              <span className="text-muted-foreground">متابعة التعلم</span>
            )}
            <span className="font-bold text-foreground">{progress}%</span>
          </div>
        </div>
      </div>
    </Link>
  );
}
