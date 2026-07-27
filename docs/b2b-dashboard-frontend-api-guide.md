# دليل الواجهة الأمامية (Web + Mobile) — لوحة إدارة اشتراك B2B

**الصفحة:** `/local/academy/b2b_dashboard.php`
**الجمهور:** مطوّرو الويب ومطوّرو تطبيق الموبايل الذين يبنون واجهة إدارة اشتراك B2B.
**الوظيفة:** تمكين المستخدم صاحب اشتراك B2B (مدير الأعمال / `b2b_administrator`) من إدارة اشتراكاته:
عرض السعة، توليد **رابط الدعوة** ومشاركته، والموافقة/الرفض/الإزالة للأعضاء.

> كل الاستدعاءات تمرّ عبر نقطة نهاية واحدة: `api.php` بنمط
> `?function=<name>&token=<token>`، وترجع JSON بالشكل `{status, data}` عند النجاح أو
> `{status:'fail'|'error', error:'...'}` عند الفشل.

---

## 0. اجعل الدومين ديناميكيًا من ملف البيئة (env) — إلزامي

**لا تكتب الدومين `https://academy2026.nitg-eg.com` بشكل ثابت (hardcoded) في كود الويب أو الموبايل.**
اقرأه من متغيّر بيئة حتى يعمل نفس الكود على `dev / staging / production` دون تعديل.

`base_url` هو أصل الموقع (origin) فقط، ونشتق منه نقطتَي النهاية:

| المتغيّر | يُشتق منه |
|---|---|
| `ACADEMY_BASE_URL` (مثال: `https://academy2026.nitg-eg.com`) | — |
| نقطة نهاية الـ API | `${ACADEMY_BASE_URL}/local/academy/api.php` |
| نقطة الحصول على التوكن (Mobile) | `${ACADEMY_BASE_URL}/login/token.php` |

**Web (`.env`):**
```env
# Vite
VITE_ACADEMY_BASE_URL=https://academy2026.nitg-eg.com
# Next.js
NEXT_PUBLIC_ACADEMY_BASE_URL=https://academy2026.nitg-eg.com
```

```js
const BASE_URL = import.meta.env.VITE_ACADEMY_BASE_URL;          // Vite
// const BASE_URL = process.env.NEXT_PUBLIC_ACADEMY_BASE_URL;    // Next.js
const API_ENDPOINT = `${BASE_URL}/local/academy/api.php`;
```

**Flutter / React Native (env أو dart-define):**
```dart
const baseUrl = String.fromEnvironment('ACADEMY_BASE_URL');
final apiEndpoint = '$baseUrl/local/academy/api.php';
final tokenEndpoint = '$baseUrl/login/token.php';
```

> ملاحظة مهمة عن **رابط الدعوة**: الـ API نفسه يُرجع الرابط كاملًا (`data.url`) مبنيًّا على الدومين
> المضبوط في إعدادات Moodle (`$CFG->wwwroot`) على الخادم. لذلك **لا تُركِّب الرابط يدويًا في العميل** —
> استخدم القيمة القادمة من الخادم كما هي. متغيّر البيئة في العميل يخصّ نقطة نهاية الـ API فقط.

---

## 1. المصادقة (Authentication) — كيف تحصل على `token`

كل استدعاء يتطلّب `token` صالحًا لخدمة الموبايل الرسمية (`moodle_mobile_app`). الـ `token`
يُحدِّد المستخدم؛ والملكية (أن هذا المستخدم يملك اشتراك B2B فعلًا) تُفرض داخل الخادم في `b2b_manager`.

### Web (داخل صفحة Moodle)
الصفحة تولّد التوكن على الخادم وتحقنه في `window.ACADEMY_B2B`:
```js
window.ACADEMY_B2B = {
  endpoint: "https://.../local/academy/api.php",
  token:    "<per-user token>",
  lang:     "ar"   // أو "en"
};
```
إن كنت تبني SPA منفصلًا، احصل على التوكن بنفس طريقة الموبايل أدناه.

### Mobile (تطبيق مستقل)
اطلب التوكن مرة واحدة عند تسجيل الدخول، ثم خزّنه بأمان (Keychain / Keystore):
```
POST ${BASE_URL}/login/token.php
  username=<user>
  password=<pass>
  service=moodle_mobile_app
→ { "token": "abc123...", "privatetoken": "..." }
```
> **قواعد الأمان:** لا تُدخِل بيانات اعتماد المستخدم في أي واجهة إلا شاشة تسجيل الدخول الرسمية،
> ولا تضع التوكن في الـ URL query عند POST — أرسله في جسم الطلب (body).

---

## 2. الاصطلاح العام للطلبات (Request/Response contract)

