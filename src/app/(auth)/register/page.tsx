"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations, useLocale } from "next-intl";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { toast } from "sonner";
import { Loader2, UserPlus } from "lucide-react";

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

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterInput>({
    resolver: zodResolver(registerSchema),
  });

  const onSubmit = async (data: RegisterInput) => {
    try {
      setIsSubmitting(true);
      const res = await authService.register(data);
      setUser(res.data.user);
      toast.success(locale === "ar" ? "تم إنشاء الحساب بنجاح" : "Account created successfully");
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
    <div className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
      <div className="w-full max-w-md space-y-6 rounded-2xl border border-border bg-card p-6 sm:p-8 shadow-sm">
        <div className="text-center space-y-2">
          <div className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm">
            <UserPlus className="size-6" />
          </div>
          <h1 className="text-h2 font-bold">{t("auth.register")}</h1>
          <p className="text-caption text-muted-foreground">{t("app.tagline")}</p>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label className="text-caption font-medium">الاسم الأول</label>
              <input
                type="text"
                placeholder="أحمد"
                className="h-10 w-full rounded-xl border border-input bg-background px-3 text-caption focus:outline-none focus:ring-2 focus:ring-ring"
                {...register("firstname")}
              />
              {errors.firstname && (
                <p className="text-small text-destructive">{errors.firstname.message}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <label className="text-caption font-medium">الاسم الأخير</label>
              <input
                type="text"
                placeholder="محمود"
                className="h-10 w-full rounded-xl border border-input bg-background px-3 text-caption focus:outline-none focus:ring-2 focus:ring-ring"
                {...register("lastname")}
              />
              {errors.lastname && (
                <p className="text-small text-destructive">{errors.lastname.message}</p>
              )}
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-caption font-medium">{t("auth.email")}</label>
            <input
              type="email"
              placeholder={t("auth.emailPlaceholder")}
              className="h-10 w-full rounded-xl border border-input bg-background px-3 text-caption focus:outline-none focus:ring-2 focus:ring-ring"
              {...register("email")}
            />
            {errors.email && (
              <p className="text-small text-destructive">{errors.email.message}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <label className="text-caption font-medium">{t("auth.password")}</label>
            <input
              type="password"
              placeholder="••••••••"
              className="h-10 w-full rounded-xl border border-input bg-background px-3 text-caption focus:outline-none focus:ring-2 focus:ring-ring"
              {...register("password")}
            />
            {errors.password && (
              <p className="text-small text-destructive">{errors.password.message}</p>
            )}
          </div>

          <Button type="submit" className="w-full h-11 rounded-xl font-semibold" disabled={isSubmitting}>
            {isSubmitting ? <Loader2 className="size-4 animate-spin me-2" /> : null}
            {t("auth.register")}
          </Button>
        </form>

        <div className="text-center text-caption text-muted-foreground">
          <span>{t("auth.haveAccount")} </span>
          <Link href="/login" className="font-semibold text-primary hover:underline">
            {t("auth.login")}
          </Link>
        </div>
      </div>
    </div>
  );
}