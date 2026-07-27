"use client";

import { useEffect } from "react";
import { toast } from "sonner";

/**
 * Shows a success/failure toast when the user lands back from the payment gateway.
 * The Moodle payment callback redirects here with `?payment=success|failed` (see
 * local_payments frontend_url setting). We consume the flag once, then strip it from
 * the URL so a refresh doesn't re-fire the toast.
 */
export function PaymentResultToast() {
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const status = params.get("payment");
    if (!status) return;

    if (status === "success") {
      toast.success("تمت عملية الدفع بنجاح 🎉");
    } else if (status === "failed") {
      toast.error("فشلت عملية الدفع أو تم إلغاؤها");
    }

    params.delete("payment");
    const qs = params.toString();
    const url = window.location.pathname + (qs ? `?${qs}` : "") + window.location.hash;
    window.history.replaceState(null, "", url);
  }, []);

  return null;
}
