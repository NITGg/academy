// Generates the EAAC mobile API Postman collection (v2.1).
// Run (from repo root): node docs/apis/postman/gen_postman.js > docs/apis/postman/EAAC-mobile-api.postman_collection.json
const crypto = require('crypto');
const uid = () => crypto.randomUUID();

// ---------- helpers ----------------------------------------------------------
// Optional params are shipped "disabled" so they are visible but not sent.
const p = (key, value, description = '', o = {}) => ({
  key, value: String(value), description: (o.req ? '(required) ' : '(optional) ') + description,
  disabled: !o.req, type: 'text',
});
const R = (k, v, d) => p(k, v, d, { req: true });   // required
const O = (k, v, d) => p(k, v, d, { req: false });  // optional
const LANG = [O('moodlewssettinglang', '{{lang}}', 'Display language for strings (en / ar).'),
              O('moodlewssettingfilter', '1', 'Run text filters (resolves {mlang}).')];

function url(pathStr, query) {
  const enabled = query.filter(q => !q.disabled);
  const raw = '{{baseUrl}}/' + pathStr + (enabled.length ? '?' + enabled.map(q => `${q.key}=${q.value}`).join('&') : '');
  return { raw, host: ['{{baseUrl}}'], path: pathStr.split('/'), query: query.map(({ type, ...q }) => q) };
}

function request(name, method, pathStr, params, description, { body = false } = {}) {
  const req = { method, header: [], description };
  if (body) {
    req.url = url(pathStr, []);
    req.body = { mode: 'urlencoded', urlencoded: params };
  } else {
    req.url = url(pathStr, params);
  }
  return { name, request: req, response: [] };
}

// Standard Moodle web-service function. Reads -> GET + query, writes -> POST + form body.
function ws(name, fn, { type = 'read', token = '{{wstoken}}', params = [], desc = '', lang = true, nologin = false }) {
  const fixed = [R('wstoken', token, nologin ? 'Registration API token (pre-login).' : 'Web-service token of the signed-in user.'),
                 R('wsfunction', fn, ''), R('moodlewsrestformat', 'json', '')];
  const all = [...fixed, ...params, ...(lang ? LANG : [])];
  const head = `**\`${fn}\`** — ${type === 'read' ? 'read' : '**write**'}${nologin ? ' · pre-login' : ''}\n\n`;
  return request(name, type === 'read' ? 'GET' : 'POST', 'webservice/rest/server.php', all, head + desc, { body: type !== 'read' });
}

// local_academy / local_vdocipher JSON protocol: ?function=<name>&token=<token>
function api(name, fn, { plugin = 'academy', method = 'GET', token = '{{wstoken}}', params = [], desc = '' }) {
  const isPost = method === 'POST';
  const fixed = [R('function', fn, ''), R('token', token, token === '{{regtoken}}' ? 'Registration API token (pre-login).' : 'Web-service token of the signed-in user.')];
  const all = [...fixed, ...params, O('lang', '{{lang}}', 'Language of error messages (en / ar).')];
  const head = `**\`/local/${plugin}/api.php?function=${fn}\`** — ${method}${isPost ? ' (GET is refused)' : ''}\n\nEnvelope: \`{"status":"success","data":…}\` or \`{"status":"fail","error":"<show>","errorcode":"<branch on>"}\`. HTTP 401 only when the token is dead (sign the user out).\n\n`;
  const pathStr = `local/${plugin}/api.php`;
  if (isPost) {
    // function= stays in the query so the call is readable in the history; the rest goes in the body.
    const item = request(name, 'POST', pathStr, all.filter(x => x.key !== 'function'), head + desc, { body: true });
    item.request.url = url(pathStr, [R('function', fn, '')]);
    return item;
  }
  return request(name, 'GET', pathStr, all, head + desc);
}

const folder = (name, description, item) => ({ name, description, item });