- **القراءة (GET):** المعاملات في الـ query string.
- **التعديل (POST):** يجب أن تكون `POST` (الخادم يرفض غير ذلك بـ `err_postrequired`)، والجسم
  `application/x-www-form-urlencoded`.
- **اللغة:** أرسل `alang=ar|en` (وليس `lang`) لتُترجَم رسائل الخادم. استخدام `lang` قد يعبث بلغة
  الجلسة — استخدم `alang` دائمًا.
- **شكل الرد:**
  - نجاح: `{ "status": "success", "data": <payload> }`
  - فشل: `{ "status": "fail" | "error", "error": "<رسالة مترجمة>" }`

### Helper موحّد (Web)
```js
async function api(fn, params = {}, method = 'GET') {
  const data = new URLSearchParams({ function: fn, token: CFG.token });
  if (CFG.lang) data.append('alang', CFG.lang);          // ← alang وليس lang
  Object.entries(params).forEach(([k, v]) => data.append(k, v));

  let url = API_ENDPOINT, opts = {};
  if (method === 'POST') {
    opts = { method: 'POST',
             headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
             body: data.toString() };
  } else {
    url += '?' + data.toString();
  }
  const res  = await fetch(url, opts);
  const json = await res.json();                          // قد يفشل إذا انتهت الجلسة
  if (json.status !== 'success') throw new Error(json.error || 'Request failed');
  return json.data;
}
```

---

## 3. تدفّق الصفحة (Page flow) — خطوة بخطوة

```
1) get_my_b2b_subscriptions            ← اجلب اشتراكات B2B التي يديرها المستخدم
     └─ إن كانت فارغة → اعرض "لا توجد اشتراكات" وتوقّف
     └─ إن كان أكثر من اشتراك → اعرض قائمة اختيار (dropdown)
2) get_b2b_dashboard(purchaseid)       ← للاشتراك المختار: السعة + الأعضاء + الدعوات
     ├─ capacity     → بطاقات الإحصائيات
     ├─ invitations  → منطقة رابط الدعوة
     └─ members      → جدول الأعضاء (مع فلترة حسب الحالة)
3) إجراءات المستخدم (كلها POST ثم إعادة تحميل get_b2b_dashboard):
     ├─ b2b_generate_invite / b2b_revoke_invite     ← رابط الدعوة
     ├─ b2b_approve_member / b2b_reject_member       ← الأعضاء المعلّقون
     └─ b2b_remove_member                            ← الأعضاء المعتمَدون
```

على الموبايل: نفس التسلسل تمامًا — استدعِ `get_my_b2b_subscriptions` عند فتح الشاشة، ثم
`get_b2b_dashboard` عند اختيار اشتراك، وأعد تحميله بعد كل إجراء ناجح.

---

## 4. مرجع الـ APIs

### 4.1 `get_my_b2b_subscriptions` — قائمة اشتراكات B2B للمستخدم
- **Method:** GET · **Params:** لا شيء (غير التوكن).
- **Response `data`:** مصفوفة اشتراكات:
```json
[
  {
    "purchaseid": 42,          // مُعرّف الاشتراك — يُمرَّر لكل الاستدعاءات التالية
    "subscriptionid": 7,
    "name": "الباقة الاحترافية",
    "seats": 20,               // إجمالي المقاعد المشتراة
    "consumed": 12,            // مقاعد مستهلكة
    "available": 8,            // مقاعد متاحة
    "price_paid": "4000.00",
    "status": "active",        // active | expired | cancelled
    "expires_at": 1793664000   // Unix timestamp، 0 = لا ينتهي
  }
]
```
- **الاستخدام:** لو `[]` فارغة → المستخدم ليس مدير B2B، اعرض حالة فارغة. لو عنصر واحد → افتحه مباشرة.
  لو أكثر → dropdown، والنص المقترح: `name (available/seats)`.

### 4.2 `get_b2b_dashboard` — تفاصيل اشتراك واحد
- **Method:** GET · **Params:** `purchaseid` (int، مطلوب).
- **Response `data`:** ثلاثة أقسام:
```json
{
  "capacity": {
    "purchaseid": 42,
    "capacity": 20,            // = seats
    "consumed": 12,
    "available": 8,
    "pending": 3,              // طلبات تنتظر الموافقة
    "approved": 12,
    "rejected": 1,
    "removed": 2,
    "removed_returned": 1,     // أُزيلوا وأُعيد مقعدهم
    "removed_kept": 1,         // أُزيلوا واحتُفظ بمقعدهم
    "expires_at": 1793664000,
    "status": "active"
  },
  "members": [
    {
      "id": 501,               // membershipid — يُمرَّر لإجراءات العضو
      "userid": 88,
      "user_fullname": "أحمد علي",
      "user_email": "ahmed@example.com",
      "status": "pending",     // pending | approved | rejected | removed | expired
      "consumes_seat": 0,      // 1 إذا كان يشغل مقعدًا
      "approved_at": 0,
      "removed_at": 0,
      "reject_reason": "",
      "timecreated": 1790000000
    }
  ],
  "invitations": [
    {
      "id": 9,
      "status": "active",      // active | expired | disabled | revoked
      "expires_at": 0,
      "timecreated": 1790000000,
      "url": "https://.../local/academy/b2b_join.php?t=<token>"
      // ↑ url موجود فقط للرابط النشِط الذي خُزِّن توكنه (روابط قديمة قد لا تحتويه)
    }
  ]
}
```
- **بطاقات الإحصائيات:** اعرض `capacity` / `consumed` / `available` / `pending` /
  `expires_at` (نسّق التاريخ من Unix timestamp ×1000).
