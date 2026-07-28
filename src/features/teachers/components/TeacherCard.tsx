import Image from "next/image";
import { Star, Clock, BookOpen } from "lucide-react";
import type { Teacher } from "../types";

interface TeacherCardProps {
  teacher: Teacher;
  locale: string;
}

function StarRating({ rating }: { rating: number }) {
  return (
    <div className="flex items-center gap-0.5">
      {Array.from({ length: 5 }).map((_, i) => (
        <Star
          key={i}
          className={`size-3.5 ${
            i < Math.round(rating)
              ? "fill-amber-400 text-amber-400"
              : "fill-muted text-muted-foreground/30"
          }`}
        />
      ))}
      <span className="ms-1 text-[11px] text-muted-foreground font-medium">
        {rating.toFixed(1)}
      </span>
    </div>
  );
}

export function TeacherCard({ teacher, locale }: TeacherCardProps) {
  const isAr = locale === "ar";
  const isAvailable = teacher.available === 1;

  return (
    <div className="flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-shadow hover:shadow-md">
      {/* Photo + availability */}
      <div className="relative flex flex-col items-center gap-3 bg-muted/30 px-6 pt-6 pb-4">
        <div className="relative size-20 shrink-0 overflow-hidden rounded-full border-2 border-border shadow-sm">
          {teacher.photourl ? (
            <Image
              src={teacher.photourl.replace("/webservice/pluginfile.php", "/pluginfile.php")}
              alt={teacher.fullname}
              fill
              sizes="80px"
              className="object-cover"
              unoptimized
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center bg-primary/10 text-2xl font-bold text-primary">
              {teacher.fullname.charAt(0)}
            </div>
          )}
        </div>

        {/* Availability dot */}
        <span
          className={`absolute end-4 top-4 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ${
            isAvailable
              ? "bg-emerald-500/10 text-emerald-600"
              : "bg-muted text-muted-foreground"
          }`}
        >
          <span
            className={`size-1.5 rounded-full ${isAvailable ? "bg-emerald-500" : "bg-muted-foreground/40"}`}
          />
          {isAvailable
            ? isAr ? "متاح" : "Available"
            : isAr ? "غير متاح" : "Unavailable"}
        </span>

        <div className="text-center">
          <h3 className="text-caption font-bold leading-snug text-foreground">
            {teacher.fullname}
          </h3>
          {teacher.headline && (
            <p className="mt-0.5 text-[11px] text-muted-foreground line-clamp-2">
              {teacher.headline}
            </p>
          )}
        </div>

        {teacher.rating != null && teacher.rating > 0 && (
          <StarRating rating={teacher.rating} />
        )}
      </div>

      {/* Details */}
      <div className="flex flex-1 flex-col gap-3 p-4">
        {/* Experience */}
        {teacher.experience && (
          <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
            <Clock className="size-3.5 shrink-0" />
            <span>{teacher.experience}</span>
          </div>
        )}

        {/* Subjects */}
        {teacher.subjects && teacher.subjects.length > 0 && (
          <div className="flex items-start gap-2">
            <BookOpen className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
            <div className="flex flex-wrap gap-1">
              {teacher.subjects.slice(0, 3).map((s, i) => (
                <span
                  key={i}
                  className="rounded-md bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                >
                  {s.subject}
                </span>
              ))}
              {teacher.subjects.length > 3 && (
                <span className="rounded-md bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">
                  +{teacher.subjects.length - 3}
                </span>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
