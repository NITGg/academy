import type { FrontpageBlock } from "@/services/home.service";
import { CurriculumMap } from "@/components/home/CurriculumMap";

/**
 * Renders the Moodle front-page sections (`cocoon_custom_html` blocks) as authored in the admin.
 *
 * - `kind: "html"` — trusted, admin-authored, self-contained HTML (inline styles + server-substituted
 *   live stats) fetched server-side; rendered as-is, no Moodle theme CSS required.
 * - `kind: "specialties"` — the curriculum block, rendered natively by {@link CurriculumMap} from
 *   live data so its course cards + category filter work as a real React component.
 */
export function FrontpageBlocks({ blocks }: { blocks: FrontpageBlock[] }) {
  if (!blocks?.length) return null;

  return (
    <div className="frontpage-blocks">
      {blocks.map((block) => {
        if (block.kind === "specialties") {
          return <CurriculumMap key={block.id} block={block} />;
        }
        return (
          <section
            key={block.id}
            data-block-id={block.id}
            // eslint-disable-next-line react/no-danger -- trusted admin-authored Moodle block HTML fetched server-side
            dangerouslySetInnerHTML={{ __html: block.html ?? "" }}
          />
        );
      })}
    </div>
  );
}
