export interface PaymentHistoryItem {
  transaction_id: number;
  order_id: string;
  courseid?: number;
  course_name?: string;
  amount: number;
  original_amount?: number;
  currency: string;
  status: string;
  provider?: string;
  payment_method?: string;
  invoice_number?: string;
  timecreated: number;
}
