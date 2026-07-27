"use client";

import { useState, useTransition, useRef, useEffect } from "react";
import Image from "next/image";
import { MessageCircle, Send, ArrowRight, Loader2, Plus, Search, X, User } from "lucide-react";
import { cn } from "@/lib/utils";
import type { Conversation, ChatMessage, ConversationMember } from "../types";
import type { Teacher } from "@/features/teachers/types";
import {
  fetchThreadAction,
  sendMessageAction,
  sendInstantMessageAction,
  markConversationReadAction,
} from "../actions";

// ── helpers ──────────────────────────────────────────────────────────────────

function formatTime(ts: number): string {
  if (!ts) return "";
  const d = new Date(ts * 1000);
  const now = new Date();
  const isToday =
    d.getDate() === now.getDate() &&
    d.getMonth() === now.getMonth() &&
    d.getFullYear() === now.getFullYear();

  const h = String(d.getHours()).padStart(2, "0");
  const m = String(d.getMinutes()).padStart(2, "0");
  if (isToday) return `${h}:${m}`;

  const day = String(d.getDate()).padStart(2, "0");
  const month = String(d.getMonth() + 1).padStart(2, "0");
  return `${d.getFullYear()}/${month}/${day} ${h}:${m}`;
}

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, "").replace(/&nbsp;/g, " ").trim();
}

// ── sub-components ────────────────────────────────────────────────────────────

function Avatar({
  src,
  name,
  isOnline,
  size = "md",
}: {
  src?: string;
  name: string;
  isOnline?: boolean;
  size?: "sm" | "md";
}) {
  const dim = size === "sm" ? "size-9" : "size-11";
  return (
    <div className={cn("relative shrink-0 overflow-hidden rounded-full bg-muted border border-border", dim)}>
      {src ? (
        <Image src={src} alt={name} fill sizes="44px" className="object-cover" />
      ) : (
        <span className="flex h-full w-full items-center justify-center text-base font-bold text-primary">
          {name.charAt(0)}
        </span>
      )}
      {isOnline && (
        <span className="absolute bottom-0 end-0 size-2.5 rounded-full border-2 border-card bg-emerald-500" />
      )}
    </div>
  );
}

// ── ConversationList ──────────────────────────────────────────────────────────

function ConversationList({
  conversations,
  currentUserId,
  activeId,
  onSelect,
}: {
  conversations: Conversation[];
  currentUserId: number;
  activeId: number | null;
  onSelect: (conv: Conversation) => void;
}) {
  if (conversations.length === 0) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center gap-2 text-muted-foreground">
        <MessageCircle className="size-10 opacity-20" />
        <p className="text-caption">لا توجد محادثات</p>
      </div>
    );
  }

  return (
    <div className="flex-1 overflow-y-auto divide-y divide-border">
      {conversations.map((conv) => {
        const other =
          conv.members.find((m) => m.id !== currentUserId) ?? conv.members[0];
        const displayName = conv.name || other?.fullname || "محادثة";
        const avatar = conv.imageurl || other?.profileimageurl;
        const sortedMsgs = (conv.messages ?? []).slice().sort((a, b) => a.timecreated - b.timecreated || a.id - b.id);
        const lastMsg = sortedMsgs[sortedMsgs.length - 1];
        const preview = lastMsg ? stripHtml(lastMsg.text) : "";
        const hasUnread = (conv.unreadcount ?? 0) > 0;
        const isActive = conv.id === activeId;

        return (
          <button
            key={conv.id}
            onClick={() => onSelect(conv)}
            className={cn(
              "w-full flex items-center gap-3 px-4 py-3 text-start transition-colors",
              isActive
                ? "bg-primary/10"
                : "hover:bg-muted/40"
            )}
          >
            <Avatar
              src={avatar}
              name={displayName}
              isOnline={other?.isonline}
            />
            <div className="min-w-0 flex-1">
              <div className="flex items-center justify-between gap-1">
                <span className="text-[11px] text-muted-foreground shrink-0">
                  {lastMsg ? formatTime(lastMsg.timecreated) : ""}
                </span>
                <span
                  className={cn(
                    "truncate text-small font-bold",
                    hasUnread ? "text-foreground" : "text-foreground/80"
                  )}
                >
                  {displayName}
                </span>
              </div>
              <div className="flex items-center justify-between gap-1 mt-0.5">
                {hasUnread ? (
                  <span className="shrink-0 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">
                    {conv.unreadcount}
                  </span>
                ) : (
                  <span />
                )}
                <p className="truncate text-[11px] text-muted-foreground">
                  {preview}
                </p>
              </div>
            </div>
          </button>
        );
      })}
    </div>
  );
}

