"use client";

import { useState } from "react";
import { ShoppingCart } from "lucide-react";
import { BuyCourseModal } from "./BuyCourseModal";

interface CourseBuyButtonProps {
  courseId: number;
  courseName: string;
  price: number;
  originalPrice?: number;
  currency?: string;
  label: string;
}

export function CourseBuyButton({
  courseId,
  courseName,
  price,
  originalPrice,
  currency = "جنيه",
  label,
}: CourseBuyButtonProps) {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
      >
        <ShoppingCart className="size-4" />
        {label}
      </button>

      <BuyCourseModal
        courseId={courseId}
        courseName={courseName}
        basePrice={price}
        originalPrice={originalPrice}
        currency={currency}
        open={open}
        onClose={() => setOpen(false)}
      />
    </>
  );
}
