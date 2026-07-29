export interface CouponTarget {
  item_type: "course" | "package" | "subscription" | "program" | string;
  item_id: number;
  label: string;
}

export interface AvailableCoupon {
  code: string;
  status: "active" | string;
  discount_type: "percent" | "amount" | "fixed" | string;
  discount_value: number;
  max_discount?: number | null;
  usage_type?: string;
  usage_limit?: number | null;
  usage_count?: number;
  /** Unix timestamp in seconds (no underscore) */
  startdate?: number;
  /** Unix timestamp in seconds (no underscore) */
  enddate?: number;
  applies_to?: CouponTarget[];
}