// ---------- 01 Auth & onboarding ---------------------------------------------
const auth = folder('01 · Auth & Onboarding',
  'Sign-in, registration, forgot-password and Google sign-in. Everything that happens before the app holds a personal `wstoken`.\n\n' +
  'Pre-login calls use the shared **Registration API token** (`{{regtoken}}`), created by an admin for the `moodle_mobile_app` service. ' +
  'Once signed in, store `data.token` from the login response into `{{wstoken}}` (the collection test script does this for you).', [
  folder('Login', 'Two ways to get a token. Prefer the academy endpoint: it reports a **blocked** account (`errorcode: accountlocked`) where `/login/token.php` only ever says `invalidlogin`.', [
    api('Login (academy — reports blocked accounts)', 'login', { method: 'POST', token: '{{regtoken}}', params: [
      R('username', 'student@example.com', 'Username or e-mail address (login-via-email is on).'),
      R('password', 'Secret123!', 'Password as typed.'),
      R('service', '{{service}}', 'Web-service shortname, normally moodle_mobile_app.'),
    ], desc: 'Success → `data.token`, `data.privatetoken` (https only, never for admins), `data.userid`.\n\n`errorcode` values: `invalidlogin`, `accountlocked`, `usernotconfirmed`, `passwordisexpired`, `sitemaintenance`, `servicenotavailable`, `restoredaccountresetpassword`, `postrequired`.\n\nDoc: docs/apis/login-mobile-api.md' }),
    request('Login (stock Moodle /login/token.php)', 'POST', 'login/token.php', [
      R('username', 'student@example.com', ''), R('password', 'Secret123!', ''), R('service', '{{service}}', ''),
    ], 'Stock Moodle token endpoint, kept for older app builds. Returns `{"token","privatetoken"}` or `{"error","errorcode"}`. A blocked account is reported as plain `invalidlogin`.', { body: true }),
    request('Google Sign-In → token exchange', 'POST', 'local/googleauth/token.php', [
      R('idtoken', '<GOOGLE_ID_TOKEN_JWT>', 'ID token from native Google Sign-In. `id_token` is accepted as an alias.'),
      O('service', '{{service}}', 'External service shortname, defaults to moodle_mobile_app.'),
    ], 'Verifies the Google ID token, maps/creates the Moodle account (imports the Google avatar) and returns a web-service token. Errors: `{"error":"<code>"}` with a 4xx status. After this, call **local_profilefields_get_completion_status** — an OAuth account may still owe sign-up fields/consent.\n\nDoc: public/local/googleauth/README.md', { body: true }),
    ws('Site info (who am I / site capabilities)', 'core_webservice_get_site_info', { desc: 'Standard first call after login: user id, full name, picture, site name, language, and the list of functions this token may call (`functions[]`) — useful to confirm the mobile service exposes our `local_*` functions. Sets `{{userid}}`.', lang: false }),
  ]),
  folder('Registration (sign-up)', 'The site\'s own sign-up, replacing `auth_email_get_signup_settings` / `auth_email_signup_user`. All four are pre-login and can also be called with no token at all via `/lib/ajax/service-nologin.php`.\n\nDoc: docs/apis/registration-mobile-api.md', [
    ws('1 · Describe the sign-up form', 'local_profilefields_get_signup_form', { token: '{{regtoken}}', nologin: true, desc: 'The fields to show, in order, with labels, requiredness, options, `minlength`/`maxlength`/`pattern`, plus flow flags: `usernamefromemail`, `countryfromphone`, `ipmatchphone`, `consent{required,label,documents[]}`, `passwordpolicy`, `passwordrules`.' }),
    ws('2 · Policy documents (Terms text)', 'local_profilefields_get_policy_documents', { token: '{{regtoken}}', nologin: true, params: [O('versionid', '0', '0 = every sign-up document; or one versionid from consent.documents.')], desc: 'The text of the policies shown on sign-up so the app can render them itself.' }),
    ws('3 · Create the account', 'local_profilefields_signup_user', { type: 'write', token: '{{regtoken}}', nologin: true, params: [
      R('email', 'new.student@example.com', 'Also becomes the username.'),
      R('password', 'Secret123!', 'Must satisfy passwordpolicy.'),
      R('firstname', 'Ahmed', ''), R('lastname', 'Mohamed', ''),
      R('consent', '1', '1 = the user ticked the agreement box (required when consent.required).'),
      R('customprofilefields[0][type]', 'phone', 'Custom field entries: type/name/value triplets, indexed.'),
      R('customprofilefields[0][name]', 'profile_field_phone', 'The field name exactly as get_signup_form gave it.'),
      R('customprofilefields[0][value]', 'EG:1012345678', 'Phone: "ISO:number" or JSON {"country":"EG","number":"1012345678"}.'),
      O('email2', '', 'Only when get_signup_form lists an email2 field.'),
      O('city', '', 'Omit; site default is used.'), O('country', '', 'Omit; follows the phone field.'),
      O('username', '', 'Ignored while usernamefromemail is true.'),
      O('recaptcharesponse', '', 'Only when a captcha is configured.'),
      O('redirect', '', 'Local URL to land on after confirmation.'),
    ], desc: 'Field-level problems come back in `warnings[]` (not as exceptions). On success the account is created unconfirmed and a confirmation e-mail is sent.' }),
    ws('4 · Resend confirmation e-mail', 'local_profilefields_resend_confirmation', { type: 'write', token: '{{regtoken}}', nologin: true, params: [R('email', 'new.student@example.com', 'Address entered at sign-up.')], desc: 'Rate-limited. Always returns `retryafter` (seconds) — drive the button countdown from it. Never reveals whether an address is registered.' }),
  ]),
  folder('Forgot password (OTP)', 'Three-step e-mail OTP flow on the academy endpoint. All POST, all with the Registration API token.\n\nDoc: docs/apis/forgot-password-mobile-api.md', [
    api('1 · Request OTP', 'request_password_otp', { method: 'POST', token: '{{regtoken}}', params: [R('email', 'student@example.com', 'The account e-mail.')], desc: 'Sends a 6-digit code by e-mail. The response never says whether the address exists.' }),
    api('2 · Verify OTP', 'verify_password_otp', { method: 'POST', token: '{{regtoken}}', params: [R('email', 'student@example.com', 'Same e-mail.'), R('otp', '123456', 'The 6-digit code.')], desc: 'Success → `data.resettoken` + `data.expiresin` (seconds).' }),
    api('3 · Set new password', 'reset_password', { method: 'POST', token: '{{regtoken}}', params: [R('resettoken', '<RESET_TOKEN_FROM_STEP_2>', ''), R('newpassword', 'NewPass456!', 'Must meet the site password policy.')], desc: 'Clears any account lockout as well.' }),
  ]),
  folder('OAuth completion (finish a Google sign-up)', 'An account created by Google sign-in was never shown the sign-up form. Ask what is still outstanding, then save it.\n\nDoc: docs/apis/oauth-completion-mobile-api.md', [
    ws('1 · What is still outstanding?', 'local_profilefields_get_completion_status', { desc: '`complete`, `gateenabled`, `countryfromphone`, `fields[]` (in sign-up order, with `locked`, `options[]`) and `consent{required,label,documents[]}`. If `complete` is true skip the screen.' }),
    ws('2 · Save the answers (+ consent)', 'local_profilefields_update_profile', { type: 'write', params: [
      R('fields[0][name]', 'profile_field_phone', 'Name exactly as get_completion_status gave it.'),
      R('fields[0][value]', 'SA:512345678', 'Phone = "ISO:number" without dial code; datetime = unix ts; checkbox = "0"/"1".'),
      O('fields[1][name]', 'country', ''), O('fields[1][value]', 'SA', ''),
      O('consent', '1', 'Send 1 only when consent.required was true.'),
      O('userid', '0', '0 = the calling user.'),
    ], desc: 'Same call as the profile editor; completion is recorded automatically when nothing is left outstanding.' }),
  ]),
]);

