import type { FrontpageBlock } from "@/services/home.service";

/**
 * Renders the Moodle front-page custom-HTML blocks (hero + marketing sections) exactly as authored
 * in the admin. The HTML is trusted, admin-authored content fetched server-side from Moodle and is
 * self-contained (inline styles + server-substituted live stats), so it renders with the same
 * appearance as the Moodle site without pulling in the Moodle theme CSS.
 */
export function FrontpageBlocks({ blocks }: { blocks: FrontpageBlock[] }) {
  if (!blocks?.length) return null;

  return (
    <div className="frontpage-blocks">
      {blocks.map((block) => (
        <section
          key={block.id}
          data-block-id={block.id}
          // eslint-disable-next-line react/no-danger -- trusted admin-authored Moodle block HTML fetched server-side
          dangerouslySetInnerHTML={{ __html: block.html }}
        />
      ))}
    </div>
  );
}