// ── ChatThread ────────────────────────────────────────────────────────────────

function ChatThread({
  messages,
  members,
  currentUserId,
  isLoading,
}: {
  messages: ChatMessage[];
  members: ConversationMember[];
  currentUserId: number;
  isLoading: boolean;
}) {
  const bottomRef = useRef<HTMLDivElement>(null);

  const sortedMessages = [...messages].sort(
    (a, b) => a.timecreated - b.timecreated || a.id - b.id
  );

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [sortedMessages.length]);

  if (isLoading) {
    return (
      <div className="flex flex-1 items-center justify-center text-muted-foreground">
        <Loader2 className="size-6 animate-spin" />
      </div>
    );
  }

  if (sortedMessages.length === 0) {
    return (
      <div className="flex flex-1 items-center justify-center text-muted-foreground">
        <p className="text-caption">لا توجد رسائل بعد</p>
      </div>
    );
  }

  const memberMap = Object.fromEntries(members.map((m) => [m.id, m]));

  return (
    <div className="flex-1 overflow-y-auto px-4 py-4 space-y-3">
      {sortedMessages.map((msg) => {
        const isMine = msg.useridfrom === currentUserId;
        const sender = memberMap[msg.useridfrom];
        const text = stripHtml(msg.text);

        return (
          <div
            key={msg.id}
            className={cn("flex items-end gap-2", isMine ? "justify-start" : "justify-end")}
          >
            {!isMine && sender && (
              <Avatar
                src={sender.profileimageurl}
                name={sender.fullname}
                size="sm"
              />
            )}
            <div
              className={cn(
                "max-w-[70%] rounded-2xl px-3.5 py-2.5",
                isMine
                  ? "rounded-ss-sm bg-primary text-primary-foreground"
                  : "rounded-se-sm bg-muted text-foreground"
              )}
            >
              <p className="text-small leading-relaxed whitespace-pre-wrap break-words">
                {text}
              </p>
              <p
                className={cn(
                  "mt-1 text-[10px] text-end",
                  isMine ? "text-primary-foreground/70" : "text-muted-foreground"
                )}
              >
                {formatTime(msg.timecreated)}
              </p>
            </div>
          </div>
        );
      })}
      <div ref={bottomRef} />
    </div>
  );
}

// ── MessageInput ──────────────────────────────────────────────────────────────

function MessageInput({
  onSend,
  disabled,
}: {
  onSend: (text: string) => void;
  disabled: boolean;
}) {
  const [text, setText] = useState("");

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const trimmed = text.trim();
    if (!trimmed) return;
    onSend(trimmed);
    setText("");
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="flex items-center gap-2 border-t border-border bg-card px-3 py-3"
    >
      <button
        type="submit"
        disabled={disabled || !text.trim()}
        className="shrink-0 flex size-9 items-center justify-center rounded-full bg-primary text-primary-foreground transition-opacity disabled:opacity-40"
      >
        {disabled ? (
          <Loader2 className="size-4 animate-spin" />
        ) : (
          <Send className="size-4" style={{ transform: "scaleX(-1)" }} />
        )}
      </button>
      <input
        value={text}
        onChange={(e) => setText(e.target.value)}
        placeholder="اكتب رسالة..."
        disabled={disabled}
        className="flex-1 bg-transparent text-small text-foreground placeholder:text-muted-foreground outline-none text-end disabled:opacity-50"
        dir="rtl"
      />
    </form>
  );
}

