"use client";

import { useEffect, useRef } from "react";
import { markModuleViewed } from "@/features/courses/actions";

/**
 * Fires Moodle's "viewed" event once when an activity opens, so on-view automatic
 * completion triggers (the web app renders content itself instead of loading
 * Moodle's own module page). Best-effort and silent — never blocks the UI.
 */
export function ViewTracker({
  modname,
  instance,
}: {
  modname: string;
  instance?: number;
}) {
  const fired = useRef(false);
  useEffect(() => {
    if (fired.current || !instance) return;
    fired.current = true;
    void markModuleViewed(modname, instance);
  }, [modname, instance]);
  return null;
}
