import type { Metadata } from "next";
import { GraduationCap } from "lucide-react";
import { getSessionFromCookie } from "@/lib/session";
import { getCataloguePrograms, getMyPrograms } from "@/features/programs/server";
import { ProgramsPageClient } from "@/features/programs/components/ProgramsPageClient";

export const metadata: Metadata = { title: "البرامج" };

export default async function ProgramsPage() {
  const session = await getSessionFromCookie();

  const [cataloguePrograms, myPrograms] = await Promise.all([
    getCataloguePrograms(),
    session ? getMyPrograms(session.wstoken) : Promise.resolve([]),
  ]);

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <GraduationCap className="size-6 text-primary" />
        <h1 className="text-h1 font-bold">البرامج</h1>
      </div>

      <ProgramsPageClient
        myPrograms={myPrograms}
        cataloguePrograms={cataloguePrograms}
        isLoggedIn={Boolean(session?.wstoken)}
      />
    </div>
  );
}
