import { AppShell } from "@/components/layout/app-shell";
import { getSessionFromCookie } from "@/lib/session";
import { getHomeDashboardData } from "@/services/home.service";
import { HomePageClient } from "@/components/home/HomePageClient";

export default async function HomePage() {
  const session = await getSessionFromCookie();
  const data = await getHomeDashboardData(session?.wstoken);

  return (
    <AppShell>
      <HomePageClient
        courses={data.courses}
        myCourses={data.myCourses}
        teachers={data.teachers}
        programs={data.programs}
        packages={data.packages}
        subscriptions={data.subscriptions}
        myPackages={data.myPackages}
        mySubscriptions={data.mySubscriptions}
        myB2bSubscriptions={data.myB2bSubscriptions}
        myPrograms={data.myPrograms}
        isLoggedIn={Boolean(session?.wstoken)}
      />
    </AppShell>
  );
}
