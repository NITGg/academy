"use client";

import { useState, type CSSProperties } from "react";
import type { FrontpageBlock, Specialty } from "@/services/home.service";

/**
 * Renders the front-page "الخريطة التدريبية" (curriculum map) section natively.
 *
 * On the Moodle site this section's course cards are built by `local_academy_before_footer()` JS and
 * injected into an empty `<div id="xt-specialties">`. Here we receive the admin-authored wrapper +
 * header (`wrapperStyle`/`wrapperDir`/`headerHtml`) plus live `specialties` data and render the
 * cards + category filter as a real React component — same appearance, with working filtering.
 *
 * Inline-style constants are copied verbatim from the Moodle markup (lib.php) so the look matches
 * exactly; `css()` turns those CSS strings into React style objects.
 */

const CONTAINER_STYLE =
  "display:flex;flex-wrap:wrap;gap:10px;justify-content:center;align-items:center;margin-bottom:40px;padding-bottom:15px;border-bottom:1px solid rgba(201,146,42,0.2)";
const FILTER_BASE =
  "background-color:rgba(10,22,40,0.7);border:1px solid rgba(201,146,42,0.2);border-radius:40px;padding:8px 18px;font-size:13px;font-weight:600;color:#8A9AB5;cursor:pointer;display:inline-block;transition:all .18s;font-family:inherit";
const FILTER_ACTIVE =
  "background:linear-gradient(135deg,#C9922A,#E8B84B);color:#0A1628;border-radius:40px;padding:8px 18px;font-size:13px;font-weight:bold;cursor:pointer;display:inline-block;border:none;font-family:inherit";
const SPEC_H3 =
  "font-size:24px;font-weight:800;color:#E8B84B;margin:0 0 15px;border-right:4px solid #C9922A;padding-right:12px";
const LEVEL_BOX =
  "background-color:rgba(255,255,255,0.02);border-radius:16px;padding:20px;border:1px solid rgba(201,146,42,0.1);margin-top:15px";
const LEVEL_BADGE =
  "display:inline-flex;align-items:center;gap:8px;font-weight:bold;font-size:15px;margin-bottom:20px;background-color:rgba(201,146,42,0.15);padding:5px 15px;border-radius:40px;color:#E8B84B";
const CARDS_ROW = "display:flex;flex-wrap:wrap;gap:20px";
const CARD =
  "flex:1 1 280px;min-width:260px;background:linear-gradient(145deg,#0D2149,#0A1628);border:1px solid rgba(201,146,42,0.2);border-radius:12px;padding:20px;display:flex;flex-direction:column;justify-content:space-between;transition:border-color .18s,transform .15s";
const CARD_TITLE =
  "font-size:16px;font-weight:bold;margin:0 0 10px;color:#FFFFFF;line-height:1.4";
const CARD_DESC = "font-size:13px;color:#8A9AB5;line-height:1.6;margin:0 0 15px";
const CARD_META =
  "display:flex;gap:10px;font-size:11px;color:#00A99D;margin-bottom:15px;flex-wrap:wrap";
const CARD_BTN =
  "display:block;text-align:center;background-color:transparent;border:1px solid #C9922A;color:#E8B84B;padding:8px 15px;border-radius:6px;font-weight:bold;font-size:13px;cursor:pointer;width:100%;text-decoration:none;box-sizing:border-box";
const SPEC_LINK =
  "display:inline-block;margin-top:15px;color:#E8B84B;font-weight:700;font-size:14px;text-decoration:none";

/** Convert an inline CSS string ("a:b;c:d") into a React style object. */
function css(cssText?: string): CSSProperties {
  const style: Record<string, string> = {};
  if (!cssText) return style;
  for (const decl of cssText.split(";")) {
    const idx = decl.indexOf(":");
    if (idx === -1) continue;
    const prop = decl.slice(0, idx).trim();
    const val = decl.slice(idx + 1).trim();
    if (!prop) continue;
    const camel = prop.replace(/-([a-z])/g, (_, c: string) => c.toUpperCase());
    style[camel] = val;
  }
  return style as CSSProperties;
}

function SpecialtySection({ spec }: { spec: Specialty }) {
  return (
    <div style={{ marginBottom: 40 }}>
      <h3 style={css(SPEC_H3)}>{spec.name}</h3>

      {spec.levels.map((lv, i) => (
        <div key={i} style={css(LEVEL_BOX)}>
          {lv.name && (
            <div style={css(LEVEL_BADGE)}>
              {lv.name} ({lv.courses.length} دورات)
            </div>
          )}
          <div style={css(CARDS_ROW)}>
            {lv.courses.map((c) => (
              <div key={c.id} style={css(CARD)}>
                <div>
                  <h4 style={css(CARD_TITLE)}>{c.name}</h4>
                  {c.desc && <p style={css(CARD_DESC)}>{c.desc}</p>}
                </div>
                <div>
                  <div style={css(CARD_META)}>
                    <span>📘 دبلوم مهني</span>
                    <span>🎓 شهادة معتمدة</span>
                    <span>⏱️ وصول فوري</span>
                  </div>
                  <a href={c.url} style={css(CARD_BTN)}>
                    📋 تفاصيل
                  </a>
                </div>
              </div>
            ))}
          </div>
        </div>
      ))}

      <a href={spec.url} style={css(SPEC_LINK)}>
        عرض كل دورات {spec.name} ←
      </a>
    </div>
  );
}

export function CurriculumMap({ block }: { block: FrontpageBlock }) {
  const specialties = block.specialties ?? [];
  const [activeCat, setActiveCat] = useState<number | "all">("all");

  if (!specialties.length) return null;

  const visible =
    activeCat === "all"
      ? specialties
      : specialties.filter((s) => s.id === activeCat);

  return (
    <div
      id="curriculum"
      dir={block.wrapperDir || "rtl"}
      style={css(block.wrapperStyle)}
    >
      {/* Admin-authored header chrome (badge + title + subtitle) */}
      {block.headerHtml && (
        <div dangerouslySetInnerHTML={{ __html: block.headerHtml }} />
      )}

      <div id="xt-specialties">
        {/* Filter bar */}
        <div style={css(CONTAINER_STYLE)}>
          <button
            type="button"
            onClick={() => setActiveCat("all")}
            style={css(activeCat === "all" ? FILTER_ACTIVE : FILTER_BASE)}
          >
            كل التخصصات
          </button>
          {specialties.map((s) => (
            <button
              key={s.id}
              type="button"
              onClick={() => setActiveCat(s.id)}
              style={css(activeCat === s.id ? FILTER_ACTIVE : FILTER_BASE)}
            >
              {s.name}
            </button>
          ))}
        </div>

        {/* Specialty sections */}
        {visible.map((s) => (
          <SpecialtySection key={s.id} spec={s} />
        ))}
      </div>
    </div>
  );
}
