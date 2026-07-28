"use client";

import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations, useLocale } from "next-intl";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { toast } from "sonner";
import { Eye, EyeOff, Loader2 } from "lucide-react";

import { registerSchema, type RegisterInput } from "@/validations/auth.schema";
import { authService } from "@/services/auth.service";
import { useAuthStore } from "@/store/useAuthStore";
import { Button } from "@/components/ui/button";

export default function RegisterPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const setUser = useAuthStore((state) => state.setUser);
  
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [yearOptions, setYearOptions] = useState<string[]>([]);
  const [loadingFields, setLoadingFields] = useState(true);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterInput>({
    resolver: zodResolver(registerSchema),
  });

  useEffect(() => {
    async function fetchYears() {
      try {
        const res = await authService.getProfileFields();
        const yearField = res.data.fields.find((f) => f.shortname === "year");
        if (yearField?.options && yearField.options.length > 0) {
          setYearOptions(yearField.options);
        } else {
          // Default fallbacks if custom field definition isn't configured
          setYearOptions(["الأول الثانوي", "الثاني الثانوي", "الثالث الثانوي"]);
        }
      } catch (err) {
        console.error("Failed to load profile fields:", err);
        setYearOptions(["الأول الثانوي", "الثاني الثانوي", "الثالث الثانوي"]);
      } finally {
        setLoadingFields(false);
      }
    }

    fetchYears();
  }, []);

  const onSubmit = async (data: RegisterInput) => {
    try {
      setIsSubmitting(true);
      const res = await authService.register(data);
      setUser(res.data.user);
      toast.success(
        locale === "ar" ? "تم إنشاء الحساب بنجاح" : "Account created successfully"
      );
      router.push("/");
    } catch (err: unknown) {
      const errorObj = err as { response?: { data?: { error?: string } } };
      const message =
        errorObj.response?.data?.error ||
        (locale === "ar" ? "حدث خطأ أثناء إنشاء الحساب" : "Registration failed");
      toast.error(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen flex-col items-center justify-between bg-background p-4 sm:p-6">
      {/* Top Header Title */}
      <div className="w-full text-center pt-6 pb-2">
        <h1 className="text-h2 font-bold text-foreground">
          {t("auth.register")}
        </h1>
      </div>

      {/* Main Card */}
      <div className="w-full max-w-md space-y-6 rounded-2xl border border-border bg-card p-6 sm:p-8 shadow-sm my-auto">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          {/* Email Field */}
          <div className="space-y-1.5">
            <label className="text-caption font-medium text-foreground">
              {t("auth.email")}
            </label>
            <input
              type="email"
              placeholder={t("auth.emailPlaceholder")}
              dir="ltr"
              className="h-11 w-full rounded-xl border border-input bg-background px-4 text-caption placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              {...register("email")}
            />
            {errors.email && (
              <p className="text-small text-destructive">{errors.email.message}</p>
            )}
          </div>

          {/* Password Field */}
          <div className="space-y-1.5">
            <label className="text-caption font-medium text-foreground">
              {t("auth.password")}
            </label>
            <div className="relative">
              <input
                type={showPassword ? "text" : "password"}
                placeholder="••••••••"
                className="h-11 w-full rounded-xl border border-input bg-background ps-4 pe-10 text-caption placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                {...register("password")}
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute inset-y-0 end-0 flex items-center pe-3 text-muted-foreground hover:text-foreground"
              >
                {showPassword ? (
                  <EyeOff className="size-4" />
                ) : (
                  <Eye className="size-4" />
                )}
              </button>
            </div>
            {errors.password && (
              <p className="text-small text-destructive">
                {errors.password.message}
              </p>
            )}
          </div>

          {/* Confirm Password Field */}
          <div className="space-y-1.5">
            <label className="text-caption font-medium text-foreground">
              {t("auth.confirmPassword")}
            </label>
            <div className="relative">
              <input
                type={showConfirmPassword ? "text" : "password"}
                placeholder="••••••••"
                className="h-11 w-full rounded-xl border border-input bg-background ps-4 pe-10 text-caption placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                {...register("confirmPassword")}
              />
              <button
                type="button"
                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                className="absolute inset-y-0 end-0 flex items-center pe-3 text-muted-foreground hover:text-foreground"
              >
                {showConfirmPassword ? (
                  <EyeOff className="size-4" />
                ) : (
                  <Eye className="size-4" />
                )}
              </button>
            </div>
            {errors.confirmPassword && (
              <p className="text-small text-destructive">
                {errors.confirmPassword.message}
              </p>
            )}
          </div>

          {/* Academic Year Dropdown */}
          <div className="space-y-1.5">
            <label className="text-caption font-medium text-foreground">
              {t("auth.academicYear")}
            </label>
            <select
              className="h-11 w-full rounded-xl border border-input bg-background px-4 text-caption focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
              disabled={loadingFields}
              {...register("year")}
            >
              <option value="">{t("auth.selectAcademicYear")}</option>
              {yearOptions.map((opt) => (
                <option key={opt} value={opt}>
                  {opt}
                </option>
              ))}
            </select>
          </div>

          {/* Parent Phone Field */}
          <div className="space-y-1.5">
            <label className="text-caption font-medium text-foreground">
              {t("auth.parentPhone")}
            </label>
            <input
              type="tel"
              placeholder="01xxxxxxxxx"
              dir="ltr"
              className="h-11 w-full rounded-xl border border-input bg-background px-4 text-caption placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              {...register("parentPhone")}
            />
          </div>

          {/* Terms & Conditions Notice */}
          <div className="text-center text-caption text-muted-foreground pt-1">
            <span>{t("auth.termsNotice")} </span>
            <Link
              href="#"
              className="font-semibold text-primary hover:underline"
            >
              {t("auth.termsLink")}
            </Link>
          </div>

          {/* Form Actions */}
          <div className="space-y-3 pt-2">
            <Button
              type="submit"
              className="w-full h-11 rounded-xl font-semibold text-body"
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <Loader2 className="size-4 animate-spin me-2" />
              ) : null}
              {t("auth.register")}
            </Button>

            <Button
              type="button"
              variant="secondary"
              className="w-full h-11 rounded-xl font-semibold bg-slate-900 text-white hover:bg-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700"
              onClick={() => router.push("/")}
            >
              {t("auth.guestLogin")}
            </Button>
          </div>
        </form>

        {/* Existing Account Link */}
        <div className="text-center text-caption text-muted-foreground">
          <span>{t("auth.haveAccount")} </span>
          <Link
            href="/login"
            className="font-semibold text-primary hover:underline"
          >
            {t("auth.login")}
          </Link>
        </div>
      </div>

      {/* Footer Rights */}
      <div className="w-full text-center text-small text-muted-foreground pb-4 pt-2">
        <p>{t("auth.termsNotice")} <Link href="#" className="text-primary hover:underline">{t("auth.termsLink")}</Link></p>
        <p>{t("auth.allRights")}</p>
      </div>
    </div>
  );
}