// ---------- 02 Public site content -------------------------------------------
const site = folder('02 · Site Content (public, no login)',
  'Branding and static content the app renders before or without a login. These need **no personal token** — the Registration token is used only so the web-service calls go through the mobile service.', [
  ws('Footer (contacts, links, social, copyright)', 'local_profilefields_get_footer', { token: '{{regtoken}}', nologin: true, params: [O('lang', '{{lang}}', 'en / ar')], desc: 'The site footer as data, already resolved to one language.\n\nDoc: docs/apis/footer-mobile-api.md' }),
  ws('Static pages — list', 'local_profilefields_get_static_pages', { token: '{{regtoken}}', nologin: true, params: [O('lang', '{{lang}}', 'en / ar')], desc: 'Slug, kind, name and web address of every published static page (about, contact, terms, privacy, refund, faq).' }),
  ws('Static page — one page', 'local_profilefields_get_static_page', { token: '{{regtoken}}', nologin: true, params: [R('page', 'about', 'about | contact | terms | privacy | refund | faq'), O('lang', '{{lang}}', 'en / ar')], desc: 'Body as HTML, plus contact rows/social links (Contact) or Q&A (FAQ). Legal pages return the mapped tool_policy document.' }),
  request('Home categories feed (image + icon)', 'GET', 'local/nit_category/home.php', [
    R('function', 'get_categories', ''), O('limit', '50', 'Max rows, clamped 0..50; 0 (the default) = every top-level category, empty ones included.'), O('alang', '{{lang}}', 'ar / en'),
  ], 'No auth. Category `id` (join key to core_course_get_categories), localised `name`, recursive `coursecount`, hero `image` URL and `icon` (URL **or a single emoji** or "").\n\nDoc: docs/apis/category-course-details-mobile-api.md §1.2'),
  request('Design system (brand colours, category styles, fonts, components)', 'GET', 'theme/nit/design_system.php', [],
    'Public, cached 5 min, CORS `*`. `brandcolors.schemes.{light,dark}` says which group the site wears per mode; `brandcolors.groups[].roles[]` carry the 16 role colours; `categorystyles`, `fonts`, `components` mirror the gallery tabs.\n\nDoc: docs/apis/design-system-api.md'),
  request('Colour palette (legacy flat list)', 'GET', 'theme/nit/colours.php', [],
    'Older flat palette feed: `colours{key:hex}` + `tokens[]`. Prefer design_system.php.\n\nDoc: docs/apis/colour-palette-api.md'),
]);

