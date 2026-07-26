import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { SESSION_COOKIE } from "@/config/constants";

const protectedPrefixes = ["/courses", "/lessons", "/profile", "/messages", "/notifications", "/payments"];

export default function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const hasSession = request.cookies.has(SESSION_COOKIE);

  const isProtected = protectedPrefixes.some((prefix) =>
    pathname.startsWith(prefix)
  );

  if (isProtected && !hasSession) {
    const loginUrl = new URL("/login", request.url);
    loginUrl.searchParams.set("from", pathname);
    return NextResponse.redirect(loginUrl);
  }

  if ((pathname === "/login" || pathname === "/register") && hasSession) {
    return NextResponse.redirect(new URL("/", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    "/courses/:path*",
    "/lessons/:path*",
    "/profile/:path*",
    "/messages/:path*",
    "/notifications/:path*",
    "/payments/:path*",
    "/login",
    "/register",
  ],
};
