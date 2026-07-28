"use client";

import { CheckCircle2, Circle, Loader2 } from "lucide-react";
import { useState, useTransition } from "react";
import type { CompletionRule } from "@/features/courses/types";
import { markActivityComplete } from "../actions";

/**
 * Renders the completion state that the old Moodle page showed under each activity:
 *  - automatic (completion === 2): the per-rule checklist ("للقيام به: تلقّي علامة", …)
 *  - manual   (completion === 1): a toggle button ("تمييز كمنجز" / "منجز")
 *  - none     (completion === 0): nothing.
 */
export function CompletionPanel({
  cmid,
  courseId,
  completion,
  completionstate,
  completiondetails,
  isArabic,
}: {
  cmid: number;
  courseId: number;
  completion: number;
  completionstate: number;
  completiondetails: CompletionRule[];
  isArabic: boolean;
}) {
  const [state, setState] = useState(completionstate);
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);

  if (completion === 0) return null;

  const isComplete = state === 1 || state === 2;

  const toggle = () => {
    setError(null);
    const next = !isComplete;
    startTransition(async () => {
      const res = await markActivityComplete(cmid, courseId, next);
      if (res.ok) {
        setState(next ? 1 : 0);
      } else {
        setError(
          res.error ??
            (isArabic ? "تعذّر تحديث حالة الإتمام" : "Could not update completion"),
        );
      }
    });
  };

  return (
    <div className="rounded-xl border border-border bg-muted/30 p-4 space-y-3">
      <div className="flex items-center gap-2">
        {isComplete ? (
          <CheckCircle2 className="size-5 text-emerald-500" />
        ) : (
          <Circle className="size-5 text-muted-foreground/40" />
        )}
        <span className="text-caption font-semibold text-foreground">
          {isComplete
            ? isArabic
              ? "تم إنجاز هذا النشاط"
              : "Activity completed"
            : isArabic
              ? "لم يُنجز بعد"
              : "Not completed yet"}
        </span>
      </div>

      {/* Automatic completion: show the rule checklist, exactly like the old site. */}
      {completion === 2 && completiondetails.length > 0 && (
        <ul className="space-y-1.5 ps-1">
          {completiondetails.map((rule, i) => (
            <li key={i} className="flex items-center gap-2 text-small">
              {rule.status === 1 ? (
                <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
              ) : (
                <Circle className="size-4 shrink-0 text-muted-foreground/40" />
              )}
              <span
                className={
                  rule.status === 1
                    ? "text-muted-foreground line-through"
                    : "text-foreground"
                }
              >
                {isArabic ? "للقيام به: " : "To do: "}
                {rule.description}
              </span>
            </li>
          ))}
        </ul>
      )}

      {/* Manual completion: the student marks it done themselves. */}
      {completion === 1 && (
        <div className="space-y-1.5">
          <button
            type="button"
            onClick={toggle}
            disabled={pending}
            className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 text-small font-medium transition-colors disabled:opacity-60 ${
              isComplete
                ? "border border-border text-muted-foreground hover:bg-muted/50"
                : "bg-primary text-primary-foreground hover:bg-primary/90"
            }`}
          >
            {pending ? (
              <Loader2 className="size-4 animate-spin" />
            ) : isComplete ? (
              <CheckCircle2 className="size-4" />
            ) : (
              <Circle className="size-4" />
            )}
            {isComplete
              ? isArabic
                ? "إلغاء تمييز الإنجاز"
                : "Mark as not done"
              : isArabic
                ? "تمييز كمنجز"
                : "Mark as done"}
          </button>
          {error && <p className="text-caption text-red-500">{error}</p>}
        </div>
      )}
    </div>
  );
}