// ---------- 03 Catalogue & courses -------------------------------------------
const catalogue = folder('03 · Catalogue & Courses',
  'Browsing, searching and opening courses. Prices are resolved per country: profile country → IP country → default row. Pass `country` to override.\n\nDoc: docs/apis/category-course-details-mobile-api.md', [
  ws('Category tree', 'core_course_get_categories', { params: [O('criteria[0][key]', 'parent', 'id | ids | name | parent | idnumber | visible | theme'), O('criteria[0][value]', '0', ''), O('addsubcategories', '1', '')], desc: 'Core. id, name, parent, path, depth, description, coursecount. Join with the home categories feed for image/icon.' }),
  ws('Courses with pricing (list / by category / by ids)', 'local_payments_get_courses_with_pricing', { params: [
    O('field', 'category', 'id | ids | shortname | idnumber | category. Empty = all courses.'),
    O('value', '{{categoryid}}', 'Filter value; for ids a comma-separated list.'),
    O('country', '{{country}}', 'ISO-2 override for pricing.'), O('lang', '{{lang}}', 'en / ar'),
  ], desc: 'Course rows in the shape the app\'s course cards use, each with its country-resolved price, discount/offer and free flag.' }),
  ws('Search courses + subject areas', 'local_nit_category_search', { params: [
    R('query', 'python', 'What the visitor typed (Arabic-tolerant).'),
    O('coursepage', '0', ''), O('courseperpage', '20', 'max 100'), O('categorypage', '0', ''), O('categoryperpage', '20', 'max 100'),
    O('format', 'full', 'full = priced course rows; ids = ids only'), O('country', '{{country}}', ''), O('lang', '{{lang}}', ''),
  ], desc: 'Courses and categories counted and paged separately. Course rows match local_payments_get_courses_with_pricing.' }),
  ws('Course details (product / preview screen)', 'core_course_get_courses_by_field', { params: [R('field', 'id', 'id | ids | shortname | idnumber | category'), R('value', '{{courseid}}', '')], desc: 'Core. Works without enrolment. `overviewfiles[0].fileurl` (+`&token=`) is the cover, `contacts[]` the instructors, `customfields[]` the prerequisites / ILOs / hours / language / certificate / free flags — read `valueraw` for checkbox & number.' }),
  ws('Course price', 'local_payments_get_course_price', { params: [R('courseid', '{{courseid}}', ''), O('country', '{{country}}', ''), O('lang', '{{lang}}', '')], desc: 'Resolved price, currency, and any offer/discount for one course.' }),
  ws('Course access (enrolled? paid? locked?)', 'local_payments_get_course_access', { params: [R('courseid', '{{courseid}}', ''), O('country', '{{country}}', ''), O('lang', '{{lang}}', '')], desc: 'Enrolment + payment status for the course — decides between Buy / Continue / Preview.' }),
  api('Is course free?', 'is_course_free', { params: [R('courseid', '{{courseid}}', '')], desc: '`{courseid,is_free,price?,currency?,country_required?}`.' }),
  api('Enrol in a free course', 'enrol_free_course', { method: 'POST', params: [R('courseid', '{{courseid}}', '')], desc: 'Self-enrols the caller in a course that has no price rows. Fails with `coursenotfree` otherwise.' }),
  ws('My enrolled courses', 'core_enrol_get_users_courses', { params: [R('userid', '{{userid}}', 'The caller\'s id (from site info).'), O('returnusercount', '0', '')], desc: 'Core. The "My learning" list; pair with local_payments_get_purchased_courses.' }),
  request('Course structure — sections, topics, activities (enrolled only)', 'GET', 'local/multitopics/getalltopics.php', [
    R('courseid', '{{courseid}}', ''), R('wstoken', '{{wstoken}}', ''),
  ], 'Full course tree for the player screen: course metadata + `parents[]` (sections → topics → activities). Each activity carries `modname`, `mediatype` (video/audio/image/pdf/document/vdocipher), `isvdocipher`, `otpurl`, `fileurl`, playing time and the free-preview flag. Returns `403 nopermissions` when not enrolled — use core_course_get_courses_by_field for the preview screen.'),
  ws('Course contents (core, alternative)', 'core_course_get_contents', { params: [R('courseid', '{{courseid}}', '')], desc: 'Core equivalent of getalltopics.php without our media/vdocipher enrichment.' }),
]);

// ---------- 04 Instructors ---------------------------------------------------
const teachers = folder('04 · Instructors', 'Instructor directory and profiles (local_academy JSON protocol).', [
  api('Browse instructors', 'browse_teachers', { params: [O('subject', '', 'Filter by subject/category name.')], desc: 'Directory of instructors with photo, subjects and course count.' }),
  api('Instructor profile', 'get_teacher', { params: [R('teacherid', '2', 'User id (e.g. from course contacts[]).')], desc: 'Bio, photo, subjects, courses taught. `errorcode: teachernotfound` when missing.' }),
  api('Instructor courses', 'get_teacher_courses', { params: [R('teacherid', '2', '')], desc: 'Courses this instructor teaches.' }),
  api('All instructors (admin only)', 'get_all_teachers', { params: [O('search', '', ''), O('courseid', '', ''), O('categoryid', '', ''), O('page', '0', ''), O('perpage', '20', '')], desc: 'Requires `local/academy:manageplatform`. Paged, filterable list for a dashboard app.' }),
]);

