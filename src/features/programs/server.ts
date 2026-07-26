import "server-only";
import { getLocale } from "next-intl/server";
import { callAcademyApi } from "@/lib/moodle-server";
import type { CatalogueProgram, MyProgram } from "./types";

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
