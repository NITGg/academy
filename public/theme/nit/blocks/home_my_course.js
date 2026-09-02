// Behaviour for the "My Course" home-page block (home_my_course_block.html).
//
// Served by js.php beside it, so a fix here ships with a git pull; the block in
// the database only has to be re-pasted when its markup changes.
//
// The one rule the block exists for: it is drawn only for a learner who is
// actually enrolled in something. The paste hides itself while it is parsed, so
// the three states are:
//
// - guest, or nobody signed in: the feed answers with an empty list and the
//   block stays hidden — no block and no placeholder in its position;
// - signed in with no active enrolment: hidden as well, unless the block is set
//   to data-empty="show", which draws a "browse the catalogue" card in place of
//   the grid;
// - otherwise one card per course, most recently accessed first.
//
// Every part of a card is optional. The design shows a lesson line, counts of
// lessons, languages and hours, a price and a progress bar, and the feed returns
// each only when the course actually carries it — so each is REMOVED from the
// document rather than filled with a zero the course never claimed. Removed, not
// hidden: see drop() for why an inline display:none cannot be relied on here.
//
// Every visible string lives in the markup, because the paste runs through the
// multilang filter and this file does not. The one exception is the lesson line,
// which the feed builds server-side in the language the block asked for.

(function() {
  var root = (document.currentScript && document.currentScript.closest(
      '[data-nit-mycourse]')) ||
    document.querySelector('[data-nit-mycourse]');
  if (!root || root.getAttribute('data-nit-wired')) {
    return;
  }
  root.setAttribute('data-nit-wired', '1');

  var grid = root.querySelector('[data-nit-mc-grid]');
  var tpl = root.querySelector('[data-nit-mc-card]');
  var empty = root.querySelector('[data-nit-mc-empty]');
  if (!grid || !tpl) {
    return;
  }

  // The block frame carries the title, so it is the frame that has to appear or
  // stay away, not just our wrapper.
  var shell = root.closest('.block') || root;
  var base = (window.M && window.M.cfg && window.M.cfg.wwwroot) ?
    window.M.cfg.wwwroot : (root.getAttribute('data-base') || '');
  var lang = (document.documentElement.getAttribute('lang') || 'en').split('-')[0];
  var limit = parseInt(root.getAttribute('data-limit') || '6', 10) || 6;

  var show = function() {
    shell.style.display = '';
    root.style.display = '';
  };

  // A link with nowhere to go is dropped rather than left pointing at "#". The
  // full-list page carries the same markup as the home block, and there "More"
  // would only lead back to itself, so it sets data-viewall="".
  var link = function(selector, path) {
    var a = root.querySelector(selector);
    if (!a) {
      return;
    }
    if (path) {
      a.setAttribute('href', base + path);
    } else {
      drop(a);
    }
  };

  /**
   * Drop a part of the card that has no data behind it.
   *
   * Removed from the document, not hidden. The markup is pasted through Moodle's
   * rich-text editor, which is free to rewrite it on save - and an inline
   * `display:none` is exactly the kind of thing it drops. A stat whose number was
   * never filled in then reappears as a bare icon, which is what happened on the
   * live site. A node that is not there cannot come back.
   *
   * @param {Element} node
   * @return {void}
   */
  var drop = function(node) {
    if (node && node.parentNode) {
      node.parentNode.removeChild(node);
    }
  };

  link('[data-nit-mc-viewall]', root.getAttribute('data-viewall'));
  link('[data-nit-mc-browse]', root.getAttribute('data-browse'));

  /**
   * Fill a stat (icon + number + label) and reveal it, or drop it entirely.
   *
   * @param {Element} node the card
   * @param {string} wrapper selector for the whole stat
   * @param {string} value selector for the number inside it
   * @param {*} amount from the feed
   */
  var stat = function(node, wrapper, value, amount) {
    var box = node.querySelector(wrapper);
    if (!box) {
      return;
    }
    var number = box.querySelector(value);
    var count = parseInt(amount, 10);
    if (!number || !(count > 0)) {
      drop(box);
      return;
    }
    number.textContent = count;
    // The markup hides it with display:none, so restoring '' would give a span
    // its inline default and lose the row's alignment.
    box.style.display = 'inline-flex';
  };

  /**
   * Fill one cloned card from one feed row.
   *
   * @param {Element} node the clone
   * @param {Object} row one course
   */
  var fill = function(node, row) {
    // The whole card is the Resume action: it opens the last position, or the
    // course page when the log cannot say where that was.
    var card = node.querySelector('[data-nit-mc-resume]');
    if (card) {
      card.setAttribute('href', row.resumeurl || row.url || '#');
    }

    var title = node.querySelector('[data-nit-mc-title]');
    if (title) {
      // textContent, not innerHTML: the course name is user-entered.
      title.textContent = row.fullname || '';
    }

    var subtitle = node.querySelector('[data-nit-mc-subtitle]');
    if (subtitle && row.subtitle) {
      subtitle.textContent = row.subtitle;
      subtitle.style.display = '';
    } else {
      drop(subtitle);
    }

    // No course image means no thumbnail box at all, rather than a grey panel
    // taking up 250px on the strength of a picture nobody uploaded. The text
    // then runs the width of the card.
    var image = node.querySelector('[data-nit-mc-image]');
    if (image && row.image) {
      image.style.backgroundImage = 'url("' + String(row.image).replace(/"/g,
        '%22') + '")';
    } else {
      drop(image);
    }

    stat(node, '[data-nit-mc-stat-lessons]', '[data-nit-mc-lessons]', row.lessons);
    stat(node, '[data-nit-mc-stat-languages]', '[data-nit-mc-languages]', row.languages);
    stat(node, '[data-nit-mc-stat-hours]', '[data-nit-mc-hours]', row.hours);

    var price = node.querySelector('[data-nit-mc-price]');
    if (price && row.price) {
      price.textContent = row.price;
      price.style.display = '';
    } else {
      drop(price);
    }

    // A null progress means the course tracks no completion at all. The bar
    // still draws — empty — but the label says so rather than claiming 0%.
    var label = node.querySelector('[data-nit-mc-label]');
    var untracked = node.querySelector('[data-nit-mc-untracked]');
    if (row.progress === null || row.progress === undefined) {
      drop(label);
      if (untracked) {
        untracked.style.display = '';
      }
    } else {
      drop(untracked);
      var pct = Math.max(0, Math.min(100, parseInt(row.progress, 10) || 0));
      var bar = node.querySelector('[data-nit-mc-bar]');
      var percent = node.querySelector('[data-nit-mc-percent]');
      if (bar) {
        bar.style.width = pct + '%';
      }
      if (percent) {
        percent.textContent = pct;
      }
    }

    node.style.display = '';
    node.removeAttribute('data-nit-mc-card');
  };

  /**
   * Nothing to list. Either stay away entirely, or draw the invitation.
   *
   * Only a signed-in user is ever offered the empty card: for a guest the
   * catalogue call to action belongs to the hero, not here.
   *
   * @return {void}
   */
  var nothing = function() {
    var loggedin = !document.body.classList.contains('notloggedin');
    if (empty && loggedin && root.getAttribute('data-empty') === 'show') {
      grid.style.display = 'none';
      empty.style.display = '';
      // "More" over an empty list is a link to a second empty page.
      drop(root.querySelector('[data-nit-mc-viewall]'));
      show();
    }
  };

  var url = base + (root.getAttribute('data-endpoint') ||
      '/local/nit_category/home.php') +
    '?function=get_my_courses&limit=' + encodeURIComponent(limit) +
    '&alang=' + encodeURIComponent(lang);

  fetch(url, {
      headers: {
        Accept: 'application/json'
      },
      credentials: 'same-origin'
    })
    .then(function(r) {
      return r.json();
    })
    .then(function(res) {
      if (!res || res.status !== 'success' || !res.data || !res.data.length) {
        nothing();
        return;
      }

      res.data.forEach(function(row) {
        var node = tpl.cloneNode(true);
        fill(node, row);
        grid.appendChild(node);
      });

      // Same reason as drop(): the template is only hidden by an inline style
      // the editor may not have kept, and a seventh blank card at the end of the
      // grid is what that looks like. Its clones are already made.
      drop(tpl);

      show();
    })
    .catch(function() {
      // A front page that renders is worth more than a section that explains
      // why it cannot: stay hidden, exactly as for a guest.
    });
})();