// ---------- 05 Learning ------------------------------------------------------
const learning = folder('05 · Learning (inside a course)', 'Playing lessons, taking quizzes, asking the AI assistant and filling Job Forms.', [
  folder('Secure video (VdoCipher)', 'DRM video: the server mints a short-lived, watermarked OTP per play. Fetch it **at tap time**, never cache it.\n\nDoc: docs/apis/vdocipher-mobile-api.md', [
    api('Get playback OTP', 'get_playback', { plugin: 'vdocipher', params: [R('cmid', '{{cmid}}', 'Course module id of the VdoCipher activity (or just GET the activity\'s `otpurl`).')], desc: 'Success → `{videoid, otp, playbackInfo, watermark, ttl}` for `EmbedInfo.streaming`. Fail → not enrolled / no access / no video attached.' }),
  ]),
  folder('AI video assistant', '', [
    ws('Ask about the video', 'local_nit_ai_ask', { params: [
      R('cmid', '{{cmid}}', 'Course module id of the video.'), R('question', 'What is a variable?', 'The student\'s question.'),
      O('currenttime', '-1', 'Playback position in seconds, -1 if unknown.'),
      O('history[0][role]', 'user', 'Earlier turns (user | assistant), held by the client.'), O('history[0][text]', '', ''),
    ], desc: 'Requires `local/nit_ai:use`. Answers from the video transcript around the current position.', lang: false }),
  ]),
  folder('Quizzes', 'Quiz slice of the local_academy JSON protocol. Images inside questions come as `qfile.php` URLs that already carry the token.', [
    api('List quizzes', 'get_quizzes', { params: [O('courseid', '{{courseid}}', '0 = every course the user is in.')], desc: 'Quizzes visible to the caller, with attempt counts and open/close dates.' }),
    api('Quiz details', 'get_quiz', { params: [R('cmid', '{{cmid}}', 'Course module id of the quiz.')], desc: 'Settings (time limit, attempts allowed, grade method) + the caller\'s attempt summary.' }),
    api('Start attempt', 'start_quiz_attempt', { method: 'POST', params: [R('quizid', '1', 'Quiz instance id (from get_quiz).')], desc: 'Creates or resumes an attempt and returns the questions with their answer options.' }),
    api('Save one answer (autosave)', 'save_quiz_answer', { method: 'POST', params: [R('attemptid', '1', ''), R('questionid', '1', ''), R('answer', '"2"', 'JSON-encoded answer (index, string, array …).')], desc: 'Saves without finishing; call as the learner moves between questions.' }),
    api('Submit all answers', 'submit_quiz_attempt', { method: 'POST', params: [R('attemptid', '1', ''), R('answers', '[{"questionid":1,"answer":"2"}]', 'JSON array of {questionid, answer}.')], desc: 'Stores every answer in one call (does not finish the attempt).' }),
    api('Finish attempt', 'finish_quiz_attempt', { method: 'POST', params: [R('attemptid', '1', '')], desc: 'Grades the attempt and returns the result summary.' }),
    api('Attempt review', 'get_quiz_attempt', { params: [R('attemptid', '1', '')], desc: 'A finished attempt with per-question correctness, feedback and grade.' }),
    api('My attempts for a quiz', 'get_my_quiz_attempts', { params: [R('quizid', '1', '')], desc: 'History of the caller\'s attempts on one quiz.' }),
    request('Question image / file', 'GET', 'local/academy/qfile.php', [
      R('token', '{{wstoken}}', ''), R('questionid', '1', ''), R('area', 'questiontext', 'questiontext | answer | generalfeedback …'), R('itemid', '0', ''), R('file', 'image.png', ''),
    ], 'Token-authorised file endpoint for images inside question text/options. The app normally receives these URLs ready-made from the quiz calls.'),
  ]),
  folder('Job Form (certificate-gated activity)', 'mod_jobform: a form the student may fill in once the course certificate is issued.\n\nDoc: docs/apis/jobform-mobile-api.md', [
    ws('Job Forms in my courses', 'mod_jobform_get_jobforms_by_courses', { params: [O('courseids[0]', '{{courseid}}', 'Empty = all the user\'s courses.'), O('lang', '{{lang}}', '')], desc: 'The Job Form activities the student can see, with intro and completion info.' }),
    ws('Get the form (fields, groups, gate status, saved answers)', 'mod_jobform_get_form', { params: [R('cmid', '{{cmid}}', ''), O('lang', '{{lang}}', '')], desc: 'Everything needed to render the form: `groups[]`, `fields[]` (type, options, required, dialcodes for phone), whether the certificate gate is open, and any draft/submission.' }),
    ws('Log view (completion)', 'mod_jobform_view_jobform', { type: 'write', params: [R('cmid', '{{cmid}}', '')], desc: 'Triggers the viewed event and view-completion.', lang: false }),
    ws('Submit answers (draft or final)', 'mod_jobform_submit_form', { type: 'write', params: [
      R('cmid', '{{cmid}}', ''),
      R('answers[0][fieldid]', '1', 'Field id from get_form (the `id`, not the fieldid column).'),
      R('answers[0][value]', 'Ahmed Mohamed', 'Multi-select = JSON array; checkbox = "1"/"0"; date = unix ts; phone = "EG:1012345678".'),
      O('answers[1][fieldid]', '2', ''), O('answers[1][value]', '', ''),
      O('draft', '0', '1 = save as draft (skips required checks).'),
    ], desc: 'Validates and stores the answers. Field-level errors return in `warnings[]`.', lang: false }),
  ]),
]);

// ---------- 06 Certificates ---------------------------------------------------
const certs = folder('06 · Certificates', 'The learner\'s own mod_customcert certificates. PDFs are rendered on demand (there is no stored file).', [
  ws('My certificates', 'local_academy_get_my_certificates', { params: [O('page', '0', 'Zero-based.'), O('perpage', '20', ''), O('lang', '{{lang}}', '')], desc: 'Newest first, with the course each belongs to and the public verification link.' }),
  ws('Certificate PDF (base64)', 'local_academy_get_certificate_pdf', { params: [R('certificateid', '1', 'From get_my_certificates.'), O('lang', '{{lang}}', 'Filename / error language only.')], desc: 'The PDF as base64 plus filename and mimetype.' }),
  request('Certificate PDF (binary download)', 'GET', 'local/academy/certificate.php', [R('cmid', '{{cmid}}', 'Course module id of the certificate activity.'), R('token', '{{wstoken}}', '')],
    'Streams the same PDF as `application/pdf` so the app can hand it to a native viewer or share sheet.'),
]);

