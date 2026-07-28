"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { Pencil, Lock, Loader2, X, Eye, EyeOff } from "lucide-react";
import { Button } from "@/components/ui/button";
import { authService } from "@/services/auth.service";
import { useAuthStore } from "@/store/useAuthStore";
import type { User } from "@/types";

interface ProfileActionsProps {
  user: User;
}

export function ProfileActions({ user }: ProfileActionsProps) {
  const setUser = useAuthStore((state) => state.setUser);
  
  // Modals state
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [isPasswordOpen, setIsPasswordOpen] = useState(false);

  // Edit profile form state
  const [firstname, setFirstname] = useState(user.firstname || "");
  const [lastname, setLastname] = useState(user.lastname || "");
  const [phone, setPhone] = useState(user.phone || user.parentPhone || "");
  const [year, setYear] = useState(user.year || "");
  const [yearOptions, setYearOptions] = useState<string[]>([]);
  const [isUpdatingProfile, setIsUpdatingProfile] = useState(false);

  // Change password form state
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showCurrentPass, setShowCurrentPass] = useState(false);
  const [showNewPass, setShowNewPass] = useState(false);
  const [showConfirmPass, setShowConfirmPass] = useState(false);
  const [isChangingPassword, setIsChangingPassword] = useState(false);

  useEffect(() => {
    async function loadYears() {
      try {
        const res = await authService.getProfileFields();
        const yearField = res.data.fields.find((f) => f.shortname === "year");
        if (yearField?.options && yearField.options.length > 0) {
          setYearOptions(yearField.options);
        } else {
          setYearOptions(["الأول الثانوي", "الثاني الثانوي", "الثالث الثانوي", "Secondary 1", "Secondary 2", "Secondary 3"]);
        }
      } catch {
        setYearOptions(["الأول الثانوي", "الثاني الثانوي", "الثالث الثانوي", "Secondary 1", "Secondary 2", "Secondary 3"]);
      }
    }
    loadYears();
  }, []);

  // Update profile handler
  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      setIsUpdatingProfile(true);
      const res = await authService.updateProfile({
        firstname,
        lastname,
        phone,
        year,
        parentPhone: phone,
      });
      setUser(res.data.user);
      toast.success("تم تحديث الملف الشخصي بنجاح");
      setIsEditOpen(false);
      window.location.reload();
    } catch (err: unknown) {
      const errorObj = err as { response?: { data?: { error?: string } } };
      toast.error(errorObj.response?.data?.error || "حدث خطأ أثناء تحديث البيانات");
    } finally {
      setIsUpdatingProfile(false);
    }
  };

  // Change password handler
  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (newPassword.length < 6) {
      toast.error("كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل");
      return;
    }
    if (newPassword !== confirmPassword) {
      toast.error("كلمة المرور الجديدة وتأكيدها غير متطابقين");
      return;
    }

    try {
      setIsChangingPassword(true);
      await authService.changePassword({
        currentPassword,
        newPassword,
      });
      toast.success("تم تغيير كلمة المرور بنجاح");
      setIsPasswordOpen(false);
      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
    } catch (err: unknown) {
      const errorObj = err as { response?: { data?: { error?: string } } };
      toast.error(errorObj.response?.data?.error || "فشل تغيير كلمة المرور");
    } finally {
      setIsChangingPassword(false);
    }
  };

  return (
    <>
      {/* Action Buttons */}
      <div className="space-y-3">
        <Button
          variant="default"
          size="lg"
          className="w-full rounded-xl gap-2 font-semibold"
          onClick={() => setIsEditOpen(true)}
        >
          <Pencil className="size-4" />
          تعديل الملف الشخصي
        </Button>
        <Button
          variant="outline"
          size="lg"
          className="w-full rounded-xl gap-2 font-semibold"
          onClick={() => setIsPasswordOpen(true)}
        >
          <Lock className="size-4" />
          تغيير كلمة المرور
        </Button>
      </div>

      {/* Edit Profile Modal */}
      {isEditOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200">
          <div className="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-lg space-y-4">
            <div className="flex items-center justify-between border-b border-border pb-3">
              <h2 className="text-h3 font-bold text-foreground">تعديل الملف الشخصي</h2>
              <button
                type="button"
                onClick={() => setIsEditOpen(false)}
                className="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                <X className="size-5" />
              </button>
            </div>

            <form onSubmit={handleUpdateProfile} className="space-y-4 pt-1">
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                  <label className="text-caption font-medium text-foreground">الاسم الأول</label>
                  <input
                    type="text"
                    value={firstname}
                    onChange={(e) => setFirstname(e.target.value)}
                    className="h-10 w-full rounded-xl border border-input bg-background px-3 text-caption focus:outline-none focus:ring-2 focus:ring-ring"
                    required
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-caption font-medium text-foreground">الاسم الأخير</label>
                  <input
                    type="text"
                    value={lastname}
                    onChange={(e) => setLastname(e.target.value)}
                    className="h-10 w-full rounded-xl border border-input bg-background px-3 text-caption focus:outline-none focus:ring-2 focus:ring-ring"
                    required
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-caption font-medium text-foreground">رقم الهاتف</label>
                <input
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="01xxxxxxxxx"
                  dir="ltr"
                  className="h-10 w-full rounded-xl border border-input bg-background px-3 text-caption placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-caption font-medium text-foreground">العام الدراسي</label>
                <select
                  value={year}
                  onChange={(e) => setYear(e.target.value)}
                  className="h-10 w-full rounded-xl border border-input bg-background px-3 text-caption focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="">اختر العام الدراسي</option>
                  {yearOptions.map((opt) => (
                    <option key={opt} value={opt}>
                      {opt}
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex gap-3 pt-2">
                <Button
                  type="submit"
                  className="flex-1 h-11 rounded-xl font-semibold"
                  disabled={isUpdatingProfile}
                >
                  {isUpdatingProfile ? <Loader2 className="size-4 animate-spin me-2" /> : null}
                  حفظ التعديلات
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  className="h-11 rounded-xl font-semibold"
                  onClick={() => setIsEditOpen(false)}
                >
                  إلغاء
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Change Password Modal */}
      {isPasswordOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200">
          <div className="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-lg space-y-4">
            <div className="flex items-center justify-between border-b border-border pb-3">
              <h2 className="text-h3 font-bold text-foreground">تغيير كلمة المرور</h2>
              <button
                type="button"
                onClick={() => setIsPasswordOpen(false)}
                className="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                <X className="size-5" />
              </button>
            </div>

            <form onSubmit={handleChangePassword} className="space-y-4 pt-1">
              <div className="space-y-1.5">
                <label className="text-caption font-medium text-foreground">كلمة المرور الحالية</label>
                <div className="relative">
                  <input
                    type={showCurrentPass ? "text" : "password"}
                    value={currentPassword}
                    onChange={(e) => setCurrentPassword(e.target.value)}
                    placeholder="••••••••"
                    className="h-10 w-full rounded-xl border border-input bg-background ps-4 pe-10 text-caption placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowCurrentPass(!showCurrentPass)}
                    className="absolute inset-y-0 end-0 flex items-center pe-3 text-muted-foreground hover:text-foreground"
                  >
                    {showCurrentPass ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                  </button>
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-caption font-medium text-foreground">كلمة المرور الجديدة</label>
                <div className="relative">
                  <input
                    type={showNewPass ? "text" : "password"}
                    value={newPassword}
                    onChange={(e) => setNewPassword(e.target.value)}
                    placeholder="••••••••"
                    className="h-10 w-full rounded-xl border border-input bg-background ps-4 pe-10 text-caption placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowNewPass(!showNewPass)}
                    className="absolute inset-y-0 end-0 flex items-center pe-3 text-muted-foreground hover:text-foreground"
                  >
                    {showNewPass ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                  </button>
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-caption font-medium text-foreground">تأكيد كلمة المرور الجديدة</label>
                <div className="relative">
                  <input
                    type={showConfirmPass ? "text" : "password"}
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    placeholder="••••••••"
                    className="h-10 w-full rounded-xl border border-input bg-background ps-4 pe-10 text-caption placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowConfirmPass(!showConfirmPass)}
                    className="absolute inset-y-0 end-0 flex items-center pe-3 text-muted-foreground hover:text-foreground"
                  >
                    {showConfirmPass ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                  </button>
                </div>
              </div>

              <div className="flex gap-3 pt-2">
                <Button
                  type="submit"
                  className="flex-1 h-11 rounded-xl font-semibold"
                  disabled={isChangingPassword}
                >
                  {isChangingPassword ? <Loader2 className="size-4 animate-spin me-2" /> : null}
                  تحديث كلمة المرور
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  className="h-11 rounded-xl font-semibold"
                  onClick={() => setIsPasswordOpen(false)}
                >
                  إلغاء
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}
