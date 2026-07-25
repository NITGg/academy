// Shared API response envelopes used across all Moodle endpoints.
// See docs/mobile-reference/nextjs-web-port-reference.md §1 for full shape.

export interface MoodleRestResponse<T = unknown> {
  data?: T;
  exception?: string;
  errorcode?: string;
  message?: string;
  debuginfo?: string;
}

// local_academy/api.php response shape
export interface AcademyApiResponse<T = unknown> {
  status: "success" | "fail";
  data?: T;
  error?: string;
}

// Shared checkout session returned by all *_create_*_checkout calls
export interface CheckoutSession {
  orderId: string;
  checkoutUrl: string;
  expiresAt: string;
  provider: string;
  transactionId: string;
}

// Shared discount preview (one function, item_type switches target)
export interface DiscountPreview {
  original: number;
  offerDiscount: number;
  offerName: string;
  couponDiscount: number;
  couponCode: string;
  discount: number;
  finalPrice: number;
  couponError?: string;
}

// Offer attached to courses, packages, subscriptions, programs
export interface Offer {
  name: string;
  discountType: string;
  discountValue: number;
  discount: number;
  original: number;
  finalPrice: number;
  label: string;
}
