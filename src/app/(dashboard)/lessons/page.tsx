import type { Metadata } from "next";
import { Video } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { getMyLessons, getFlexHistory } from "@/features/lessons/server";
import { LessonsPageClient } from "@/features/lessons/components/LessonsPageClient";

export const metadata: Metadata = { title: "حصصي" };

export default async function LessonsPage() {
  const session = await getSessionFromCookie();

  const [lessons, flexHistory] = await Promise.all([
    session ? getMyLessons(session.wstoken) : Promise.resolve([]),
    session ? getFlexHistory(session.wstoken) : Promise.resolve([]),
  ]);

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Video className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">حصصي</h1>
      </div>
      <LessonsPageClient lessons={lessons} flexHistory={flexHistory} />
    </div>
  );
}
