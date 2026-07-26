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
