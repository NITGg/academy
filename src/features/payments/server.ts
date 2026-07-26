import "server-only";
import { callMoodleRest } from "@/lib/moodle-server";
import type { PaymentHistoryItem } from "./types";

export async function getPaymentHistory(wstoken: string): Promise<PaymentHistoryItem[]> {
  try {
    const data = await callMoodleRest<PaymentHistoryItem[]>({
      functionName: "local_payments_get_payment_history",
      token: wstoken,
      params: { page: 0, perpage: 100 },
    });
    return Array.isArray(data) ? data : [];
  } catch {
    return [];
  }
}
