// Behaviour for home_hero_split_block_2.html — the constellation hero.
//
// WHY THIS IS A FILE AND NOT INLINE IN THE BLOCK
// The block is pasted into a Moodle HTML block, so the copy that runs lives in
// the database and is written through the editor and HTML Purifier on save.
// Carrying ~25KB of JavaScript through that is not survivable: the saved copy
// came back truncated in the middle of a `for` loop, which closed the block
// early, spilled the rest of the script into the page as markup, and took
// Moodle's own footer scripts down with it — including the require.config call,
// so RequireJS fell back to a "./" baseUrl, every AMD module 404'd, and nothing
// on the site could be clicked. Served from here the code is a real file: no
// purifier, no size ceiling, and a fix ships with a git pull instead of a
// re-paste. This is the same arrangement home_my_course.js and
// home_subscriptions.js already use; js.php beside this file serves it.
//
// Everything below was inline in the block before. Nothing else changed.
(function() {
  'use strict';

  // ------------------------------------------------------------------
  // Styles. Almost none of this could be an inline style attribute: SVG child
  // fills, the two rings drawn as ::before/::after, the hover states and the
  // phone media query all need real selectors.
  // ------------------------------------------------------------------
  function orbitStyle() {
    if (document.getElementById('nit-hero-orbit-style')) {
      return;
    }
    var css =
      // ---- centre ---------------------------------------------------------
      // font-size:0 is a guard, not styling: the editor drops an &nbsp; into
      // any container it thinks is empty, and that stray text node would sit in
      // this flex row and push the logo off centre.
      '[data-nit-orbit-core]{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);' +
      'width:26%;height:26%;border-radius:50%;display:flex;align-items:center;justify-content:center;' +
      'font-size:0;' +
      'background:radial-gradient(circle at 50% 40%,var(--nit-brand-surface) 0%,var(--nit-brand-background) 70%);' +
      'box-shadow:0 0 0 1px color-mix(in srgb,var(--nit-brand-textprimary) 6%,transparent),' +
      '0 0 60px color-mix(in srgb,var(--nit-brand-surface) 90%,transparent)}' +

      // The bright ring hugging the logo. A conic gradient masked down to a 2px
      // annulus: cheaper and crisper than an SVG stroke, and it takes its stops
      // straight from the palette.
      '[data-nit-orbit-core]::before{content:"";position:absolute;inset:-9%;border-radius:50%;' +
      'background:conic-gradient(from 210deg,var(--nit-brand-primary),var(--nit-brand-accenttext),' +
      'var(--nit-brand-info),var(--nit-brand-success),var(--nit-brand-warning),var(--nit-brand-primary));' +
      '-webkit-mask:radial-gradient(closest-side,transparent calc(100% - 2px),#000 calc(100% - 2px));' +
      'mask:radial-gradient(closest-side,transparent calc(100% - 2px),#000 calc(100% - 2px))}' +

      '[data-nit-orbit-core]::after{content:"";position:absolute;inset:-26%;border-radius:50%;' +
      'border:1px solid var(--nit-brand-bordersecondary)}' +

      // Sized to fit comfortably within the core circle. Fallback footer-logo.png
      // gets an invert filter in setupLogo(); real brand logos display naturally.
      '[data-nit-orbit-logo]{width:82%;max-width:82%;height:auto;max-height:82%;object-fit:contain;' +
      'display:block;position:relative;z-index:2}' +

      // ---- the ring the shapes sit on -------------------------------------
      '[data-nit-orbit-track]{stroke:var(--nit-brand-bordersecondary)}' +

      // ---- one shape ------------------------------------------------------
      // Square by explicit width AND height, not by aspect-ratio: the node is
      // absolutely positioned, so if its content ever outgrew the box the ratio
      // would give way and preserveAspectRatio="none" would stretch the contour
      // into an ellipse. For the same reason there is no percentage padding
      // here — on an absolutely positioned element percentage padding resolves
      // against the ORBIT's width, not the node's, which crushes the content to
      // nothing. The content is held off the edges with max-width on the
      // children instead, which does resolve against the node because those
      // children are in normal flow.
      '[data-nit-orbit-node]{position:absolute;width:27.5%;height:27.5%;transform:translate(-50%,-50%);' +
      'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;' +
      'text-align:center;text-decoration:none!important;color:var(--nit-brand-textprimary)}' +
      '[data-nit-orbit-node]>span{position:relative;z-index:1;max-width:74%}' +

      '[data-nit-orbit-shape]{position:absolute;inset:0;width:100%;height:100%;overflow:visible;z-index:0;' +
      'filter:drop-shadow(0 0 13px color-mix(in srgb,var(--nit-ac) 42%,transparent))}' +
      '[data-nit-shape-base]{fill:color-mix(in srgb,var(--nit-brand-background) 94%,transparent)}' +
      // The accent wash is strongest at the top and gone by two thirds down — a
      // linear-gradient mask rather than an SVG gradient, because an SVG
      // gradient needs an id and every clone would then share the first one.
      '[data-nit-shape-tint]{fill:var(--nit-ac);opacity:.22;' +
      '-webkit-mask-image:linear-gradient(to bottom,#000,transparent 70%);' +
      'mask-image:linear-gradient(to bottom,#000,transparent 70%)}' +
      // non-scaling-stroke keeps the outline 1px however big the shape gets: the
      // path lives in a 100-unit box scaled to ~185px, so a plain stroke-width
      // would be drawn nearly twice as thick as asked.
      '[data-nit-shape-line]{fill:none;stroke:var(--nit-ac);stroke-width:1;opacity:.9;' +
      'vector-effect:non-scaling-stroke}' +

      '[data-nit-orbit-ico]{width:52px;height:52px;border-radius:50%;flex:0 0 auto;overflow:hidden;' +
      'display:flex;align-items:center;justify-content:center;font-size:26px;line-height:1;' +
      'background:linear-gradient(160deg,var(--nit-ac),color-mix(in srgb,var(--nit-ac) 62%,#000));' +
      'box-shadow:0 6px 18px color-mix(in srgb,var(--nit-ac) 45%,transparent)}' +
      '[data-nit-orbit-ico] svg{width:28px;height:28px;fill:var(--nit-brand-textprimary)}' +
      // The uploaded icons are a circular badge drawn on a white square, and
      // that square's corners would otherwise survive as a white ring inside the
      // disc. Cropping to cover and zooming past the margin pushes the white out
      // of frame and leaves the badge filling the disc.
      '[data-nit-orbit-ico] img{width:100%;height:100%;object-fit:cover;transform:scale(1.36)}' +

      '[data-nit-orbit-eyebrow]{font-size:11.5px;font-weight:500;line-height:1.35;' +
      'color:var(--nit-brand-textsecondary)}' +
      '[data-nit-orbit-eyebrow]:empty{display:none}' +
      '[data-nit-orbit-name]{font-size:16.5px;font-weight:700;line-height:1.3;' +
      'color:var(--nit-brand-textprimary)}' +
      '[data-nit-orbit-go]{width:28px;height:28px;border-radius:50%;flex:0 0 auto;margin-top:2px;' +
      'display:flex;align-items:center;justify-content:center;' +
      'border:1px solid color-mix(in srgb,var(--nit-brand-textprimary) 55%,transparent)}' +
      // An SVG with no fill falls back to black rather than to the text colour
      // around it, and a fill presentation attribute does not survive being
      // saved through Moodle's HTML block. Set it from CSS.
      '[data-nit-orbit-arrow]{fill:currentColor}' +
      'html[dir="rtl"] [data-nit-orbit-arrow]{transform:scaleX(-1)}' +

      // ---- hover ----------------------------------------------------------
      '[data-nit-orbit-node]{transition:transform .25s ease}' +
      '[data-nit-orbit-shape],[data-nit-orbit-ico]{transition:filter .25s ease,transform .25s ease}' +
      '[data-nit-orbit-node]:hover{transform:translate(-50%,-50%) scale(1.06)}' +
      '[data-nit-orbit-node]:hover [data-nit-orbit-shape]{' +
      'filter:drop-shadow(0 0 22px color-mix(in srgb,var(--nit-ac) 70%,transparent))}' +
      '[data-nit-orbit-node]:hover [data-nit-orbit-go]{background:var(--nit-ac);' +
      'border-color:var(--nit-ac)}' +

      // ---- phones ---------------------------------------------------------
      // A ring of 185px shapes needs ~670px to exist. Below that it folds to the
      // logo above a two-column grid of the same shapes. @media, not
      // @container: Moodle's RTL build silently drops @container blocks.
      '@media (max-width:767px){' +
      // minmax(0,1fr), not 1fr: a plain 1fr track cannot go below the shape's
      // min-content width, which pushed the orbit wider than the phone.
      '[data-nit-orbit]{aspect-ratio:auto!important;max-width:440px;display:grid;' +
      'grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}' +
      '[data-nit-orbit-ring]{display:none}' +
      // relative, NOT static. The shape's <svg> is position:absolute;inset:0, so
      // it is laid out against its nearest POSITIONED ancestor — take the
      // position off the node and every contour reparents to the orbit box and
      // stretches across the whole grid. left/top have to be cleared with
      // !important because this script writes them as inline styles, which
      // outrank a plain stylesheet rule.
      '[data-nit-orbit-core],[data-nit-orbit-node]{position:relative!important;' +
      'left:auto!important;top:auto!important;transform:none!important}' +
      '[data-nit-orbit-core]{grid-column:1/-1;display:flex;' +
      'width:100%!important;height:auto!important;padding:8px 0 22px;border-radius:0!important;' +
      'background:none!important;box-shadow:none!important}' +
      '[data-nit-orbit-core]::before,[data-nit-orbit-core]::after{display:none}' +
      '[data-nit-orbit-logo]{width:min(70%,240px);max-height:100px;object-fit:contain}' +
      '[data-nit-orbit-node]{width:100%!important;height:auto!important;aspect-ratio:1!important}' +
      '[data-nit-orbit-node]:hover{transform:none!important}' +
      // A grid cell on a phone is ~165px against ~185px in the ring, and the
      // longest names reach the contour at the desktop size.
      '[data-nit-orbit-name]{font-size:15px}[data-nit-orbit-eyebrow]{font-size:11px}' +
      '}' +

      '@media (prefers-reduced-motion:reduce){[data-nit-orbit-node],[data-nit-orbit-shape],' +
      '[data-nit-orbit-ico]{transition:none}[data-nit-orbit-node]:hover{transform:translate(-50%,-50%)}}';
    var st = document.createElement('style');
    st.id = 'nit-hero-orbit-style';
    st.textContent = css;
    (document.head || document.documentElement).appendChild(st);
  }

  // The shared home-page hover polish, injected by whichever section block gets
  // there first. Kept byte-identical to the copy in the other blocks.
  function hoverStyle() {
    if (document.getElementById('nit-hoverfx-style')) {
      return;
    }
    var css =
      '.nit-btn-solid,.nit-btn-ghost,[data-nit-copy],[data-nit-scroll],[data-nit-coupons-scroll],[data-nit-categories]>a,[data-nit-my-courses]>div,[data-nit-about-card]{transition:background-color .2s ease,background .2s ease,color .2s ease,border-color .2s ease,transform .2s ease,box-shadow .2s ease}' +
      '.nit-btn-solid:not(:disabled):hover{background:var(--nit-brand-primary-hover)!important;color:var(--nit-brand-textprimary)!important;transform:translateY(-2px);box-shadow:0 10px 24px rgba(0,0,0,.35)}' +
      '.nit-btn-ghost:not(:disabled):hover{background:var(--nit-brand-hoverbackground)!important;color:var(--nit-brand-hovertext)!important;border-color:var(--nit-brand-bordersecondary)!important;transform:translateY(-2px);box-shadow:0 10px 24px rgba(0,0,0,.35)}' +
      '[data-nit-copy]:hover{background:var(--nit-brand-hoverbackground)!important;color:var(--nit-brand-hovertext)!important;border-color:var(--nit-brand-hoverbackground)!important;transform:translateY(-2px);box-shadow:0 10px 24px rgba(0,0,0,.35)}' +
      '[data-nit-scroll]:hover,[data-nit-coupons-scroll]:hover{background:var(--nit-brand-hoverbackground)!important;color:var(--nit-brand-hovertext)!important;border-color:var(--nit-brand-hoverbackground)!important;transform:translateY(-50%) scale(1.08)!important}' +
      '[data-nit-categories]>a:hover,[data-nit-my-courses]>div:not([data-nit-my-course-card]):not([data-nit-my-courses-empty]):hover{transform:translateY(-6px);border-color:var(--nit-brand-accent)!important;box-shadow:0 16px 40px rgba(0,0,0,.45)}' +
      '[data-nit-categories]>a:hover [data-nit-cta]{background:var(--nit-brand-primary-hover)!important;color:var(--nit-brand-textprimary)!important}' +
      '[data-nit-about-card]:hover{transform:translateY(-4px);border-color:var(--nit-brand-accent)!important}';
    var st = document.createElement('style');
    st.id = 'nit-hoverfx-style';
    st.textContent = css;
    (document.head || document.documentElement).appendChild(st);
  }

  function playStyle() {
    if (document.getElementById('nit-hero-split-style')) {
      return;
    }
    var css =
      '[data-nit-hero-playdot]{transition:transform .2s ease}' +
      '[data-nit-hero-play]:hover [data-nit-hero-playdot]{transform:scale(1.08)}' +
      '@media (prefers-reduced-motion:reduce){[data-nit-hero-playdot]{transition:none}}';
    var st = document.createElement('style');
    st.id = 'nit-hero-split-style';
    st.textContent = css;
    (document.head || document.documentElement).appendChild(st);
  }

  // ------------------------------------------------------------------
  // The constellation.
  //
  // Rows come from window.NIT_CATEGORIES, which theme_nit's front-page layout
  // already prints (layout/frontpage.php builds it, frontpage.mustache emits
  // it). It lists every top-level category INCLUDING the ones with no courses
  // yet, which is what a showcase needs and what the JSON feed deliberately
  // withholds — /local/nit_category/home.php drops a zero-course category
  // because a dead link is the wrong thing to put in a grid of courses. The
  // feed stays wired up below for any page that does not print the global.
  // ------------------------------------------------------------------
  function drawOrbit() {
    var orbit = document.querySelector('[data-nit-orbit]');
    if (!orbit || orbit.dataset.nitLoaded) {
      return;
    }
    orbit.dataset.nitLoaded = '1';

    var tpl = orbit.querySelector('[data-nit-orbit-card]');
    if (!tpl) {
      return;
    }
    // Taken out of the document, not just hidden: it is still the node every
    // shape is cloned from, it just no longer sits where another script could
    // find it and try to fill it too.
    tpl.parentNode.removeChild(tpl);

    // Six irregular rounded polygons in a 100x100 box, pre-generated with
    // jittered vertices and rounded corners. Assigned by position, so no two
    // neighbours share a contour. A seventh category reuses the first shape.
    var SHAPES = [
      'M89.8 59.7L83.2 74.4Q77.4 87.5 63.5 91.4L61.4 92Q47.6 95.9 36.5 86.7L25.7 77.9Q15 69.2 11 56L9.2 50.3Q5 36.9 16.5 28.8L36.5 14.7Q48.1 6.6 61.6 10.8L65.2 11.9Q76.6 15.4 83 25.4L87.6 32.3Q96.1 45.4 89.8 59.7Z',
      'M88.2 57.7L85.2 66.2Q80 81.4 65.2 87.7L56.6 91.3Q46.8 95.5 37 91.2L29.9 88.1Q21 84.2 16.1 75.7L10.9 66.6Q3.9 54.5 9.1 41.6L11.9 34.7Q16.9 22.3 28.1 15.1L31.1 13.2Q43.7 5.1 57.6 10.8L62.5 12.8Q76.7 18.6 84.1 32L86.3 35.9Q92.1 46.4 88.2 57.7Z',
      'M89.3 56.8L78.5 73.2Q70.4 85.4 56 87.3L50.2 88Q37.5 89.7 27.3 82L27 81.8Q17 74.3 14.1 62L11.1 49.4Q7.1 32 20.8 20.5L27.6 14.8Q38.2 6 51.6 9L65.4 12.1Q81.1 15.7 88.6 29.9L90.5 33.6Q96.8 45.6 89.3 56.8Z',
      'M89 60.4L85.1 72.6Q81.9 82.6 72.7 87.7L68.4 90.1Q57.2 96.4 45.1 92.3L29.8 87.1Q19.7 83.7 14.2 74.4L11 68.9Q4.3 57.5 8.6 45L11.9 35.7Q16 23.8 27.4 18.7L35.8 15Q46.9 10 58.9 12.5L65.5 14Q78.2 16.7 84 28.2L86.8 33.5Q93.4 46.5 89 60.4Z',
      'M90.1 58.1L83.5 70.5Q76.7 83.1 62.9 87L46.7 91.6Q36.1 94.5 28.3 86.8L22.8 81.4Q13.5 72.3 11.1 59.5L9.4 51Q6.5 35.8 18.4 25.8L26.3 19.2Q38.5 9 53.8 13.6L68.4 17.9Q80.8 21.6 87.9 32.4L89 34.1Q96.7 45.8 90.1 58.1Z',
      'M91.8 55.4L85.2 67.6Q78.6 79.6 66.2 85.6L61.2 88Q51.9 92.5 42.3 88.7L28.6 83.3Q16.7 78.7 13.5 66.3L13.1 64.8Q9.8 52.1 13.2 39.4L14.5 34.4Q18.1 21 30.9 15.8L34.6 14.3Q48.5 8.6 62.1 15.1L74.9 21.2Q83.2 25.2 88.1 32.8L91.2 37.8Q96.8 46.5 91.8 55.4Z'
    ];

    // Palette roles, in the order the shapes take them. `secondary` is skipped
    // because it is a near-background navy — a shape wearing it would read as a
    // hole — and `accent` because Brand Colors currently has it set to the same
    // hex as primary, so using both would draw two identical shapes. `error`
    // earns its place because this palette is deliberately red-free and its
    // error role is a warm orange, which reads as decoration here rather than as
    // an alarm. Give accent its own hue and swapping it in is a one-word change.
    var ROLES = ['primary', 'info', 'success', 'warning', 'accenttext', 'error'];

    // Fallback glyphs, used only when a category has neither an uploaded icon
    // nor an emoji, or when data-icons="glyph". Ordered to match the six subject
    // areas the site ships with; a seventh reuses the first.
    var GLYPHS = [
      'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z',
      'M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z',
      'M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z',
      'M1 21h12v2H1zM5.245 8.07l2.83-2.827 14.14 14.142-2.828 2.828zM12.317 1l5.657 5.656-2.83 2.83-5.654-5.66zM3.825 9.485l5.657 5.657-2.828 2.828-5.657-5.657z',
      'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 11h-4v4h-4v-4H6v-4h4V6h4v4h4v4z',
      'M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z'
    ];

    // Every one of these categories is named "<prefix> <subject>" in both
    // languages. Splitting the prefix off puts it on its own muted line and
    // leaves the shape showing the subject in large type, which is what makes it
    // readable at 185px. The trailing [\s.…]* is not decoration: one of the
    // Arabic names is written with an ellipsis after the prefix, which would
    // otherwise open the shape's headline. A name that matches nothing keeps its
    // full text and simply has no eyebrow — :empty hides the element.
    var PREFIXES = [
      /^\s*خبراء\s+إياك\s+ف[يى][\s.…]*/,
      /^\s*EAAC\s+Experts\s+in[\s.…]*/i
    ];

    function splitName(name) {
      var text = String(name == null ? '' : name);
      for (var i = 0; i < PREFIXES.length; i++) {
        var m = text.match(PREFIXES[i]);
        // A category named nothing but the prefix would end up with an empty
        // headline, so in that one case the full name stays put.
        if (m && text.slice(m[0].length).trim() !== '') {
          return {eyebrow: m[0].replace(/[\s.…]+$/, ''), name: text.slice(m[0].length)};
        }
      }
      return {eyebrow: '', name: text};
    }

    function isurl(v) {
      return typeof v === 'string' && /^https?:\/\//i.test(v);
    }

    // Evenly spaced around a circle, first item straight up. R is a share of the
    // orbit box; 33% keeps the shapes clear of both the centre rings and the box
    // edge, including the glow each one casts.
    function place(node, i, total) {
      var a = (i / total) * Math.PI * 2;
      node.style.left = (50 + Math.sin(a) * 33) + '%';
      node.style.top = (50 - Math.cos(a) * 33) + '%';
    }

    // One bead per gap, sitting on the ring at the midpoint between two shapes.
    // Two circles each: a soft halo and a solid dot. Cloned from the ring rather
    // than built with createElementNS so the SVG namespace URL never has to
    // appear as a string — Moodle's "Convert URLs into links" filter rewrites a
    // bare URL in block content into an <a href>, and inside a script that is a
    // syntax error. The block no longer carries this code, but the habit stays.
    function beads(total) {
      var box = orbit.querySelector('[data-nit-orbit-beads]');
      var track = orbit.querySelector('[data-nit-orbit-track]');
      if (!box || !track) {
        return;
      }
      for (var i = 0; i < total; i++) {
        var a = ((i + 0.5) / total) * Math.PI * 2;
        var cx = 370 + Math.sin(a) * 300;
        var cy = 370 - Math.cos(a) * 300;
        var role = 'var(--nit-brand-' + ROLES[i % ROLES.length] + ')';
        [[13, '.22'], [6, '1']].forEach(function(spec) {
          var c = track.cloneNode(false);
          c.removeAttribute('data-nit-orbit-track');
          c.removeAttribute('stroke-width');
          c.setAttribute('cx', cx);
          c.setAttribute('cy', cy);
          c.setAttribute('r', spec[0]);
          c.setAttribute('opacity', spec[1]);
          // Inline styles, because the clone carries the ring's own fill="none"
          // presentation attribute and a style wins over one.
          c.style.fill = role;
          c.style.stroke = 'none';
          box.appendChild(c);
        });
      }
    }

    var useglyph = (orbit.dataset.icons || 'category') === 'glyph';

    // Cloned from the arrow already in the template, for the same reason the
    // beads are: no namespace string, and no innerHTML carrying "<svg" through
    // anything. The arrow's viewBox is 0 0 24 24, which is what the glyph paths
    // are drawn in.
    function glyph(link, ico, i) {
      var arrow = link.querySelector('[data-nit-orbit-arrow]');
      if (!arrow) {
        return;
      }
      var g = arrow.cloneNode(true);
      g.removeAttribute('data-nit-orbit-arrow');
      g.removeAttribute('width');
      g.removeAttribute('height');
      var p = g.querySelector('path');
      if (p) {
        p.setAttribute('d', GLYPHS[i % GLYPHS.length]);
      }
      ico.textContent = '';
      ico.appendChild(g);
    }

    function paintIcon(link, ico, row, i) {
      // window.NIT_CATEGORIES splits the two — `iconurl` is the uploaded file
      // and `icon` the emoji — while the JSON feed puts whichever exists in
      // `icon` alone. Normalise both shapes before choosing.
      var url = row.iconurl || (isurl(row.icon) ? row.icon : '');
      var emoji = isurl(row.icon) ? '' : row.icon;

      if (!useglyph && url) {
        var img = document.createElement('img');
        // Deliberately NOT loading="lazy". This is hero artwork, above the fold
        // on the first paint: deferring it leaves the disc showing as a flat
        // coloured circle wrapped in its own glow, which reads as a rendering
        // fault rather than as a loading state.
        img.alt = '';
        img.decoding = 'async';
        // A renamed or deleted upload otherwise leaves that same empty disc
        // sitting there for good.
        img.addEventListener('error', function() {
          glyph(link, ico, i);
        });
        img.src = url;
        ico.appendChild(img);
        return;
      }
      if (!useglyph && emoji) {
        ico.textContent = emoji;
        return;
      }
      glyph(link, ico, i);
    }

    // Builds the two wrappers the card template cannot carry, because the editor
    // deletes any element that holds nothing of its own: the icon disc, and the
    // round button the arrow sits in.
    function shell(link) {
      var ico = document.createElement('span');
      ico.setAttribute('data-nit-orbit-ico', '');
      // Before the eyebrow, so the disc stays on top however the template was
      // reordered on save. A null reference here appends, which is the same
      // place when the eyebrow is the first child.
      link.insertBefore(ico, link.querySelector('[data-nit-orbit-eyebrow]'));

      var arrow = link.querySelector('[data-nit-orbit-arrow]');
      if (arrow) {
        var go = document.createElement('span');
        go.setAttribute('data-nit-orbit-go', '');
        go.appendChild(arrow);
        link.appendChild(go);
      }
      return ico;
    }

    function fill(node, row, i, total) {
      var link = node.querySelector('[data-nit-orbit-node]');
      link.setAttribute('href', link.getAttribute('href').replace('{{url}}', row.url || '#'));

      // The accent every colour in this shape is mixed from. Set once, on the
      // link, so the shape, its glow, the icon disc and the hover state all read
      // one value.
      link.style.setProperty('--nit-ac', 'var(--nit-brand-' + ROLES[i % ROLES.length] + ')');
      place(link, i, total);

      var d = SHAPES[i % SHAPES.length];
      link.querySelectorAll('[data-nit-shape-base],[data-nit-shape-tint],[data-nit-shape-line]')
        .forEach(function(p) {
          p.setAttribute('d', d);
        });

      // The icon disc has to exist before the arrow is moved into its button,
      // because glyph() reads the arrow off the link.
      var ico = shell(link);
      paintIcon(link, ico, row, i);

      // Text markers. Walking text nodes rather than rewriting innerHTML keeps a
      // category name containing < or & from being parsed as markup.
      var parts = splitName(row.name);
      var walker = document.createTreeWalker(link, NodeFilter.SHOW_TEXT, null);
      var textnode;
      while ((textnode = walker.nextNode())) {
        if (textnode.nodeValue.indexOf('{{') === -1) {
          continue;
        }
        textnode.nodeValue = textnode.nodeValue
          .replace('{{eyebrow}}', parts.eyebrow)
          .replace('{{name}}', parts.name);
      }

      link.setAttribute('title', row.name || '');
      node.style.display = '';
      node.removeAttribute('data-nit-orbit-card');
    }

    // data-ids pins which categories are drawn and in what order. An id that does
    // not exist is skipped rather than left as a hole, so deleting a category
    // degrades to a five-shape pentagon instead of a broken circle.
    function choose(rows) {
      var wanted = (orbit.dataset.ids || '').split(',')
        .map(function(s) {
          return s.trim();
        })
        .filter(Boolean);
      if (!wanted.length) {
        return rows.slice(0, 6);
      }
      var byid = {};
      rows.forEach(function(r) {
        byid[String(r.id)] = r;
      });
      return wanted.map(function(id) {
        return byid[id];
      }).filter(Boolean).slice(0, 6);
    }

    function draw(rows) {
      var picked = choose(rows || []);
      if (!picked.length) {
        // Nothing to show: the hero keeps its headline and buttons and simply
        // loses the artwork, rather than printing an empty ring.
        orbit.style.display = 'none';
        return;
      }
      picked.forEach(function(row, i) {
        var node = tpl.cloneNode(true);
        fill(node, row, i, picked.length);
        orbit.appendChild(node);
      });
      beads(picked.length);
    }

    // Already on the page, and the only source that includes a category with no
    // courses in it yet.
    if (Array.isArray(window.NIT_CATEGORIES) && window.NIT_CATEGORIES.length) {
      draw(window.NIT_CATEGORIES);
      return;
    }

    // Fallback for a page that does not print the global. The feed hides empty
    // categories, so the ring may come up short here — that is the feed's rule,
    // not a fault in this block.
    var root = (window.M && window.M.cfg && window.M.cfg.wwwroot) ? window.M.cfg.wwwroot : '';
    var lang = (document.documentElement.getAttribute('lang') || 'en').split('-')[0];
    fetch(root + '/local/nit_category/home.php?function=get_categories' +
        '&limit=12&alang=' + encodeURIComponent(lang), {
        headers: {Accept: 'application/json'},
        credentials: 'same-origin'
      })
      .then(function(r) {
        return r.json();
      })
      .then(function(res) {
        if (!res || res.status !== 'success' || !res.data || !res.data.length) {
          orbit.style.display = 'none';
          return;
        }
        draw(res.data);
      })
      .catch(function() {
        orbit.style.display = 'none';
      });
  }

  // ------------------------------------------------------------------
  // "Start Now" scrolls to the Learning Categories section when that block is on
  // the page, and otherwise just follows its href — so the link still works with
  // JS off, or on a page where the section is absent. "See how it works" opens
  // the video modal.
  // ------------------------------------------------------------------
  function wireHero() {
    if (window.nitHeroSplitWired) {
      return;
    }
    window.nitHeroSplitWired = true;
    var modal = null;

    // A missing file, or a codec the browser cannot decode, otherwise leaves the
    // player spinning with nothing to act on. Surface the URL the browser
    // actually requested so it can be opened directly and told apart: a 404
    // means the filename is wrong, a clean download means the encoding is.
    function showVideoError() {
      if (!modal) {
        return;
      }
      var srcEl = modal.querySelector('source');
      var panel = modal.querySelector('[data-nit-hero-video-error]');
      var link = modal.querySelector('[data-nit-hero-video-url]');
      var vid = modal.querySelector('video');
      if (link && srcEl) {
        link.href = srcEl.src;
        link.textContent = srcEl.src;
      }
      if (vid) {
        vid.style.display = 'none';
      }
      if (panel) {
        panel.style.display = 'block';
      }
    }

    function getModal() {
      if (!modal) {
        modal = document.querySelector('[data-nit-hero-modal]');
        // The block wrapper clips with overflow:hidden, and a transformed
        // ancestor would trap a position:fixed child, so re-parent once.
        if (modal && modal.parentNode !== document.body) {
          document.body.appendChild(modal);
        }
        if (modal) {
          var srcEl = modal.querySelector('source');
          if (srcEl) {
            srcEl.addEventListener('error', showVideoError);
          }
        }
      }
      return modal;
    }

    function closeModal() {
      var m = getModal();
      if (!m || m.style.display === 'none') {
        return;
      }
      var v = m.querySelector('video');
      if (v) {
        v.pause();
      }
      m.style.display = 'none';
      document.documentElement.style.overflow = '';
    }

    // The site header is pinned, so scrolling a section to y=0 tucks it
    // underneath. Measure whatever is actually painted at the top of the
    // viewport rather than hard-coding a height: the bar is a different height on
    // mobile, and it is pinned by Moodle's own CSS, not by this theme, so a
    // number copied from $navbar-height would drift.
    function fixedTopOffset() {
      if (!document.elementsFromPoint) {
        return 0;
      }
      var stack = document.elementsFromPoint(Math.round(window.innerWidth / 2), 2) || [];
      var offset = 0;
      for (var i = 0; i < stack.length; i++) {
        var pos = window.getComputedStyle(stack[i]).position;
        if (pos !== 'fixed' && pos !== 'sticky') {
          continue;
        }
        var rect = stack[i].getBoundingClientRect();
        if (rect.top <= 2 && rect.bottom > offset) {
          offset = rect.bottom;
        }
      }
      return Math.ceil(offset);
    }

    document.addEventListener('click', function(ev) {
      var scroller = ev.target.closest('[data-nit-scrollto]');
      if (scroller) {
        var target = document.querySelector(scroller.getAttribute('data-nit-scrollto'));
        // The hook sits on the cards grid, but landing there cuts off the
        // section's own badge and heading. Moodle wraps every block in
        // <section data-block="...">, so climb to that and the whole section
        // comes into view. Falls back to the element itself where there is no
        // block wrapper.
        if (target) {
          target = target.closest('[data-block]') || target;
        }
        if (target) {
          ev.preventDefault();
          // Honour reduced-motion, and never leave the click doing nothing: if
          // smooth is unavailable the jump still happens.
          var reduce = window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;
          var top = target.getBoundingClientRect().top + window.pageYOffset -
            fixedTopOffset() - 12;
          window.scrollTo({
            top: top > 0 ? top : 0,
            behavior: reduce ? 'auto' : 'smooth'
          });
        }
        return;
      }
      if (ev.target.closest('[data-nit-hero-play]')) {
        var m = getModal();
        if (!m) {
          return;
        }
        ev.preventDefault();
        m.style.display = 'block';
        document.documentElement.style.overflow = 'hidden';
        var v = m.querySelector('video');
        if (v) {
          var playing = v.play();
          if (playing && playing.catch) {
            playing.catch(function() {});
          }
          // NETWORK_NO_SOURCE (3) is the deterministic signal that every
          // <source> failed. The source error event alone is unreliable here:
          // with preload="none" it can fire before this handler is wired up.
          setTimeout(function() {
            if (v.networkState === 3 && !v.readyState) {
              showVideoError();
            }
          }, 1500);
        }
        return;
      }
      if (ev.target.closest('[data-nit-hero-modal-close]') ||
          ev.target.hasAttribute('data-nit-hero-modal-backdrop')) {
        closeModal();
      }
    });

    document.addEventListener('keydown', function(ev) {
      if (ev.key === 'Escape') {
        closeModal();
      }
    });
  }

  // ------------------------------------------------------------------
  // AC-4.7.3: "For an authenticated learner the hero call to action changes to
  // 'Continue learning' and links to the most recently accessed course."
  //
  // A guest keeps the button exactly as the block rendered it, so the page is
  // correct before this runs and correct if it never does — the button is a
  // working link to the catalogue either way.
  // ------------------------------------------------------------------
  function continueCta() {
    var cta = document.querySelector('[data-nit-hero-cta]');
    if (!cta || cta.dataset.nitLoaded) {
      return;
    }
    cta.dataset.nitLoaded = '1';

    // No session, no "continue" — and no request either, since the only possible
    // answer is that there is nothing to continue.
    if (!(window.M && window.M.cfg && window.M.cfg.sesskey)) {
      return;
    }

    var root = window.M.cfg.wwwroot || '';
    var lang = (document.documentElement.getAttribute('lang') || 'en').split('-')[0];
    var label = lang === 'ar' ? 'متابعة التعلّم' : 'Continue learning';

    fetch(root + '/local/nit_category/home.php?function=get_continue' +
        '&alang=' + encodeURIComponent(lang), {
        headers: {Accept: 'application/json'},
        credentials: 'same-origin'
      })
      .then(function(r) {
        return r.json();
      })
      .then(function(res) {
        if (!res || res.status !== 'success' || !res.data || !res.data.url) {
          return;
        }
        cta.textContent = label;
        cta.setAttribute('href', res.data.url);
        cta.setAttribute('title', res.data.fullname || '');
        // The scroll-to-categories behaviour belongs to "Start Now". Once the
        // button opens a course, intercepting the click would stop it working.
        cta.removeAttribute('data-nit-scrollto');
      })
      .catch(function() {
        // Leave the catalogue link in place.
      });
  }

  // ------------------------------------------------------------------
  // Centre logo: reads from the site/navbar logo (window.NIT_LOGO or
  // .nit-navbar-logo in the DOM) so the hero constellation matches the navbar.
  // ------------------------------------------------------------------
  function setupLogo() {
    var logoImg = document.querySelector('[data-nit-orbit-logo]');
    if (!logoImg) {
      return;
    }

    var root = (window.M && window.M.cfg && window.M.cfg.wwwroot) ? window.M.cfg.wwwroot : '';
    var sitename = (window.M && window.M.cfg && window.M.cfg.sitename) || '';

    // 1. Check window.NIT_LOGO (emitted by theme_nit frontpage layout)
    var logoUrl = (typeof window.NIT_LOGO === 'string' && window.NIT_LOGO) ? window.NIT_LOGO : '';
    var logoAlt = sitename;

    // 2. Read directly from the navbar brand logo in the DOM
    if (!logoUrl) {
      var navLogo = document.querySelector('.nit-navbar-logo, .navbar-brand img, .navbar-brand-logo');
      if (navLogo && navLogo.getAttribute('src')) {
        logoUrl = navLogo.getAttribute('src');
        if (navLogo.getAttribute('alt')) {
          logoAlt = navLogo.getAttribute('alt');
        }
      }
    }

    // 3. Fallback to default theme logo asset if nothing else is set
    if (!logoUrl) {
      logoUrl = root + '/theme/nit/pix/footer-logo.png';
    }

    // Only invert the default dark navy footer-logo.png fallback; real brand logos stay untinted.
    if (/footer-logo\.png$/i.test(logoUrl)) {
      logoImg.style.filter = 'brightness(0) invert(1)';
    } else {
      logoImg.style.filter = 'none';
    }

    logoImg.src = logoUrl;
    if (logoAlt) {
      logoImg.alt = logoAlt;
    }
  }

  function start() {
    orbitStyle();
    hoverStyle();
    playStyle();
    setupLogo();
    drawOrbit();
    wireHero();
    continueCta();
  }

  // The loader in the block appends this with a plain <script src>, which is
  // async-by-nature relative to the block markup only in that the markup is
  // already parsed by the time it runs — but a deferred or cached execution can
  // still land before the rest of the document. Guard both ways.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