// ── NewChatModal ──────────────────────────────────────────────────────────────

function NewChatModal({
  isOpen,
  onClose,
  teachers,
  currentUserId,
  onStartChat,
}: {
  isOpen: boolean;
  onClose: () => void;
  teachers: Teacher[];
  currentUserId: number;
  onStartChat: (teacher: Teacher, text: string) => Promise<void>;
}) {
  const [search, setSearch] = useState("");
  const [selectedTeacher, setSelectedTeacher] = useState<Teacher | null>(null);
  const [initialText, setInitialText] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!isOpen) return null;

  const filteredTeachers = teachers.filter((t) => {
    if (t.userid === currentUserId) return false;
    const q = search.trim().toLowerCase();
    if (!q) return true;
    const nameMatch = t.fullname.toLowerCase().includes(q);
    const subjectMatch = t.subjects?.some((s) => s.subject.toLowerCase().includes(q));
    return nameMatch || subjectMatch;
  });

  async function handleSend() {
    if (!selectedTeacher || !initialText.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      await onStartChat(selectedTeacher, initialText.trim());
      setSelectedTeacher(null);
      setInitialText("");
      onClose();
    } catch (err) {
      setError(err instanceof Error ? err.message : "تعذّر بدء المحادثة");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-md rounded-2xl bg-card p-6 shadow-xl border border-border space-y-4">
        <div className="flex items-center justify-between border-b border-border pb-3">
          <h3 className="text-base font-bold text-foreground">محادثة جديدة مع مدرس</h3>
          <button onClick={onClose} className="text-muted-foreground hover:text-foreground">
            <X className="size-5" />
          </button>
        </div>

        {error && (
          <div className="rounded-xl bg-destructive/10 p-3 text-xs text-destructive font-medium">
            {error}
          </div>
        )}

        {!selectedTeacher ? (
          <>
            {/* Search input */}
            <div className="relative">
              <Search className="absolute start-3 top-2.5 size-4 text-muted-foreground" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="ابحث عن مدرس بالاسم أو المادة..."
                className="w-full rounded-xl border border-border bg-background ps-9 pe-3 py-2 text-xs text-foreground placeholder:text-muted-foreground outline-none focus:ring-1 focus:ring-primary"
                dir="rtl"
              />
            </div>

            {/* Teachers list */}
            <div className="max-h-64 overflow-y-auto divide-y divide-border rounded-xl border border-border">
              {filteredTeachers.length === 0 ? (
                <div className="p-4 text-center text-xs text-muted-foreground">
                  لا يوجد مدرسين مطابقين للبحث
                </div>
              ) : (
                filteredTeachers.map((t) => (
                  <button
                    key={t.userid}
                    onClick={() => setSelectedTeacher(t)}
                    className="w-full flex items-center gap-3 p-3 text-start transition-colors hover:bg-muted/40"
                  >
                    <div className="relative size-9 shrink-0 overflow-hidden rounded-full bg-muted border border-border">
                      {t.photourl ? (
                        <Image src={t.photourl} alt={t.fullname} fill sizes="36px" className="object-cover" />
                      ) : (
                        <User className="size-5 m-auto text-muted-foreground" />
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="text-xs font-bold text-foreground truncate">{t.fullname}</p>
                      {t.headline && (
                        <p className="text-[10px] text-muted-foreground truncate">{t.headline}</p>
                      )}
                    </div>
                  </button>
                ))
              )}
            </div>
          </>
        ) : (
          <div className="space-y-4">
            <div className="flex items-center justify-between rounded-xl bg-muted/30 p-3">
              <div className="flex items-center gap-3">
                <Avatar src={selectedTeacher.photourl} name={selectedTeacher.fullname} size="sm" />
                <div>
                  <p className="text-xs font-bold">{selectedTeacher.fullname}</p>
                  <p className="text-[10px] text-muted-foreground">{selectedTeacher.headline || "مدرس"}</p>
                </div>
              </div>
              <button
                onClick={() => setSelectedTeacher(null)}
                className="text-xs text-primary font-medium hover:underline"
              >
                تغيير
              </button>
            </div>

            <div className="space-y-2">
              <label className="text-xs font-bold text-foreground">الرسالة الأولى</label>
              <textarea
                rows={3}
                value={initialText}
                onChange={(e) => setInitialText(e.target.value)}
                placeholder="اكتب رسالتك للبدء..."
                className="w-full rounded-xl border border-border bg-background p-3 text-xs text-foreground outline-none focus:ring-1 focus:ring-primary"
                dir="rtl"
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setSelectedTeacher(null)}
                className="rounded-xl px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-muted"
              >
                رجوع
              </button>
              <button
                type="button"
                onClick={handleSend}
                disabled={submitting || !initialText.trim()}
                className="flex items-center gap-2 rounded-xl bg-primary px-5 py-2 text-xs font-bold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
              >
                {submitting ? (
                  <>
                    <Loader2 className="size-4 animate-spin" />
                    جاري الإرسال...
                  </>
                ) : (
                  "إرسال الرسالة"
                )}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

// ── Main component ────────────────────────────────────────────────────────────

interface MessagesClientProps {
  conversations: Conversation[];
  currentUserId: number;
  initialConvid?: number;
  teachers?: Teacher[];
}

export function MessagesClient({
  conversations: initialConversations,
  currentUserId,
  initialConvid,
  teachers = [],
}: MessagesClientProps) {
  const [conversations, setConversations] = useState<Conversation[]>(initialConversations);
  const [activeConv, setActiveConv] = useState<Conversation | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [members, setMembers] = useState<ConversationMember[]>([]);
  const [threadLoading, startThreadTransition] = useTransition();
  const [sending, startSendTransition] = useTransition();
  // mobile: show thread panel over list
  const [mobileShowThread, setMobileShowThread] = useState(false);
  const [showNewChatModal, setShowNewChatModal] = useState(false);

  function openConversation(conv: Conversation) {
    setActiveConv(conv);
    setMobileShowThread(true);
    startThreadTransition(async () => {
      const thread = await fetchThreadAction(conv.id);
      if (thread) {
        setMessages(thread.messages);
        setMembers(thread.members);
      }
      // clear unread locally
      setConversations((prev) =>
        prev.map((c) => (c.id === conv.id ? { ...c, unreadcount: 0 } : c))
      );
      await markConversationReadAction(conv.id);
    });
  }

  // Support deep linking to a specific conversation id
  useEffect(() => {
    if (!initialConvid) return;
    const existing = conversations.find((c) => c.id === initialConvid);
    if (existing) {
      openConversation(existing);
    } else {
      startThreadTransition(async () => {
        const thread = await fetchThreadAction(initialConvid);
        if (thread) {
          const other = thread.members.find((m) => m.id !== currentUserId) ?? thread.members[0];
          const newConv: Conversation = {
            id: thread.id,
            type: 1,
            name: other?.fullname || "محادثة",
            imageurl: other?.profileimageurl,
            unreadcount: 0,
            members: thread.members,
            messages: thread.messages,
          };
          setConversations((prev) => [newConv, ...prev.filter((c) => c.id !== newConv.id)]);
          setActiveConv(newConv);
          setMessages(thread.messages);
          setMembers(thread.members);
          setMobileShowThread(true);
        }
      });
    }
  }, [initialConvid]);

  function handleSend(text: string) {
    if (!activeConv) return;
    startSendTransition(async () => {
      const result = await sendMessageAction(activeConv.id, text);
      if (result.ok) {
        // Refetch thread to get server-confirmed message
        const thread = await fetchThreadAction(activeConv.id);
        if (thread) {
          setMessages(thread.messages);
          setMembers(thread.members);
        }
      }
    });
  }

  async function handleStartNewChat(teacher: Teacher, text: string) {
    const res = await sendInstantMessageAction(teacher.userid, text);
    if (!res.ok || !res.conversationid) {
      throw new Error(res.error || "تعذّر إرسال الرسالة");
    }

    const convid = res.conversationid;
    const thread = await fetchThreadAction(convid);
    if (thread) {
      const other = thread.members.find((m) => m.id !== currentUserId) ?? thread.members[0];
      const newConv: Conversation = {
        id: thread.id,
        type: 1,
        name: teacher.fullname || other?.fullname || "محادثة",
        imageurl: teacher.photourl || other?.profileimageurl,
        unreadcount: 0,
        members: thread.members,
        messages: thread.messages,
      };

      setConversations((prev) => [newConv, ...prev.filter((c) => c.id !== convid)]);
      setActiveConv(newConv);
      setMessages(thread.messages);
      setMembers(thread.members);
      setMobileShowThread(true);
    }
  }

  const totalUnread = conversations.reduce(
    (s, c) => s + (c.unreadcount ?? 0),
    0
  );

  const activeOther =
    activeConv?.members.find((m) => m.id !== currentUserId) ??
    activeConv?.members[0];
  const activeDisplayName =
    activeConv?.name || activeOther?.fullname || "محادثة";
  const activeAvatar =
    activeConv?.imageurl || activeOther?.profileimageurl;

  return (
    <div className="flex flex-col gap-0">
      {/* Page header */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <MessageCircle className="size-6 text-primary" />
          <h1 className="text-h1 font-bold">الرسائل</h1>
          {totalUnread > 0 && (
            <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
              {totalUnread}
            </span>
          )}
        </div>

        <button
          onClick={() => setShowNewChatModal(true)}
          className="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-xs font-bold text-primary-foreground shadow-sm transition hover:opacity-90"
        >
          <Plus className="size-4" />
          محادثة جديدة
        </button>
      </div>

      {/* New Chat Modal */}
      <NewChatModal
        isOpen={showNewChatModal}
        onClose={() => setShowNewChatModal(false)}
        teachers={teachers}
        currentUserId={currentUserId}
        onStartChat={handleStartNewChat}
      />

      {/* Split pane */}
      <div className="flex h-[calc(100svh-11rem)] overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
        {/* Conversation list — always visible on desktop, hidden on mobile when thread is open */}
        <div
          className={cn(
            "flex flex-col border-s border-border",
            "w-full md:w-80 lg:w-96 shrink-0",
            mobileShowThread ? "hidden md:flex" : "flex"
          )}
        >
          <div className="flex items-center justify-between px-4 py-3 border-b border-border">
            <p className="text-small font-bold text-foreground">المحادثات</p>
          </div>
          <ConversationList
            conversations={conversations}
            currentUserId={currentUserId}
            activeId={activeConv?.id ?? null}
            onSelect={openConversation}
          />
        </div>

        {/* Chat thread panel */}
        <div
          className={cn(
            "flex flex-1 flex-col",
            !mobileShowThread && "hidden md:flex"
          )}
        >
          {activeConv ? (
            <>
              {/* Thread header */}
              <div className="flex items-center gap-3 border-b border-border px-4 py-3">
                {/* Back button — mobile only */}
                <button
                  className="md:hidden shrink-0 text-muted-foreground"
                  onClick={() => setMobileShowThread(false)}
                  aria-label="رجوع"
                >
                  <ArrowRight className="size-5" />
                </button>
                <Avatar
                  src={activeAvatar}
                  name={activeDisplayName}
                  isOnline={activeOther?.isonline}
                  size="sm"
                />
                <div className="min-w-0 flex-1 text-end">
                  <p className="text-small font-bold truncate">
                    {activeDisplayName}
                  </p>
                  {activeOther?.isonline && (
                    <p className="text-[10px] text-emerald-500">متصل الآن</p>
                  )}
                </div>
              </div>

              {/* Messages */}
              <ChatThread
                messages={messages}
                members={members}
                currentUserId={currentUserId}
                isLoading={threadLoading}
              />

              {/* Input */}
              <MessageInput onSend={handleSend} disabled={sending} />
            </>
          ) : (
            <div className="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground">
              <MessageCircle className="size-12 opacity-15" />
              <p className="text-caption">اختر محادثة للبدء</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

