"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations, useLocale } from "next-intl";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import Image from "next/image";
import { toast } from "sonner";
import { Eye, EyeOff, Loader2, LogIn } from "lucide-react";

import { loginSchema, type LoginInput } from "@/validations/auth.schema";
import { authService } from "@/services/auth.service";
import { useAuthStore } from "@/store/useAuthStore";
import { Button } from "@/components/ui/button";

export default function LoginPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const searchParams = useSearchParams();
  const setUser = useAuthStore((state) => state.setUser);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const fromUrl = searchParams.get("from") || "/";

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginInput>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = async (data: LoginInput) => {
    try {
      setIsSubmitting(true);
      const res = await authService.login(data);
      setUser(res.data.user);
      toast.success(locale === "ar" ? "تم تسجيل الدخول بنجاح" : "Logged in successfully");
      router.push(fromUrl);
    } catch (err: unknown) {
      const errorObj = err as { response?: { data?: { error?: string; code?: string } } };
      let message =
        errorObj.response?.data?.error ||
        (locale === "ar" ? "فشل تسجيل الدخول. تحقق من اسم المستخدم وكلمة المرور" : "Login failed. Check credentials");

      if (errorObj.response?.data?.code === "not_student") {
        message =
          locale === "ar"
            ? "هذه البوابة مخصصة للطلاب فقط. لا يمكن لحسابات الإدارة تسجيل الدخول من هنا."
            : "This portal is for students only. Administrator accounts cannot sign in here.";
      }

      toast.error(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
      <div className="w-full max-w-md space-y-6 rounded-2xl border border-border bg-card p-6 sm:p-8 shadow-sm">
        {/* Header / Logo */}
        <div className="text-center space-y-2">
          <div className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm">
            <LogIn className="size-6" />
          </div>
          <h1 className="text-h2 font-bold">{t("auth.login")}</h1>
          <p className="text-caption text-muted-foreground">{t("app.tagline")}</p>
        </div>

        {/* Login Form */}
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-1.5">
            <label className="text-caption font-medium">{t("auth.email")}</label>
            <input
              type="text"
              placeholder={t("auth.emailPlaceholder")}
              className="h-10 w-full rounded-xl border border-input bg-background px-3 text-caption placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              {...register("username")}
            />
            {errors.username && (
              <p className="text-small text-destructive">{errors.username.message}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <label className="text-caption font-medium">{t("auth.password")}</label>
              <Link href="#" className="text-small text-primary hover:underline">
                {t("auth.forgotPassword")}
              </Link>
            </div>
            <div className="relative">
              <input
                type={showPassword ? "text" : "password"}
                placeholder="••••••••"
                className="h-10 w-full rounded-xl border border-input bg-background ps-4 pe-10 text-caption placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                {...register("password")}
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute inset-y-0 end-0 flex items-center pe-3 text-muted-foreground hover:text-foreground"
              >
                {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
              </button>
            </div>
            {errors.password && (
              <p className="text-small text-destructive">{errors.password.message}</p>
            )}
          </div>

          <Button type="submit" className="w-full h-11 rounded-xl font-semibold" disabled={isSubmitting}>
            {isSubmitting ? <Loader2 className="size-4 animate-spin me-2" /> : null}
            {t("auth.login")}
          </Button>
        </form>

        {/* Register Link */}
        <div className="text-center text-caption text-muted-foreground">
          <span>{t("auth.noAccount")} </span>
          <Link href="/register" className="font-semibold text-primary hover:underline">
            {t("auth.register")}
          </Link>
        </div>
      </div>
    </div>
  );
}