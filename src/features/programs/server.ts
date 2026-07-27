import "server-only";
import { getLocale } from "next-intl/server";
import { callAcademyApi, callAcademyApiGet } from "@/lib/moodle-server";
import type { CatalogueProgram, MyProgram, ProgramDetails, ProgramCertificate } from "./types";

export async function getCataloguePrograms(): Promise<CatalogueProgram[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const token = process.env.MOODLE_ADMIN_TOKEN;
  if (!token) return [];
  try {
    return await callAcademyApi<CatalogueProgram[]>("get_catalogue_programs", {}, token, lang);
  } catch {
    return [];
  }
}

export async function getMyPrograms(wstoken: string): Promise<MyProgram[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  try {
    return await callAcademyApi<MyProgram[]>("get_my_programs", {}, wstoken, lang);
  } catch {
    return [];
  }
}

export async function getProgramDetails(programId: number, wstoken?: string): Promise<ProgramDetails | null> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  const token = wstoken || process.env.MOODLE_ADMIN_TOKEN;
  if (!token) return null;
  try {
    return await callAcademyApiGet<ProgramDetails>("get_program_details", { programid: programId }, token, lang);
  } catch (err) {
    console.error("Error fetching program details:", err);
    return null;
  }
}

export async function getProgramCertificateEligibility(programId: number, wstoken: string): Promise<ProgramCertificate[]> {
  const locale = await getLocale();
  const lang = locale === "ar" ? "ar" : "en";
  if (!wstoken) return [];
  try {
    const res = await callAcademyApiGet<any>("list_program_certificate_eligibility", { programid: programId }, wstoken, lang);
    if (Array.isArray(res)) return res;
    if (res?.certificates && Array.isArray(res.certificates)) return res.certificates;
    return [];
  } catch {
    return [];
  }
}