// ---------- 07 Payments -------------------------------------------------------
const payments = folder('07 · Payments & Checkout',
  'Buying a course through the payment gateway (Fawaterk). Flow: **price → payment methods → create_checkout → open URL / show reference → verify_payment → course access**.\n\nDocs: docs/payments/fawaterk.md · docs/apis/subscriptions-coupons-mobile-guide.md §3.4 (same `payment_data` contract)', [
  folder('Checkout', '', [
    ws('1 · Payment providers for this course', 'local_payments_get_payment_methods', { params: [R('courseid', '{{courseid}}', ''), O('country', '{{country}}', ''), O('lang', '{{lang}}', '')], desc: 'Which gateway(s) serve the buyer\'s country + the course currency.' }),
    ws('2 · Gateway payment methods (card, Fawry, Meeza, wallets)', 'local_payments_get_provider_payment_methods', { params: [O('courseid', '{{courseid}}', '0 = resolve by country/currency alone (subscriptions).'), O('country', '{{country}}', ''), O('currency', '', 'Only used when courseid is 0.'), O('lang', '{{lang}}', '')], desc: 'The concrete methods the active gateway offers; pass the chosen `id` as `payment_method_id` to create_checkout.' }),
    ws('3 · Create checkout', 'local_payments_create_checkout', { type: 'write', params: [
      R('courseid', '{{courseid}}', ''), O('country', '{{country}}', ''), O('lang', '{{lang}}', 'Gateway page language (en/ar).'),
      O('coupon_code', '', 'Coupon to apply. The larger of coupon vs. live offer wins — never both.'),
      O('payment_method_id', '0', '0 = gateway-hosted picker page; otherwise a specific method id.'),
    ], desc: 'Returns `order_id`, `checkout_url` and `payment_data{type: redirect|reference|none, redirect_url?, reference?, reference_expires_at?, qr?}`.' }),
    ws('4 · Verify payment (after return)', 'local_payments_verify_payment', { type: 'write', params: [R('order_id', '<ORDER_ID>', 'From create_checkout.'), O('lang', '{{lang}}', '')], desc: 'Confirms with the gateway and enrols on success. Safe to call repeatedly / poll for reference-type payments.' }),
  ]),
  folder('History, invoices & refunds', '', [
    ws('Purchased courses', 'local_payments_get_purchased_courses', { params: [O('lang', '{{lang}}', '')], desc: 'Courses the caller has paid for.' }),
    ws('Transactions (filtered, paged screen)', 'local_payments_get_transactions', { params: [
      O('page', '0', ''), O('perpage', '20', ''), O('q', '', 'Search order reference / invoice number.'), O('status', '', 'One of filters.statuses.'),
      O('courseid', '0', ''), O('datefrom', '', 'YYYY-MM-DD'), O('dateto', '', 'YYYY-MM-DD'), O('lang', '{{lang}}', ''),
    ], desc: 'The Invoices screen in one call: rows, total, filter options, and per row whether an invoice can be printed and a refund still taken.' }),
    ws('Payment history (simple)', 'local_payments_get_payment_history', { params: [O('page', '0', ''), O('perpage', '20', ''), O('lang', '{{lang}}', '')], desc: 'Older, unfiltered list. Prefer get_transactions.' }),
    ws('Invoice (details + PDF)', 'local_payments_get_invoice', { params: [R('transaction_id', '{{transactionid}}', ''), O('include_pdf', '1', '0 = details only.'), O('lang', '{{lang}}', '')], desc: 'Invoice details and, by default, the PDF as `pdf_base64`.' }),
    ws('Refund options', 'local_payments_get_refund_options', { params: [R('transaction_id', '{{transactionid}}', ''), O('lang', '{{lang}}', '')], desc: 'What refund the buyer can have on this payment (instant within the window, or a staff request after), and what it is worth.' }),
    ws('Submit refund / refund request', 'local_payments_submit_refund', { type: 'write', params: [R('transaction_id', '{{transactionid}}', ''), O('reason', '', 'Required when the request goes to staff.'), O('lang', '{{lang}}', '')], desc: 'Refunds immediately when allowed, otherwise files a request.' }),
  ]),
]);

// ---------- 08 Offers & coupons ----------------------------------------------
const commerce = folder('08 · Offers & Coupons', 'Discounts. Offers apply automatically; coupons are typed in. The larger discount wins — never both.\n\nDoc: docs/apis/subscriptions-coupons-mobile-guide.md §3.1–3.2', [
  ws('Available coupons', 'local_nit_commerce_get_available_coupons', { params: [O('categoryid', '0', 'Only coupons scoped to this category branch + site-wide ones; 0 = all.'), O('lang', '{{lang}}', '')], desc: 'Coupons the calling user can redeem now: active, in window, under the site-wide cap and under their own per-student cap. Each row carries usage_limit (all students; 0 = unlimited), user_limit (one student; 0 = unlimited), plus user_usage_count / user_uses_left (null when no per-student cap or a guest). usage_type (once / multiple) no longer exists.' }),
  ws('Preview discounted price', 'local_nit_commerce_preview_discount', { params: [R('item_type', 'course', 'course | package | subscription | program'), R('item_id', '{{courseid}}', ''), O('coupon_code', 'SUMMER10', 'Empty = offer-only price.'), O('country', '{{country}}', 'Course pricing only.'), O('lang', '{{lang}}', '')], desc: 'Base price, best offer, coupon result (`coupon_error` when rejected) and final price — without charging.' }),
]);

// ---------- 09 Subscriptions --------------------------------------------------
const subs = folder('09 · Subscriptions', 'Plans that unlock a set of courses for a period. Flow: **available plans → preview_discount (optional) → create_subscription_checkout → open URL → poll my_subscriptions**.\n\nDoc: docs/apis/subscriptions-coupons-mobile-guide.md', [
  ws('Available plans', 'local_nit_subscriptions_get_available_subscriptions', { params: [O('categoryid', '0', 'Only plans scoped to this category branch; 0 = all.'), O('country', '{{country}}', ''), O('lang', '{{lang}}', '')], desc: 'Active plans with price, duration, `seat_options` (B2B) and the courses each unlocks.' }),
  ws('Create subscription checkout', 'local_nit_subscriptions_create_subscription_checkout', { type: 'write', params: [
    R('subscriptionid', '{{subscriptionid}}', 'Plan id.'), O('type', 'normal', 'normal | b2b'), O('seats', '0', 'Required when type=b2b.'),
    O('coupon_code', '', 'Normal purchase only.'), O('country', '{{country}}', ''), O('lang', '{{lang}}', 'Gateway language.'),
    O('return_url', '', ''), O('payment_method_id', '0', 'From local_payments_get_provider_payment_methods (courseid=0).'),
  ], desc: 'Returns `checkout_url` + `payment_data` (same contract as course checkout). Activation happens on gateway callback — poll get_my_subscriptions.' }),
  ws('My active plan', 'local_nit_subscriptions_get_my_active_subscription', { params: [O('lang', '{{lang}}', '')], desc: 'Plan id, days left and whether renewal is due — for the badge on the account screen.' }),
  ws('My subscriptions', 'local_nit_subscriptions_get_my_subscriptions', { params: [O('lang', '{{lang}}', '')], desc: 'All the caller\'s subscription purchases, active first.' }),
  ws('Subscription payment history', 'local_nit_subscriptions_get_subscription_payment_history', { params: [O('lang', '{{lang}}', '')], desc: 'Gateway transactions for subscriptions, newest first.' }),
]);

