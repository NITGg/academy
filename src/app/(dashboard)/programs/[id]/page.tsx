import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getSessionFromCookie } from "@/lib/session";
import { getProgramDetails, getProgramCertificateEligibility } from "@/features/programs/server";
import { ProgramDetailsClient } from "@/features/programs/components/ProgramDetailsClient";

interface Props {
  params: Promise<{ id: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { id } = await params;
  const programId = parseInt(id, 10);
  if (isNaN(programId)) return { title: "البرنامج" };

  try {
    const program = await getProgramDetails(programId);
    return { title: program?.name || "البرنامج" };
  } catch {
    return { title: "البرنامج" };
  }
}

export default async function ProgramDetailPage({ params }: Props) {
  const { id } = await params;
  const programId = parseInt(id, 10);

  if (isNaN(programId)) notFound();

  const session = await getSessionFromCookie();
  const program = await getProgramDetails(programId, session?.wstoken);

  if (!program) {
    notFound();
  }

  const certificates = session?.wstoken
    ? await getProgramCertificateEligibility(programId, session.wstoken)
    : [];

  return (
    <ProgramDetailsClient
      program={program}
      certificates={certificates}
      isLoggedIn={Boolean(session?.wstoken)}
    />
  );
}