- **فلترة الأعضاء:** فلتر مصفوفة `members` في العميل حسب `status` (تبويبات: الكل، معلّق، معتمَد،
  مرفوض، مُزال). لا حاجة لاستدعاء جديد عند تبديل التبويب.

### 4.3 رابط الدعوة (Invitation link) — التفصيل في القسم 5
- `b2b_generate_invite` (POST): توليد رابط.
- `b2b_revoke_invite` (POST): إلغاء رابط.

### 4.4 إجراءات الأعضاء (كلها POST)

| الوظيفة | متى | Params | ملاحظات |
|---|---|---|---|
| `b2b_approve_member` | العضو `pending` | `membershipid` | يفشل إذا لا مقاعد متاحة (`err_nofreeseats`). idempotent |
| `b2b_reject_member`  | العضو `pending` | `membershipid`, `reason` (اختياري) | يُخطَر العضو بالرفض |
| `b2b_remove_member`  | العضو `approved` | `membershipid` | يسحب صلاحية الدخول للكورسات؛ إعادة المقعد حسب إعداد المنصّة |

- **الرد لكل هذه:** `{ "status":"success", "data": { "id": <membershipid> } }`.
- **بعد كل نجاح:** أعد استدعاء `get_b2b_dashboard(purchaseid)` لتحديث الأرقام والجدول.
- **UX مقترح:** أكّد قبل `remove` و`reject` (مع حقل سبب اختياري) و`revoke`.

مثال (Web):
```js
// موافقة
await api('b2b_approve_member', { membershipid: 501 }, 'POST');
// رفض مع سبب
await api('b2b_reject_member', { membershipid: 501, reason: 'خارج الفريق' }, 'POST');
// إزالة عضو معتمَد
await api('b2b_remove_member', { membershipid: 501 }, 'POST');
await loadDashboard(); // إعادة تحميل
```

---

## 5. كيفية استخدام «رابط الدعوة» (Invitation link)

رابط الدعوة هو الآلية التي يدعو بها مدير B2B أعضاءه للانضمام لاشتراكه. **الرابط نفسه لا يمنح أي
صلاحية** — هو فقط يُنشئ *طلب عضوية* يبقى `pending` حتى يوافق المدير (أو يُعتمَد تلقائيًا إن كان
الإعداد مفعّلًا ويوجد مقعد شاغر).

### 5.1 دورة الحياة
```
[المدير] b2b_generate_invite ──► رابط نشِط (data.url)
     │
     ├─ يشارك الرابط (نسخ / QR / رسالة)
     ▼
[المدعوّ] يفتح b2b_join.php?t=<token>
     ├─ غير مسجّل دخول → صفحة "سجّل الدخول أو أنشئ حسابًا" ثم يعود للرابط
     └─ مسجّل دخول → تُنشأ عضوية pending (أو approved تلقائيًا)
     ▼
[المدير] يوافق / يرفض من الـ dashboard
     │
     └─ b2b_revoke_invite ──► إبطال الرابط (لا ينفع بعدها)
```

### 5.2 توليد الرابط — `b2b_generate_invite`
- **Method:** POST · **Params:** `purchaseid` (مطلوب)، `expires_at` (Unix timestamp اختياري، `0` = لا ينتهي).
- **Response `data`:**
```json
{ "id": 9,
  "url": "https://.../local/academy/b2b_join.php?t=Ab3...40chars",
  "expires_at": 0,
  "status": "active" }
```
```js
const inv = await api('b2b_generate_invite', { purchaseid: CURRENT }, 'POST');
showInviteLink(inv.url);   // استخدم inv.url كما هو من الخادم — لا تُركّبه يدويًا
```
> **مهم:** `url` يأتي كاملًا من الخادم (مبنيًّا على `$CFG->wwwroot`). في العميل تعامل معه كنص جاهز
> للنسخ/المشاركة/توليد QR فقط.