// ---------- 10 Account & profile ---------------------------------------------
const account = folder('10 · Account & Profile', 'The account screen: navigation, profile pane, security, e-mail change, delete account.\n\nDocs: docs/apis/account-screens-mobile-api.md · docs/apis/profile-mobile-api.md', [
  folder('Account screen', '', [
    ws('Account menu (left nav)', 'local_profilefields_get_account_menu', { params: [O('active', 'profile', 'profile | security | mylearning | certificates | invoices | delete')], desc: 'The entries, in order, localised, only for installed plugins.' }),
    ws('Account profile pane', 'local_profilefields_get_account_profile', { desc: 'Fields in their sections with label, value, display value, options and lock, plus the e-mail row and picture control.' }),
    ws('Security pane', 'local_profilefields_get_security', { desc: 'Whether a password exists here to change (OAuth accounts may not), last change, cost of changing (signs out everywhere), and the password policy.' }),
    ws('Delete-account info', 'local_profilefields_get_delete_account_info', { desc: 'Whether this account may delete itself and the warning + confirmation word to show.' }),
  ]),
  folder('Profile read / edit', '', [
    ws('Profile (as /user/profile.php shows it)', 'local_profilefields_get_profile', { params: [O('userid', '0', '0 = caller.')], desc: 'Details, the custom fields this viewer may see, and the section/row tree.' }),
    ws('Profile edit form description', 'local_profilefields_get_profile_form', { params: [O('userid', '0', '0 = caller.')], desc: 'Fields in order with labels, current values, requiredness, options and which the auth plugin has locked.' }),
    ws('Custom profile field catalogue', 'local_profilefields_get_profile_fields', { desc: 'Every custom user profile field with its option values (menu fields).' }),
    ws('Update profile', 'local_profilefields_update_profile', { type: 'write', params: [
      R('fields[0][name]', 'firstname', 'Name as get_profile_form / get_account_profile gave it. "lang" is also accepted.'),
      R('fields[0][value]', 'Ahmed', ''), O('fields[1][name]', 'profile_field_phone', ''), O('fields[1][value]', 'EG:1012345678', ''),
      O('userid', '0', ''), O('descriptionformat', '1', '1 = HTML, 2 = plain.'), O('consent', '0', 'See OAuth completion.'),
    ], desc: 'Same capability checks, validation and e-mail-change confirmation as /user/edit.php. Anything left out keeps its value.' }),
    ws('Upload a file to the draft area (step 1 of picture change)', 'core_files_upload', { type: 'write', params: [
      R('contextlevel', 'user', ''), R('instanceid', '{{userid}}', ''), R('component', 'user', ''), R('filearea', 'draft', ''), R('itemid', '0', '0 = new draft area'),
      R('filepath', '/', ''), R('filename', 'avatar.jpg', ''), R('filecontent', '<BASE64_IMAGE>', 'Base64 file bytes.'),
    ], desc: 'Core. Returns `itemid` of the draft area for core_user_update_picture.', lang: false }),
    ws('Set profile picture (step 2)', 'core_user_update_picture', { type: 'write', params: [R('draftitemid', '<ITEMID_FROM_UPLOAD>', ''), O('delete', '0', '1 = remove the picture.'), O('userid', '0', '')], desc: 'Core. Applies the uploaded draft file as the user picture.', lang: false }),
    api('My profile (legacy academy shape)', 'get_my_profile', { desc: 'Older profile payload used by earlier app builds. Prefer local_profilefields_get_account_profile.' }),
  ]),
  folder('Credentials', 'Changing the password or e-mail. A password change destroys **every** session and token, including the caller\'s — expect the next call to return 401 / invalidtoken and go to login.', [
    ws('Change password', 'local_academy_change_password', { type: 'write', params: [R('currentpassword', 'Secret123!', ''), R('newpassword', 'NewPass456!', 'Must satisfy passwordpolicy from get_security.')], desc: 'Ends every session and token the account holds.', lang: false }),
    api('Change password (legacy academy endpoint)', 'change_password', { method: 'POST', params: [R('currentpassword', 'Secret123!', ''), R('newpassword', 'NewPass456!', '')], desc: 'Same effect via the JSON protocol.' }),
    ws('Request e-mail change', 'local_profilefields_request_email_change', { type: 'write', params: [R('newemail', 'new@example.com', ''), R('password', 'Secret123!', 'Proves the account is the caller\'s.')], desc: 'Nothing changes until the confirmation link sent to the new address is opened.', lang: false }),
    ws('Delete my account', 'local_profilefields_delete_account', { type: 'write', params: [R('password', 'Secret123!', ''), R('confirmword', 'DELETE', 'Exactly as get_delete_account_info gave it (case-insensitive).')], desc: 'Anonymises rather than hard-deletes; destroys every token including the caller\'s.', lang: false }),
  ]),
]);

