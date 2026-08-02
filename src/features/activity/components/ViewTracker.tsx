"use client";

import { useEffect, useRef } from "react";
import { markModuleViewed } from "@/features/courses/actions";

/**
 * Fires Moodle's "viewed" event once when an activity opens, so on-view automatic
 * completion triggers (the web app renders content itself instead of loading
 * Moodle's own module page). Best-effort and silent — never blocks the UI.
 *
 * Always passes the `cmid` so completion can be recorded even for module types
 * that have no per-type "view" web-service function (e.g. the custom video
 * `resource2` / `testnew` modules) via the generic backend fallback.
 */
export function ViewTracker({
  modname,
  cmid,
  instance,
}: {
  modname: string;
  cmid: number;
  instance?: number;
}) {
  const fired = useRef(false);
  useEffect(() => {
    if (fired.current) return;
    fired.current = true;
    void markModuleViewed(modname, cmid, instance);
  }, [modname, cmid, instance]);
  return null;
}
