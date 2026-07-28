"use client";

import { useState, useRef } from "react";
import Image from "next/image";
import { Camera, Loader2 } from "lucide-react";
import { toast } from "sonner";
import { authService } from "@/services/auth.service";
import { useAuthStore } from "@/store/useAuthStore";
import type { User } from "@/types";

interface ProfileHeaderProps {
  user: User;
}

export function ProfileHeader({ user: initialUser }: ProfileHeaderProps) {
  const storeUser = useAuthStore((state) => state.user);
  const setUser = useAuthStore((state) => state.setUser);

  const currentUser = storeUser || initialUser;
  const [isUploading, setIsUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const fullname = `${currentUser.firstname || ""} ${currentUser.lastname || ""}`.trim() || "—";
  const initials = currentUser.firstname ? currentUser.firstname.charAt(0) : "؟";

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith("image/")) {
      toast.error("الرجاء اختيار ملف صورة صالحة");
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      toast.error("حجم الصورة يجب ألا يتجاوز 10 ميجابايت");
      return;
    }

    try {
      setIsUploading(true);
      const res = await authService.uploadProfileImage(file);
      setUser(res.data.user);
      toast.success("تم تحديث صورة الملف الشخصي بنجاح");
    } catch (err: unknown) {
      const errorObj = err as { response?: { data?: { error?: string } }; message?: string };
      toast.error(
        errorObj.response?.data?.error || errorObj.message || "حدث خطأ أثناء رفع الصورة"
      );
    } finally {
      setIsUploading(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }
    }
  };

  return (
    <div className="flex flex-col items-center gap-3 rounded-2xl bg-primary px-6 py-8 text-primary-foreground shadow-sm">
      {/* Avatar Container with Upload Overlay */}
      <div className="relative group">
        <div className="relative size-28 overflow-hidden rounded-full border-4 border-primary-foreground/30 bg-primary-foreground/20 shadow-md">
          {currentUser.pictureUrl ? (
            <Image
              src={currentUser.pictureUrl}
              alt={fullname}
              fill
              sizes="112px"
              className="object-cover"
              priority
            />
          ) : (
            <span className="flex h-full w-full items-center justify-center text-4xl font-bold text-primary-foreground">
              {initials}
            </span>
          )}

          {/* Loading Overlay */}
          {isUploading && (
            <div className="absolute inset-0 flex items-center justify-center bg-black/50 backdrop-blur-xs">
              <Loader2 className="size-8 animate-spin text-white" />
            </div>
          )}
        </div>

        {/* Camera Upload Button */}
        <button
          type="button"
          onClick={() => fileInputRef.current?.click()}
          disabled={isUploading}
          title="تغيير الصورة الشخصية"
          aria-label="تغيير الصورة الشخصية"
          className="absolute bottom-0 end-0 flex size-9 items-center justify-center rounded-full border-2 border-primary bg-background text-primary shadow-lg transition-transform hover:scale-110 focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
        >
          {isUploading ? (
            <Loader2 className="size-4 animate-spin" />
          ) : (
            <Camera className="size-4" />
          )}
        </button>

        {/* Hidden File Input */}
        <input
          type="file"
          ref={fileInputRef}
          accept="image/*"
          onChange={handleFileChange}
          className="hidden"
        />
      </div>

      {/* User Name */}
      <h1 className="text-h1 font-bold text-center">{fullname}</h1>
    </div>
  );
}