// ---------- collection ---------------------------------------------------------
const collection = {
  info: {
    _postman_id: uid(),
    name: 'EAAC Mobile API',
    description: [
      '# EAAC Mobile API',
      '',
      'Every endpoint the EAAC mobile app calls on the Moodle 5.2 backend, ordered the way a user meets them: sign-in → browse → learn → pay → account.',
      '',
      '## Three transports',
      '',
      '| Transport | URL | Auth | Errors |',
      '|---|---|---|---|',
      '| **Moodle web service** (most calls) | `{{baseUrl}}/webservice/rest/server.php?wsfunction=…&moodlewsrestformat=json` | `wstoken` | HTTP 200 with `{"exception","errorcode","message"}` |',
      '| **Academy JSON protocol** (login, OTP, quizzes, instructors, video OTP) | `{{baseUrl}}/local/academy/api.php?function=…` and `/local/vdocipher/api.php` | `token` | `{"status":"fail","error","errorcode"}`; HTTP **401** only when the token is dead |',
      '| **Plain endpoints** | `getalltopics.php`, `home.php`, `design_system.php`, `certificate.php`, `googleauth/token.php` | varies (see each) | varies |',
      '',
      '## Variables (collection → Variables tab)',
      '',
      '| Variable | Meaning |',
      '|---|---|',
      '| `baseUrl` | Site root **without** trailing slash |',
      '| `regtoken` | Shared *Registration API* token — pre-login calls (sign-up, OTP, login, public content) |',
      '| `wstoken` | The signed-in user\'s token — filled automatically from the Login response |',
      '| `userid` | Filled automatically by Login / Site info |',
      '| `service` | `moodle_mobile_app` |',
      '| `lang` | `en` or `ar` — sent as `moodlewssettinglang` / `lang` |',
      '| `country` | ISO-2 override for pricing (e.g. `EG`, `SA`) |',
      '| `courseid` `cmid` `categoryid` `transactionid` `subscriptionid` | Sample ids used by the requests |',
      '',
      '## Conventions in this collection',
      '',
      '- **Read** functions are `GET` with query params; **write** functions are `POST` with an `x-www-form-urlencoded` body. Moodle accepts either for both.',
      '- Optional parameters are present but **unchecked** (disabled) so you can see them without sending them. Tick to send.',
      '- Array parameters use Moodle\'s form syntax: `fields[0][name]`, `customprofilefields[0][value]`, `courseids[0]`.',
      '- A collection-level test flags Moodle `exception` envelopes and academy `status: fail` envelopes as failed, even though the HTTP status is 200.',
      '- `errorcode: accessexception` means the function is **not in the token\'s service** — fix on the server with `php local/payments/cli/ws_diagnose.php --token=… --fix`, not in the app.',
      '',
      'Source of truth: `public/local/*/db/services.php`, `public/mod/jobform/db/services.php`, `public/local/academy/api.php`, and the guides in `docs/apis/`.',
    ].join('\n'),
    schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
  },
  item: [auth, site, catalogue, teachers, learning, certs, payments, commerce, subs, account],
  event: [{
    listen: 'test',
    script: { type: 'text/javascript', exec: [
      "// Moodle answers errors with HTTP 200, so inspect the body.",
      "let body = null; try { body = pm.response.json(); } catch (e) {}",
      "pm.test('HTTP status is 2xx', () => pm.expect(pm.response.code).to.be.within(200, 299));",
      "if (body && typeof body === 'object') {",
      "  pm.test('No Moodle exception', () => pm.expect(body.exception, body.errorcode + ': ' + body.message).to.be.undefined);",
      "  pm.test('No academy fail envelope', () => pm.expect(body.status, body.errorcode + ': ' + body.error).to.not.equal('fail'));",
      "  // Capture tokens / ids so the rest of the collection just works.",
      "  if (body.status === 'success' && body.data && body.data.token) { pm.collectionVariables.set('wstoken', body.data.token); if (body.data.userid) pm.collectionVariables.set('userid', String(body.data.userid)); }",
      "  if (body.token && !body.status) { pm.collectionVariables.set('wstoken', body.token); }",
      "  if (body.userid && body.sitename) { pm.collectionVariables.set('userid', String(body.userid)); }",
      "}",
    ] },
  }],
  variable: [
    { key: 'baseUrl', value: 'https://eaac.example.com', type: 'string' },
    { key: 'regtoken', value: '', type: 'string' },
    { key: 'wstoken', value: '', type: 'string' },
    { key: 'userid', value: '', type: 'string' },
    { key: 'service', value: 'moodle_mobile_app', type: 'string' },
    { key: 'lang', value: 'en', type: 'string' },
    { key: 'country', value: 'EG', type: 'string' },
    { key: 'courseid', value: '9', type: 'string' },
    { key: 'cmid', value: '1', type: 'string' },
    { key: 'categoryid', value: '1', type: 'string' },
    { key: 'transactionid', value: '1', type: 'string' },
    { key: 'subscriptionid', value: '1', type: 'string' },
  ],
};

process.stdout.write(JSON.stringify(collection, null, 2) + '\n');
