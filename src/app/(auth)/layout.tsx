import type { Metadata } from "next";

export const metadata: Metadata = {
  title: {
    template: "%s | أكاديمية التميز",
    default: "أكاديمية التميز",
  },
};

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
