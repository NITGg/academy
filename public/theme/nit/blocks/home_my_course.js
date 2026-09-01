// Behaviour for the "My Course" home-page block (home_my_course_block.html).
//
// Served by js.php beside it, so a fix here ships with a git pull; the block in
// the database only has to be re-pasted when its markup changes.
//
// AC-4.7.7 to AC-4.7.9, and the one rule the block exists for: it is drawn only
// for a learner who is actually enrolled in something. The paste hides itself
// while it is parsed, so the three states are:
//
// - guest, or nobody signed in: the feed answers with an empty list and the
//   block stays hidden — no block and no placeholder in its position;
// - signed in with no active enrolment: hidden as well, unless the block is set
//   to data-empty="show", which draws the wireframe's "browse the catalogue"
//   card in place of the grid;
// - otherwise one card per course, most recently accessed first, each with the
//   completion bar and a Resume that returns to the last position.
//
// Every visible string lives in the markup, because the paste runs through the
// multilang filter and this file does not.

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

  var link = function(selector, path) {
    var a = root.querySelector(selector);
    if (a && path) {
      a.setAttribute('href', base + path);
    }
  };

  link('[data-nit-mc-viewall]', root.getAttribute('data-viewall'));
  link('[data-nit-mc-browse]', root.getAttribute('data-browse'));

  /**
   * Fill one cloned card from one feed row.
   *
   * @param {Element} node the clone
   * @param {Object} row one course
   */
  var fill = function(node, row) {
    var courseurl = row.url || '#';

    var title = node.querySelector('[data-nit-mc-title]');
    if (title) {
      // textContent, not innerHTML: the course name is user-entered.
      title.textContent = row.fullname || '';
      title.setAttribute('href', courseurl);
    }

    var image = node.querySelector('[data-nit-mc-image]');
    if (image) {
      image.setAttribute('href', courseurl);
      if (row.image) {
        image.style.backgroundImage = 'url("' + String(row.image).replace(/"/g,
          '%22') + '")';
      }
    }

    // The teacher line is decoration; an empty one would leave a stray gap.
    var teacher = node.querySelector('[data-nit-mc-teacher]');
    if (teacher && row.teacher) {
      teacher.textContent = row.teacher;
      teacher.style.display = '';
    }

    // A null progress means the course tracks no completion at all. The bar
    // still draws — empty — but the label says so rather than claiming 0%.
    var bar = node.querySelector('[data-nit-mc-bar]');
    var label = node.querySelector('[data-nit-mc-label]');
    var untracked = node.querySelector('[data-nit-mc-untracked]');
    if (row.progress === null || row.progress === undefined) {
      if (label) {
        label.style.display = 'none';
      }
      if (untracked) {
        untracked.style.display = '';
      }
    } else {
      var pct = Math.max(0, Math.min(100, parseInt(row.progress, 10) || 0));
      if (bar) {
        bar.style.width = pct + '%';
      }
      var percent = node.querySelector('[data-nit-mc-percent]');
      if (percent) {
        percent.textContent = pct;
      }
    }

    // The button says Resume, so it goes to the resume point rather than to the
    // course front page (AC-4.7.8).
    var resume = node.querySelector('[data-nit-mc-resume]');
    if (resume) {
      resume.setAttribute('href', row.resumeurl || courseurl);
    }

    node.style.display = 'flex';
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
      // "View all" over an empty list is a link to a second empty page.
      var viewall = root.querySelector('[data-nit-mc-viewall]');
      if (viewall) {
        viewall.style.display = 'none';
      }
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

      show();
    })
    .catch(function() {
      // A front page that renders is worth more than a section that explains
      // why it cannot: stay hidden, exactly as for a guest.
    });
})();