### 5.3 عرض الرابط النشِط
- يظهر ضمن `get_b2b_dashboard().invitations` — خُذ العنصر الذي `status === 'active'` وله حقل `url`.
- إن كان `active` بلا `url` (رابط قديم قبل تخزين التوكن): لا يمكن إعادة عرضه — اعرض زر «إلغاء» فقط،
  أو ولّد رابطًا جديدًا.

### 5.4 مشاركة الرابط في العميل
- **Web:** زر «نسخ» عبر `navigator.clipboard.writeText(url)` مع fallback إلى `execCommand('copy')`.
- **Mobile:** استخدم ورقة المشاركة الأصلية (`Share` في React Native، `share_plus` في Flutter)
  و/أو ولّد **QR code** من `url` لمسحه.

### 5.5 إلغاء الرابط — `b2b_revoke_invite`
- **Method:** POST · **Params:** `invitationid` (= `invitation.id`).
- **Response:** `{ "status":"success", "data": { "id": <invitationid> } }`.
- بعد الإلغاء لن يصلح الرابط لأي انضمام جديد. أعد تحميل الـ dashboard.
```js
await api('b2b_revoke_invite', { invitationid: 9 }, 'POST');
await loadDashboard();
```

### 5.6 جانب المدعوّ (اختياري للموبايل) — `b2b_join`
صفحة `b2b_join.php` تتولّى التدفّق للويب تلقائيًا. لو أردت تنفيذ الانضمام داخل تطبيق الموبايل بعد
تسجيل الدخول، استدعِ:
- **Method:** POST · **Params:** `t` = التوكن الخام من الرابط.
- **Response `data`:** `{ "membershipid": 777, "status": "pending" | "approved", "existing": true? }`
  - `existing: true` تعني أنه عضو بالفعل (لا تُنشأ عضوية مكرّرة).
- **الاستخراج:** التوكن هو قيمة الـ query `?t=` من الرابط.

---

## 6. معالجة الأخطاء (Error handling)

الرد الفاشل دائمًا `{ status:'fail'|'error', error:'<رسالة مترجمة>' }`. أهم الحالات:

| رسالة/سبب | المعنى | تصرّف العميل |
|---|---|---|
| `err_authrequired` / `err_invalidtoken` | توكن مفقود/غير صالح أو انتهت الجلسة | أعد المستخدم لتسجيل الدخول |
| رد ليس JSON (فشل التحليل) | غالبًا انتهاء الجلسة | اعرض «انتهت الجلسة» وأعد المصادقة |
| `err_b2bnotowner` | المستخدم لا يملك هذا الاشتراك | أخفِ الإجراء |
| `err_b2bnotactive` / `err_b2bexpired` | الاشتراك غير نشِط/منتهٍ | امنع التوليد والموافقة |
| `err_nofreeseats` | لا مقاعد متاحة عند الموافقة | اعرض «لا مقاعد متاحة» |
| `err_notpending` / `err_notapproved` | الحالة تغيّرت (سباق) | أعد تحميل الـ dashboard |
| `err_invalidinvite` | رابط دعوة غير صالح/منتهٍ/ملغى | رسالة واضحة للمدعوّ |
| `err_postrequired` | استُخدم GET لإجراء تعديل | استخدم POST |

اعرض دائمًا `error` القادم من الخادم (مترجَم عبر `alang`) بدل نص ثابت في العميل.

---

## 7. ملخص جدول الـ APIs

| الوظيفة | Method | Params | الغرض |
|---|---|---|---|
| `get_my_b2b_subscriptions` | GET | — | اشتراكات B2B للمستخدم |
| `get_b2b_dashboard` | GET | `purchaseid` | سعة + أعضاء + دعوات |
| `b2b_generate_invite` | POST | `purchaseid`, `expires_at?` | توليد رابط دعوة |
| `b2b_revoke_invite` | POST | `invitationid` | إلغاء رابط دعوة |
| `b2b_approve_member` | POST | `membershipid` | قبول عضو معلّق |
| `b2b_reject_member` | POST | `membershipid`, `reason?` | رفض عضو معلّق |
| `b2b_remove_member` | POST | `membershipid` | إزالة عضو معتمَد |
| `b2b_join` | POST | `t` (token) | انضمام المدعوّ (اختياري للموبايل) |

كل الـ POST ترجع `{ status:'success', data:{ id } }`. أضِف `token` لكل طلب و`alang=ar|en` للترجمة.

---

**مراجع الكود:**
- الصفحة: [b2b_dashboard.php](../src/local/academy/b2b_dashboard.php)
- صفحة الانضمام: [b2b_join.php](../src/local/academy/b2b_join.php)
- منطق الأعمال: [b2b_manager.php](../src/local/academy/classes/b2b_manager.php)
- نقطة الـ API (قسم B2B): [api.php](../src/local/academy/api.php) (الأسطر ~518–584)
