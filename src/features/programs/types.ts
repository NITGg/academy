export interface ProgramOffer {
  original: number;
  final: number;
  label: string;
}

export interface CatalogueProgram {
  id: number;
  name: string;
  description?: string;
  free: number;
  price: number;
  currency: string;
  offer?: ProgramOffer;
  owned: number;
  joinable: number;
}

export interface MyProgram {
  id: number;
  name: string;
  timeallocated: number;
  timestart: number;
  timedue: number;
  timeend: number;
  timecompleted: number;
  completed: number;
}

export interface ProgramContentItem {
  itemid: number;
  type: string; // e.g. "set" | "course"
  name: string;
  courseid: number;
  sequencetype?: string;
  timecompleted: number;
  completed: number;
  children?: ProgramContentItem[];
}

export interface ProgramAllocation {
  timeallocated: number;
  timestart: number;
  timedue: number;
  timeend: number;
  timecompleted: number;
  completed: number;
}

export interface ProgramDetails {
  id: number;
  name: string;
  description?: string;
  description_html?: string;
  image?: string;
  free: number;
  price: number;
  currency?: string;
  offer?: ProgramOffer;
  owned: number;
  joinable: number;
  allocation?: ProgramAllocation;
  content: ProgramContentItem[];
}

export interface CertificateEligibilityResult {
  passed: number;
  label: string;
  actual: number;
  required: number;
  unit: string;
}

export interface ProgramCertificate {
  certificateid: number;
  name: string;
  type: string;
  eligible: number;
  enabled: number;
  operator: string;
  open_state: string;
  openable: number;
  externalref: number;
  results?: CertificateEligibilityResult[];
}

