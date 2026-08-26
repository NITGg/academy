# local_games — ركن الألعاب

الخطة كاملة في [`docs/Games Doc`](../../../docs/Games%20Doc/README.md): **24 لعبة**،
كلها معرّفة هنا من اليوم الأول، والمنفَّذ منها لعبتان.

| اللعبة | الحالة |
|--------|--------|
| 01 🔢 سباق الحساب `math-race` | ✅ جاهزة |
| 02 🧮 صياد الأرقام `math-catcher` | ✅ جاهزة |
| 03 → 24 | 🕐 في السجل، الكارت يظهر "قريباً" |

## البنية

```
index.php              صفحة الركن — كل الألعاب في شبكة كروت
play.php               صدفة اللعب المشتركة لكل الألعاب
classes/registry.php   سجل الألعاب الـ24 + شروط الشارات
classes/progress.php   النقاط والشارات (منطق الأعمال)
classes/external/      الدالة التي يستدعيها المتصفح آخر كل جولة
js/shell.js            الصدفة: الصوت، الـHUD، البداية والنهاية، الحفظ
js/<slug>.js           لعبة واحدة لكل ملف
templates/             hub.mustache و play.mustache
styles.css             تصميم الركن كله
```

القاعدة: **`shell.js` يملك كل ما يجب أن يتصرّف بنفس الطريقة في الـ24 لعبة** —
الصوت، النقاط، كارت النهاية، الحفظ. ملف اللعبة يصف جولته فقط.

## إضافة لعبة جديدة

مثال: اللعبة 03 `math-shop`.

**1.** في [`classes/registry.php`](classes/registry.php) غيّر حالتها إلى
`STATUS_LIVE` وأضف شاراتها:

```php
'math-shop' => [
    'num' => 3, 'emoji' => '💰', 'category' => 'numbers',
    'level' => 2, 'minutes' => '4-6', 'status' => self::STATUS_LIVE,
    'badges' => ['smart-shopper' => ['correct' => 15, 'maxwrong' => 1]],
],
```

**2.** أضف في ملفَّي اللغة (`lang/en` و `lang/ar`):

```php
$string['js_math_shop_ready'] = '...';   // عنوان كارت البداية
$string['js_math_shop_howto'] = '...';   // شرح مختصر
$string['badge_smart_shopper'] = '...';
$string['badgehint_smart_shopper'] = '...';
```

> اسم المفتاح = الـslug بشرطة سفلية بدل الشرطة. `play.php` يبني
> `js_<key>_ready` و `js_<key>_howto` تلقائياً، فالتسمية إلزامية.
> أي مفتاح يبدأ بـ `js_` يصل إلى المتصفح تلقائياً في `api.strings`
> (بدون البادئة).

**3.** أنشئ `js/math-shop.js`:

```js
window.LocalGames.register('math-shop', function (api) {
    return {
        start: function () { /* ابنِ الجولة داخل api.stage */ },
        stop:  function () { /* أوقف أي مؤقت أو حلقة رسم */ }
    };
});
```

**4.** أضف تنسيقاتها في [`styles.css`](styles.css) تحت `.gc-shop`.

**5.** ارفع `version.php` **فقط لو** أضفت جداول أو صلاحيات، ثم:

```bash
docker compose exec moodle php admin/cli/purge_caches.php
```

لا شيء آخر — الكارت يظهر في الركن وحده.

### ⚠️ بعد أي تعديل في `styles.css`

Moodle يدمج `styles.css` الخاص بكل إضافة داخل **ستايل الثيم المُجمَّع**
(لذلك لا يوجد `$PAGE->requires->css()` في `index.php` أو `play.php` — سيكون
تحميلاً مكرراً). النتيجة أن `purge_caches.php` **وحده لا يكفي**: يغيّر رقم
المراجعة لكنه قد يقدّم النسخة القديمة. أعد البناء صراحةً:

```bash
docker compose exec moodle php admin/cli/build_theme_css.php --themes=nit
```

## ما تحصل عليه اللعبة من `api`

| العضو | الاستخدام |
|-------|-----------|
| `api.stage` | العنصر الذي تبني اللعبة داخله |
| `api.strings` | كل مفاتيح `js_*` باللغة الحالية |
| `api.fmt(n)` | الرقم بالأرقام العربية الهندية في الواجهة العربية |
| `api.random(a,b)` · `api.pick(arr)` · `api.shuffle(arr)` | مساعدات |
| `api.say(text)` | قراءة النص بصوت عالٍ |
| `api.correct()` · `api.wrong()` | تسجيل إجابة + صوت + رسالة عائمة |
| `api.setLives(n)` | إظهار المحاولات كقلوب |
| `api.finish(score)` | إنهاء الجولة، الحفظ، وكارت النهاية |

## قواعد ملزِمة (من مستند التصميم)

مذكورة في `docs/Games Doc/README.md` قسم "قواعد كل لعبة". أهمها في الكود:

- **الغلط مش عقاب** — لا رسالة قاسية ولا نسبة مئوية. في `math-race` انتهاء
  الوقت يعيد المسألة للطابور ولا يُنقص شيئاً.
- **محاولات غير محدودة** — "العب تاني" حاضر دائماً في كارت النهاية.
- **صوت مع كل نص** — كل سؤال وكل قاعدة تُقرأ عبر `api.say()`.
- **أزرار كبيرة** — لمس الأصابع الصغيرة، لذلك `.gc-answer` و `.gc-steer`
  بارتفاعات كبيرة.

## الشارات

الشرط يُقيَّم **على الخادم** في `progress::submit()`، لا في المتصفح.
المفاتيح المتاحة: `streak` (أطول سلسلة صح)، `correct` (عدد الصح)،
`maxwrong` (أقصى غلط مسموح). تُمنح الشارة مرة واحدة مدى الحياة.

## نقطة الدخول للطفل

قصاصة الـHTML block في
[`theme/nit/blocks/games_corner_block.html`](../../theme/nit/blocks/games_corner_block.html)
تُلصق في بلوك نصّي على الصفحة الرئيسية وتوجّه إلى `/local/games/index.php`.
مستند التصميم يطلب أيضاً لينك ثابت في النافبار — يُضاف عبر إعداد
`custommenuitems` بدون أي تعديل في الكود.
