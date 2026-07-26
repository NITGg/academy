export interface PackageOffer {
  name: string;
  discount_type: string;
  discount_value: number;
  discount: number;
  original: number;
  final: number;
  label: string;
}

export interface AvailablePackage {
  id: number;
  name: string;
  description?: string;
  flex_count: number;
  price: number;
  expiration_days: number;
  status: string;
  offer?: PackageOffer;
}

export interface MyPackage {
  id: number;
  packageid: number;
  name: string;
  total_flex: number;
  remaining_flex: number;
  used_flex: number;
  price_paid: string;
  status: string;
  timeactivated: number;
  expires_at: number;
  expiration_days: number;
}

export interface PackagePaymentRecord {
  id: number;
  packageid: number;
  name: string;
  amount: string;
  method: string;
  reference: string;
  transaction_no: string;
  status: string;
  timecreated: number;
}
