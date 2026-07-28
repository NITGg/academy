export interface SubscriptionOffer {
  name: string;
  discount_type: string;
  discount_value: number;
  discount: number;
  original: number;
  final: number;
  label: string;
}

export interface SeatOption {
  seats: number;
  discount_percent: number;
}

export interface AvailableSubscription {
  id: number;
  name: string;
  description?: string;
  price: string;
  duration_days: number;
  status: string;
  b2b_enabled?: number;
  seat_options?: SeatOption[];
  courses?: { id: number; fullname: string }[];
  offer?: SubscriptionOffer;
}

export interface MySubscription {
  id: number;
  subscriptionid: number;
  name: string;
  /** "normal" | "b2b" — B2B subscriptions are administered from the B2B dashboard tab. */
  type?: string;
  price_paid: string;
  status: string;
  timeactivated: number;
  expires_at: number;
  remaining_days: number;
  duration_days: number;
  courses?: { id: number; fullname: string }[];
}

// ── B2B administration (matches local_academy b2b_manager) ────────────────────

/** One B2B subscription the current user administers (get_my_b2b_subscriptions). */
export interface B2BSubscription {
  purchaseid: number;
  subscriptionid: number;
  name: string;
  seats: number;
  consumed: number;
  available: number;
  price_paid: string;
  status: string; // active | expired | cancelled
  /** true only while the subscription is active; false ⇒ read-only history (no management). */
  can_manage?: boolean;
  expires_at: number;
}

/** Seat-capacity + per-status counts for one B2B subscription. */
export interface B2BCapacity {
  purchaseid: number;
  capacity: number;
  consumed: number;
  available: number;
  pending: number;
  approved: number;
  rejected: number;
  removed: number;
  removed_returned: number;
  removed_kept: number;
  expires_at: number;
  status: string;
  /** true only while the subscription is active; false ⇒ read-only history (no management). */
  can_manage?: boolean;
}

export interface B2BMember {
  id: number; // membershipid
  userid: number;
  user_fullname: string;
  user_email: string;
  status: string; // pending | approved | rejected | removed | expired
  consumes_seat: number;
  approved_at: number;
  removed_at: number;
  reject_reason: string;
  timecreated: number;
}

export interface B2BInvitation {
  id: number;
  status: string; // active | expired | disabled | revoked
  expires_at: number;
  timecreated: number;
  /** Present only for a still-active link whose raw token is stored. */
  url?: string;
}

export interface B2BDashboard {
  capacity: B2BCapacity;
  members: B2BMember[];
  invitations: B2BInvitation[];
}

/** Result of joining through an invitation link. */
export interface B2BJoinResult {
  membershipid: number;
  status: string; // pending | approved
  existing?: boolean;
}

export interface SubscriptionPaymentRecord {
  id: number;
  subscriptionid: number;
  name: string;
  amount: string;
  method: string;
  reference: string;
  transaction_no: string;
  status: string;
  timecreated: number;
}
