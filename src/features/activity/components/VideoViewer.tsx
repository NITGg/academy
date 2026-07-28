"use client";

/**
 * Streams a video inline through the token-safe file proxy (which forwards Range
 * requests so seeking works). Downloads are disabled.
 */
export function VideoViewer({
  courseId,
  cmid,
  mime,
  isArabic,
}: {
  courseId: number;
  cmid: number;
  mime?: string;
  isArabic: boolean;
}) {
  const basePath = process.env.NEXT_PUBLIC_BASE_PATH || "";
  const src = `${basePath}/api/activity-file?courseId=${courseId}&cmid=${cmid}`;

  return (
    <div className="space-y-3">
      <video
        controls
        controlsList="nodownload"
        disablePictureInPicture
        preload="metadata"
        className="w-full rounded-xl border border-border bg-black"
        playsInline
      >
        <source src={src} type={mime || "video/mp4"} />
        {isArabic
          ? "متصفحك لا يدعم تشغيل الفيديو."
          : "Your browser does not support the video tag."}
      </video>
    </div>
  );
}
