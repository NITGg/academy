"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { Loader2, AlertTriangle } from "lucide-react";
import type { JitsiSession } from "../types";

// Minimal typing for the Jitsi IFrame External API surface we use.
interface JitsiApiInstance {
  addEventListener: (event: string, handler: (...args: unknown[]) => void) => void;
  removeEventListener?: (event: string, handler: (...args: unknown[]) => void) => void;
  dispose: () => void;
  executeCommand: (command: string, ...args: unknown[]) => void;
}

type JitsiApiCtor = new (
  domain: string,
  options: Record<string, unknown>,
) => JitsiApiInstance;

declare global {
  interface Window {
    JitsiMeetExternalAPI?: JitsiApiCtor;
  }
}

// Cache the external_api.js load promise per server so it is injected only once.
const scriptPromises = new Map<string, Promise<void>>();

function loadJitsiScript(serverUrl: string): Promise<void> {
  if (typeof window !== "undefined" && window.JitsiMeetExternalAPI) {
    return Promise.resolve();
  }
  const src = `${serverUrl.replace(/\/+$/, "")}/external_api.js`;
  let p = scriptPromises.get(src);
  if (!p) {
    p = new Promise<void>((resolve, reject) => {
      const s = document.createElement("script");
      s.src = src;
      s.async = true;
      s.onload = () => resolve();
      s.onerror = () => {
        scriptPromises.delete(src); // allow a retry on next mount
        reject(new Error("failed to load Jitsi external_api.js"));
      };
      document.body.appendChild(s);
    });
    scriptPromises.set(src, p);
  }
  return p;
}

// Build the visible toolbar from the room's feature flags so students don't get
// moderator-only controls (recording, invite, security, mute-everyone).
function buildToolbar(session: JitsiSession): string[] {
  const buttons = [
    "microphone",
    "camera",
    "desktop",
    "chat",
    "raisehand",
    "tileview",
    "participants-pane",
    "settings",
    "fullscreen",
    "hangup",
  ];
  const f = session.feature_flags ?? {};
  if (f["recording.enabled"]) buttons.push("recording");
  if (f["invite.enabled"]) buttons.push("invite");
  if (f["security-options.enabled"]) buttons.push("security");
  if (f["mute-everyone.enabled"]) buttons.push("mute-everyone");
  return buttons;
}

/**
 * Renders a lesson's Jitsi meeting inline on the student-frontend domain using the
 * Jitsi IFrame External API, driven by the backend `jitsi_session` payload
 * (server_url + room + jwt). No Moodle page and no external tab.
 */
export function JitsiRoom({
  session,
  isArabic,
}: {
  session: JitsiSession;
  isArabic: boolean;
}) {
  const router = useRouter();
  const containerRef = useRef<HTMLDivElement>(null);
  const apiRef = useRef<JitsiApiInstance | null>(null);
  const hasSession = Boolean(session.server_url && session.room);
  const [status, setStatus] = useState<"loading" | "ready" | "error">(
    hasSession ? "loading" : "error",
  );

  useEffect(() => {
    if (!hasSession) return;
    let disposed = false;
    const serverUrl = session.server_url as string;

    loadJitsiScript(serverUrl)
      .then(() => {
        if (disposed || !containerRef.current || !window.JitsiMeetExternalAPI) {
          return;
        }
        const domain = serverUrl.replace(/^https?:\/\//, "").replace(/\/+$/, "");

        const api = new window.JitsiMeetExternalAPI(domain, {
          roomName: session.room,
          jwt: session.jwt || undefined,
          parentNode: containerRef.current,
          width: "100%",
          height: "100%",
          lang: isArabic ? "ar" : "en",
          configOverwrite: {
            subject: session.subject ?? "",
            disableDeepLinking: true,
            prejoinConfig: { enabled: true },
            toolbarButtons: buildToolbar(session),
          },
          interfaceConfigOverwrite: {
            MOBILE_APP_PROMO: false,
            SHOW_JITSI_WATERMARK: false,
            SHOW_CHROME_EXTENSION_BANNER: false,
          },
        });

        apiRef.current = api;

        const leave = () => router.push("/lessons");
        api.addEventListener("videoConferenceLeft", leave);
        api.addEventListener("readyToClose", leave);

        setStatus("ready");
      })
      .catch(() => {
        if (!disposed) setStatus("error");
      });

    return () => {
      disposed = true;
      try {
        apiRef.current?.dispose();
      } catch {
        /* ignore */
      }
      apiRef.current = null;
    };
  }, [session, isArabic, router, hasSession]);

  return (
    <div className="relative h-[calc(100dvh-11rem)] min-h-[480px] w-full overflow-hidden rounded-2xl border border-border bg-black">
      {status !== "error" && (
        <div ref={containerRef} className="h-full w-full" />
      )}

      {status === "loading" && (
        <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/80 text-white">
          <Loader2 className="size-8 animate-spin" />
          <p className="text-sm">
            {isArabic ? "جارٍ تجهيز الحصة…" : "Preparing the session…"}
          </p>
        </div>
      )}

      {status === "error" && (
        <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 px-6 text-center text-white">
          <AlertTriangle className="size-9 text-amber-400" />
          <p className="text-sm max-w-sm">
            {isArabic
              ? "تعذّر بدء الحصة المرئية. حاول تحديث الصفحة أو العودة لاحقاً."
              : "Could not start the video session. Try refreshing or come back shortly."}
          </p>
        </div>
      )}
    </div>
  );
}
