export interface ConversationMember {
  id: number;
  fullname: string;
  profileimageurl?: string;
  isonline?: boolean;
  isblocked?: boolean;
}

export interface ChatMessage {
  id: number;
  useridfrom: number;
  text: string;
  timecreated: number;
}

export interface Conversation {
  id: number;
  type: number;
  name: string;
  imageurl?: string;
  unreadcount?: number;
  members: ConversationMember[];
  messages: ChatMessage[];
}

export interface ConversationThread {
  id: number;
  members: ConversationMember[];
  messages: ChatMessage[];
}
