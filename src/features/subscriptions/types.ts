export interface SubscriptionOffer {
  name: string;
  discount_type: string;
  discount_value: number;
  discount: number;
  original: number;
  final: number;
  label: string;
}

export interface AvailableSubscription {
  id: number;
  name: string;
  description?: string;
  price: string;
  duration_days: number;
  status: string;
  courses?: { id: number; fullname: string }[];
  offer?: SubscriptionOffer;
}

export interface MySubscription {
  id: number;
  subscriptionid: number;
  name: string;
  price_paid: string;
  status: string;
  timeactivated: number;
  expires_at: number;
  remaining_days: number;
  duration_days: number;
  courses?: { id: number; fullname: string }[];
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
