import Link from "next/link";
import Image from "next/image";
import { BookOpen, Users } from "lucide-react";
import { useLocale } from "next-intl";
import { parseMlang } from "@/lib/mlang";
import type { Course } from "../types";

interface CourseCardProps {
  course: Course;
}

export function CourseCard({ course }: CourseCardProps) {
  const locale = useLocale();
  const lang = locale === "ar" ? "ar" : "en";

  const fullname = parseMlang(course.fullname ?? "", lang);
  const shortname = parseMlang(course.shortname ?? "", lang);
  const categoryname = parseMlang(course.categoryname ?? "", lang);

  const price =
    course.isFree || !course.price
      ? "مجاني"
      : `${course.price} جنيه`;

  return (
    <Link
      href={`/courses/${course.id}`}
      className="group flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-shadow hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      {/* Thumbnail */}
      <div className="relative aspect-video w-full overflow-hidden bg-muted">
        {course.courseimage ? (
          <Image
            src={course.courseimage}
            alt={fullname}
            fill
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
            className="object-cover transition-transform duration-300 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-primary/10">
            <BookOpen className="size-10 text-primary/40" />
          </div>
        )}

        {/* Price badge */}
        <span
          className={`absolute end-2 top-2 rounded-lg px-2.5 py-1 text-xs font-bold shadow-sm ${
            course.isFree || !course.price
              ? "bg-emerald-500 text-white"
              : "bg-background/90 text-foreground backdrop-blur-sm"
          }`}
        >
          {price}
        </span>

        {/* Enrolled badge */}
        {course.isEnrolled && (
          <span className="absolute start-2 top-2 rounded-lg bg-primary px-2.5 py-1 text-xs font-bold text-primary-foreground shadow-sm">
            مسجّل
          </span>
        )}
      </div>

      {/* Body */}
      <div className="flex flex-1 flex-col gap-2 p-4">
        <h3 className="line-clamp-2 text-sm font-bold leading-snug text-foreground group-hover:text-primary transition-colors">
          {fullname}
        </h3>

        {course.teacherName && (
          <p className="text-xs text-muted-foreground">{course.teacherName}</p>
        )}

        {shortname && shortname !== fullname && (
          <p className="text-xs text-muted-foreground">{shortname}</p>
        )}

        {categoryname && (
          <span className="mt-auto inline-block w-fit rounded-md bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">
            {categoryname}
          </span>
        )}

        {course.enrolledusercount != null && (
          <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
            <Users className="size-3" />
            <span>{course.enrolledusercount} طالب</span>
          </div>
        )}
      </div>
    </Link>
  );
}
