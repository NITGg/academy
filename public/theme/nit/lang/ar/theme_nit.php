<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Arabic language strings for theme_nit.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT';
$string['choosereadme'] = 'NIT هو أساس قالب مبني على Boost لإطار عمل NIT LMS. يوفّر هذا الإصدار (M2) هيكل القالب ومسار بناء الأصول؛ ويأتي نظام التصميم والهوية البصرية في مراحل لاحقة.';
$string['configtitle'] = 'إعدادات NIT';
$string['frontpagecachettl'] = 'مدة تخزين الصفحة الرئيسية مؤقتًا';
$string['frontpagecachettl_desc'] = 'المدة التي تحتفظ فيها الصفحة الرئيسية ببطاقات المقررات وعدّادات الموقع في الذاكرة المؤقتة قبل إعادة حسابها من قاعدة البيانات. القيم الأعلى تقلّل الضغط على قاعدة البيانات في أكثر الصفحات زيارةً، لكنها تجعل الأرقام أقدم قليلًا. اضبطها على 0 لتعطيل التخزين المؤقت (إعادة الحساب في كل طلب).';
$string['foundation'] = 'الأساس';
$string['gallery'] = 'نظام تصميم NIT — معرض المكوّنات';
$string['foundation_desc'] = 'هذا إصدار الأساس (M2): قالب فرعي خفيف من Boost مع مسار بناء SCSS وJavaScript جاهز. تأتي عناصر الهوية والمكوّنات في مراحل لاحقة.';

// Colour palette (edited on the gallery page).
$string['colours'] = 'لوحة الألوان';
$string['colours_desc'] = 'حرّر لوحة ألوان الموقع من صفحة معرض نظام التصميم:';
$string['coloureditor'] = 'لوحة الألوان';
$string['coloureditor_desc'] = 'الألوان التي يُبنى منها الموقع بالكامل. يُنشر كل لون كخاصية CSS مخصّصة (<code>--nit-primary</code>، <code>--nit-navbaraccent</code>، …)، فتقرأ المكوّنات — بما فيها شريط التنقّل — لونها من هنا. اختر لونًا واحفظ لإعادة تلوين الموقع.';
$string['colourssaved'] = 'تم حفظ لوحة الألوان. أُعيد بناء CSS الخاص بالقالب.';
$string['coloursreset'] = 'أُعيدت لوحة الألوان إلى القيم الافتراضية.';
$string['savecolours'] = 'حفظ الألوان';
$string['resetcolours'] = 'إعادة إلى الافتراضي';

// Brand Colors palette (the new semantic layer — edited on the gallery page).
$string['brandcolours_desc'] = 'الألوان الدلالية التي يُبنى منها الموقع بالكامل. يُنشر كل دور كخاصية CSS مخصّصة (<code>--nit-brand-primary</code>، <code>--nit-brand-surface</code>، …) تعود افتراضيًّا إلى <strong>المجموعة 1</strong>. يمكن لأي مكوّن استخدام مجموعة أخرى بإضافة صنفها (<code>.nit-brand-2</code>، <code>.nit-brand-3</code>) — نفس أسماء المتغيّرات، بقيم تلك المجموعة. اختر لونًا واحفظ لإعادة تلوين الموقع.';
$string['brandcolourssaved'] = 'تم حفظ ألوان الهوية. أُعيد بناء CSS الخاص بالقالب.';
$string['brandcoloursreset'] = 'أُعيدت ألوان الهوية إلى القيم الافتراضية.';
$string['savebrandcolours'] = 'حفظ ألوان الهوية';
$string['resetbrandcolours'] = 'إعادة إلى الافتراضي';
$string['brandgroupswitch'] = 'المجموعة قيد التحرير';
$string['navbarshape_usage'] = 'اختر ما تشاء أو لا شيء — تُرسم العلامات المختارة معًا';
$string['navbarsize'] = 'الحجم بالبكسل';
$string['navbarweight'] = 'ثِخَن الخط';
$string['navbarglass'] = 'شفافية الخلفية';
$string['navbarglass_usage'] = 'السماح بظهور الصفحة من خلف الشريط، وبأي درجة';
$string['navbarglass_on'] = 'تفعيل الشفافية';
$string['navbarglass_degree'] = 'نسبة الشفافية';
$string['navbarscroll'] = 'النمط بعد التمرير';
$string['navbarscroll_usage'] = 'يأخذ الشريط نمط هذه المجموعة بمجرّد أن تبدأ الصفحة في التمرير';
$string['navbarscroll_same'] = 'نفس هذه المجموعة (بلا تغيير)';

// The Brand section's Outline button block.
$string['btnoutlinefill'] = 'تعبئة الخلفية';
$string['btnoutlinefill_usage'] = 'عند الإيقاف تكون شفافة، فيأخذ الزر لون ما يقع فوقه';

// Design-system gallery tabs.
$string['tab_brandcolours'] = 'ألوان الهوية';

// Category styles + site styles ("Change style" tab).
$string['tab_changestyle'] = 'تغيير النمط';
$string['tab_categorystyles'] = 'أنماط التصنيفات';

// Category styles: the brand group and the navbar logo each main category uses,
// once for light mode and once for dark mode.
$string['categorystyles_desc'] = 'اضبط هوية كل تصنيف رئيسي مرّتين — مرّة للوضع الفاتح ومرّة للوضع الداكن — لأن هذا ما ينتقل بينه زر الوضع الفاتح/الداكن في شريط التنقّل. يحدّد <strong>النمط</strong> مجموعة ألوان الهوية التي تُعرض بها صفحات التصنيف (اضبط المجموعات نفسها في تبويب ألوان الهوية)، و<strong>الشعار</strong> هو العلامة التي تظهر في شريط التنقّل في تلك الصفحات. يرث كل ما تحت التصنيف الرئيسي هويّته: تصنيفاته الفرعية وكل مقرّراته. اترك النمط على <strong>افتراضي الموقع</strong> أو اترك الشعار فارغًا لاستخدام ما يخصّ الموقع في ذلك الوضع.';
$string['categorystyles_col_category'] = 'التصنيف';
$string['categorystyles_col_group'] = 'مجموعة الهوية';
$string['categorystyles_col_stylefor'] = 'النمط — {$a}';
$string['categorystyles_col_logofor'] = 'الشعار — {$a}';
$string['categorystyles_col_logolight'] = 'الشعار — الوضع الفاتح';
$string['categorystyles_col_logodark'] = 'الشعار — الوضع الداكن';
$string['categorystyles_sitedefault'] = 'افتراضي الموقع';
$string['categorystyles_nologo'] = 'شعار الموقع';
$string['categorystyles_removelogo'] = 'إزالة';
$string['categorystyles_col_image'] = 'الصورة';
$string['categorystyles_noimage'] = 'لا توجد صورة';
$string['categorystyles_editimage'] = 'تعيين صورة';
$string['categorystyles_none'] = 'لا توجد تصنيفات.';
$string['categorylogouploaderror'] = 'تعذّر رفع {$a}. حاول مرّة أخرى.';
$string['categorylogoinvalidtype'] = 'يجب أن يكون {$a} صورة بصيغة PNG أو JPG أو WebP أو GIF أو SVG.';
$string['categorylogotoomany'] = 'لم يُحفظ {$a} من ملفات الشعارات: هذا الخادم يقبل عددًا محدودًا من الملفات المرفوعة في الطلب الواحد. ارفع شعارات بضعة تصنيفات في كل مرّة.';
$string['savecategorygroups'] = 'حفظ أنماط التصنيفات';
$string['categorygroupssaved'] = 'تم حفظ أنماط التصنيفات.';

$string['sitestyles'] = 'أنماط الموقع';
$string['sitestyles_desc'] = 'اختر مجموعة ألوان الهوية التي يستخدمها الموقع في كل وضع عرض. يبدّل زر الوضع الفاتح/الداكن في شريط التنقّل بين الوضعين — وهو لا يحمل لوحة ألوان خاصة به، بل يختار إحدى المجموعات أدناه، فيرى الزائر عند الضغط عليه الألوان نفسها التي ضبطتها في تبويب ألوان الهوية. اجعل للوضعين مجموعتين مختلفتين؛ فإن تطابقتا لم يغيّر الزر شيئًا ولن يظهر. أمّا الصفحات داخل تصنيف له أنماطه الخاصة فتستخدم تلك الأنماط، وينتقل الزر بين زوج ذلك التصنيف.';
$string['sitestyles_col_mode'] = 'وضع العرض';
$string['sitestyles_col_group'] = 'مجموعة الهوية';
$string['savesitestyles'] = 'حفظ أنماط الموقع';
$string['sitestylessaved'] = 'تم حفظ أنماط الموقع.';
// Home page chrome: the navigation bar and the site footer on the Site home.
$string['homechrome'] = 'إطار الصفحة الرئيسية';
$string['homechrome_desc'] = 'اختر ما إذا كان شريط التنقّل وتذييل الموقع يظهران في الصفحة الرئيسية. يؤثّر هذا على الصفحة الرئيسية فقط — وتحتفظ كل الصفحات الأخرى بهما. الجزء الذي يُوقَف يُحذف من الصفحة كليًا فلا يشغل أي مساحة. ويظهر الاثنان دائمًا أثناء تشغيل وضع التحرير، لأن مفتاح وضع التحرير وقائمة المستخدم موجودان في شريط التنقّل.';
$string['homechrome_navbar'] = 'إظهار شريط التنقّل في الصفحة الرئيسية';
$string['homechrome_navbar_desc'] = 'عند الإيقاف: تبدأ الصفحة الرئيسية من أعلى الشاشة بأول كتلة؛ ولا يُرسم فيها شريط التنقّل (الشعار والقائمة والبحث واللغة وتسجيل الدخول).';
$string['homechrome_footer'] = 'إظهار التذييل في الصفحة الرئيسية';
$string['homechrome_footer_desc'] = 'عند الإيقاف: تنتهي الصفحة الرئيسية بآخر كتلة؛ ولا يُرسم فيها شريط تذييل الموقع (بيانات التواصل وأعمدة الروابط وحقوق النشر).';
$string['savehomechrome'] = 'حفظ إطار الصفحة الرئيسية';
$string['homechromesaved'] = 'تم حفظ إطار الصفحة الرئيسية.';
$string['modelight'] = 'الوضع الفاتح';
$string['modedark'] = 'الوضع الداكن';
$string['modeswitchtolight'] = 'التبديل إلى الوضع الفاتح';
$string['modeswitchtodark'] = 'التبديل إلى الوضع الداكن';
$string['tab_colours'] = 'الألوان';
$string['tab_fonts'] = 'الخطوط';
$string['tab_authscreens'] = 'تسجيل الدخول وإنشاء الحساب';
$string['tab_components'] = 'المكوّنات';

// Fonts (edited on the gallery page).
$string['fonts'] = 'الخطوط';
$string['fonts_desc'] = 'ارفع ملف خط (‎.ttf أو ‎.otf) لكل لغة من لغات الموقع. يُطبَّق الخط الإنجليزي عندما يكون الموقع بالإنجليزية (<code>html[lang="en"]</code>) والخط العربي عندما يكون الموقع بالعربية (<code>html[lang="ar"]</code>). الخطوط مستضافة ذاتيًا — لا يُرسَل أي طلب خارجي إطلاقًا. اترك الخانة فارغة للإبقاء على الخط الحالي؛ ويُستخدَم خط النظام المدمج حتى ترفع خطًا.';
$string['fonten'] = 'الخط الإنجليزي';
$string['fontar'] = 'الخط العربي';
$string['fonten_help'] = 'يُطبَّق عندما تكون لغة الموقع الإنجليزية.';
$string['fontar_help'] = 'يُطبَّق عندما تكون لغة الموقع العربية.';
$string['fontactive'] = 'مُفعّل';
$string['fontnone'] = 'يُستخدَم خط النظام الافتراضي.';
$string['fontpreview'] = 'معاينة';
$string['fontsampleen'] = 'The quick brown fox jumps over the lazy dog — 0123456789';
$string['fontsamplear'] = 'أبجد هوّز حطّي كلمن — نصّ تجريبي ٠١٢٣٤٥٦٧٨٩';
$string['savefonts'] = 'حفظ الخطوط';
$string['resetfonts'] = 'إزالة كل الخطوط';
$string['fontssaved'] = 'تم حفظ الخطوط. أُعيد بناء CSS الخاص بالقالب.';
$string['fontsreset'] = 'أُزيلت الخطوط. عاد الموقع إلى خط النظام الافتراضي.';
$string['fontinvalidtype'] = 'تم تجاهل {$a}: تُقبل ملفات الخطوط بصيغة ‎.ttf و‎.otf فقط.';
$string['fontuploaderror'] = 'تعذّر رفع {$a}. من فضلك حاول مرة أخرى.';

// شاشات الحساب (تُحرَّر من صفحة المعرض): الصورة بجانب بطاقتَي تسجيل الدخول
// وإنشاء الحساب، والاقتباس الذي يُرسَم فوقها.
$string['authscreens_desc'] = 'الصورة التي تظهر بجانب بطاقتَي تسجيل الدخول وإنشاء الحساب، والاقتباس الذي يُرسَم فوقها. يُرسَم شعار الموقع هناك أيضًا — وهو نفس الشعار الذي يعرضه شريط التنقّل (إدارة الموقع ← المظهر ← الشعارات)، فلا يحتاج إلى ضبطه مرتين ولا يصبح قديمًا. لا يظهر أي من ذلك تحت عرض 992 بكسل: تُخفى اللوحة على الهواتف والأجهزة اللوحية حيث يملأ النموذج الشاشة.';
$string['authimagelogin'] = 'صورة صفحة تسجيل الدخول';
$string['authimagelogin_desc'] = 'تظهر في شاشة تسجيل الدخول — وفي بقية شاشات الحساب (نسيت كلمة المرور، تأكيد البريد) ما لم تُحدَّد صورة لإنشاء الحساب بالأسفل. اترك الحقل فارغًا للإبقاء على الصورة الافتراضية المرفقة مع مودل، والتي تحمل عبارة «صورة مولّدة بالذكاء الاصطناعي».';
$string['authimagesignup'] = 'صورة صفحة إنشاء الحساب';
$string['authimagesignup_desc'] = 'تظهر في شاشة إنشاء الحساب فقط. اترك الحقل فارغًا لاستخدام صورة تسجيل الدخول هناك أيضًا.';
$string['authimageactive'] = 'قيد الاستخدام';
$string['authimagenone'] = 'لم تُرفَع أي صورة.';
$string['authimageremove'] = 'إزالة هذه الصورة عند الحفظ';
$string['authimageinvalidtype'] = 'تم تجاهل {$a}: تُقبل الصور بصيغة ‎.jpg و‎.png و‎.webp فقط.';
$string['authimageuploaderror'] = 'تعذّر رفع {$a}. من فضلك حاول مرة أخرى.';
$string['authquote'] = 'الاقتباس';
$string['authquote_desc'] = 'يُرسَم داخل بطاقة أسفل الصورة. اكتبه بكل لغة من لغات الموقع — فالمتعلّم الذي يقرأ الموقع بالعربية لا ينبغي أن يُعرَض له نصّ إنجليزي هنا. يمكن ترك أي من اللغتين فارغة؛ وتُستخدَم اللغة المكتوبة في الحالتين. واترك الاثنتين فارغتين فلا تُرسَم البطاقة أصلًا. اكتب علامات التنصيص التي تريدها — فلا يُضاف شيء نيابةً عنك.';
$string['authquotetext'] = 'نص الاقتباس';
$string['authquoteauthor'] = 'نسبة الاقتباس';
$string['authquoteauthorplaceholder'] = 'براين هربرت · قائد تربوي';
$string['saveauthscreens'] = 'حفظ شاشات الدخول والتسجيل';
$string['authscreenssaved'] = 'تم حفظ شاشتَي تسجيل الدخول وإنشاء الحساب. أُعيد بناء CSS الخاص بالقالب.';

// Sign-up page.
$string['alreadyhaveaccount'] = 'لديك حساب بالفعل؟';
$string['logintoaccount'] = 'تسجيل الدخول';

// Block region.
$string['region-side-pre'] = 'يمين';

// Front page full-width block regions.
$string['region-fullwidth-top'] = 'بعرض الصفحة (أعلى)';
$string['region-above-content'] = 'أعلى المحتوى';
$string['region-below-content'] = 'أسفل المحتوى';
$string['region-fullwidth-bottom'] = 'بعرض الصفحة (أسفل)';

// Privacy.
$string['privacy:metadata'] = 'لا يخزّن قالب NIT أي بيانات شخصية.';

// صفحة تفاصيل الكورس (theme_nit\output\format_topics_renderer).
$string['acad_browse'] = 'تصفّح';
$string['acad_skills_tab'] = 'المهارات';
$string['acad_requirements'] = 'المتطلبات';
$string['acad_modules'] = 'الوحدات';
$string['acad_plusmore'] = '+{$a} آخرين';
$string['acad_enrol'] = 'التحق الآن';
$string['acad_buynow'] = 'اشترِ الآن';
$string['acad_free'] = 'مجاني';
$string['acad_ataglance'] = 'نظرة سريعة';
$string['acad_nmodules'] = '{$a} وحدات';
$string['acad_level'] = 'المستوى';
$string['acad_duration'] = 'المدة';
$string['acad_nhours'] = '{$a} ساعة';
$string['acad_assessments'] = 'التقييمات';
$string['acad_nassessments'] = '{$a} تقييمات';
$string['acad_language'] = 'اللغة';
$string['acad_certificate'] = 'الشهادة';
$string['acad_certificate_sub'] = 'شهادة قابلة للمشاركة';
$string['acad_learn'] = 'ماذا ستتعلّم';
$string['acad_skills'] = 'المهارات التي ستكتسبها';
$string['acad_audience'] = 'لمن هذا الكورس';
$string['acad_prerequisites'] = 'المتطلبات المسبقة';
$string['acad_about_h'] = 'عن هذا الكورس';
$string['acad_nmodulesin'] = 'يحتوي هذا الكورس على {$a} وحدات';
$string['acad_modulen'] = 'الوحدة {$a}';
$string['acad_nitems'] = '{$a} عناصر';
$string['acad_moduledetails'] = 'تفاصيل الوحدة';
$string['acad_included'] = 'ما الذي تتضمّنه';
$string['acad_videolength'] = 'مدة الفيديو';
$string['acad_instructors'] = 'المدرّبون';
$string['acad_instructorrole'] = 'مدرّب';
$string['acad_offeredby'] = 'مقدَّم من';
// صيغ المفرد للأعداد.
$string['acad_nmodule'] = 'وحدة واحدة';
$string['acad_nhour'] = 'ساعة واحدة';
$string['acad_nassessment'] = 'تقييم واحد';
$string['acad_nitem'] = 'عنصر واحد';
$string['acad_1modulein'] = 'يحتوي هذا الكورس على وحدة واحدة';

// صفحة تفاصيل المقرر — تسميات الحقائق في الترويسة ومجموعات "ماذا ستتعلّم".
$string['acad_instructorlabel'] = 'المدرّب';
$string['acad_enrolledlabel'] = 'الملتحقون';
$string['acad_startlabel'] = 'يبدأ';
$string['acad_ilos'] = 'النتائج التعليمية المرجوة';
$string['acad_bytheend'] = 'بنهاية هذا البرنامج التدريبي ستتمكّن من';

// التسجيل: مؤشر قوة كلمة المرور وزر إظهار/إخفاء كلمة المرور.
$string['passwordstrength'] = 'قوة كلمة المرور';
$string['passwordstrengthweak'] = 'كلمة مرور ضعيفة';
$string['passwordstrengthfair'] = 'كلمة مرور مقبولة';
$string['passwordstrengthgood'] = 'كلمة مرور جيدة';
$string['passwordstrengthstrong'] = 'كلمة مرور قوية';
$string['showpassword'] = 'إظهار كلمة المرور';
$string['hidepassword'] = 'إخفاء كلمة المرور';

// AC-4.1.1 - زر الإرسال ينتظر اكتمال النموذج.
$string['gatehint'] = 'يرجى إكمال جميع الحقول المطلوبة أولًا.';

// نصوص شاشة التسجيل (§4.1). العنوان والسطر المساند نصّ الشاشة نفسها،
// أما تسمية المزوّد فمشتركة مع شاشة الدخول.
$string['createaccount'] = 'أنشئ حسابك';
$string['createaccountsub'] = 'ابدأ التعلّم مع الأكاديمية.';
$string['or'] = 'أو';
$string['continuewith'] = 'المتابعة باستخدام {$a}';

// نصوص شاشة الدخول (§4.3): العنوان والسطر المساند والرابطان اللذان لا نصّ
// لهما في النواة. تسمية المزوّد مشتركة مع شاشة التسجيل، وتسميات الحقول من النواة.
$string['welcomeback'] = 'أهلاً بعودتك';
$string['welcomebacksub'] = 'سجّل الدخول لمواصلة التعلّم';
$string['loginemail'] = 'البريد الإلكتروني';
$string['loginemailplaceholder'] = 'name@example.com';
$string['forgotyourpassword'] = 'هل نسيت كلمة المرور؟';
$string['eitherorlockedbyusername'] = 'مقفل ما دام اسم المستخدم مُدخلًا — ابحث بأحدهما فقط.';
$string['eitherorlockedbyemail'] = 'مقفل ما دام عنوان البريد الإلكتروني مُدخلًا — ابحث بأحدهما فقط.';
$string['eitherorclearusername'] = 'امسح اسم المستخدم';
$string['eitherorclearemail'] = 'امسح عنوان البريد الإلكتروني';
$string['noaccount'] = 'ليس لديك حساب؟';
$string['signupnow'] = 'إنشاء حساب';
$string['continueasguest'] = 'المتابعة بصفة ضيف';

// قائمة الترس في الشريط العلوي — المجموعة الثانية، وتضم شاشات الإدارة التي
// تحتاج لولا ذلك ثلاث أو أربع نقرات داخل شجرة إدارة الموقع.
$string['navmanagement'] = 'الإدارة';
$string['navgallery'] = 'معرض التصميم';


// حجم الشعار — يظهر في صفحة "الشعارات" في النواة (المظهر ← الشعارات) أسفل
// حقول الرفع مباشرة، لأنها الصفحة التي يقصدها المسؤول حين يكون حجم الشعار
// الذي رفعه غير مناسب. راجع theme_nit_logo_slots() في lib.php.
$string['logosize'] = 'حجم الشعار';
$string['logosize_desc'] = 'الحجم الذي يُرسم به الشعار أعلاه. <strong>حجم الشعار</strong> هو الإعداد الوحيد الذي تحتاجه أغلب المواقع: يغيّر حجم كل شعارات الموقع دفعةً واحدة مع الحفاظ على التناسب بينها. أما الارتفاعات أسفله فتضبط كل موضع على حدة، وتُضرب في هذه النسبة.';
$string['logoscale'] = 'حجم الشعار';
$string['logoscale_desc'] = 'نسبة مئوية تُطبَّق على كل شعارات الموقع — شريط التنقل، وقائمة الهاتف، والتذييل، وشاشات الدخول. القيمة 100% ترسمها بالارتفاعات الواردة أدناه، و150% تجعلها أكبر بمقدار النصف. المدى المسموح 25–400.';
$string['logoheightnavbar'] = 'ارتفاع الشعار في شريط التنقل';
$string['logoheightnavbar_desc'] = 'ارتفاع الشعار بالبكسل في الشريط العلوي لكل صفحة. يزداد ارتفاع الشريط نفسه عند الحاجة، فالقيمة الكبيرة هنا تجعل الترويسة كلها أطول بدلاً من أن يتجاوز الشعار حدودها.';
$string['logoheightdrawer'] = 'ارتفاع الشعار في قائمة الهاتف';
$string['logoheightdrawer_desc'] = 'ارتفاع الشعار بالبكسل أعلى القائمة المنزلقة، وهي ما يحلّ محلّ روابط شريط التنقل على الهاتف.';
$string['logoheightfooter'] = 'ارتفاع الشعار في التذييل';
$string['logoheightfooter_desc'] = 'أقصى ارتفاع بالبكسل للشعار في تذييل الموقع. الشعار العريض يبلغ عرض عموده قبل أن يبلغ هذا الارتفاع.';
$string['logoheightauthpanel'] = 'ارتفاع الشعار في لوحة الدخول';
$string['logoheightauthpanel_desc'] = 'أقصى ارتفاع بالبكسل للشعار المرسوم فوق الصورة المجاورة لنموذجَي الدخول والتسجيل.';
$string['logoheightauthcard'] = 'ارتفاع الشعار في نموذج الدخول';
$string['logoheightauthcard_desc'] = 'أقصى ارتفاع بالبكسل للشعار داخل بطاقتَي الدخول والتسجيل، أعلى العنوان.';

// Per-mode logos (Appearance → Logos).
$string['logomode'] = 'شعارات الوضع الفاتح والداكن';
$string['logomode_desc'] = 'يتغيّر لون شريط التنقّل مع زر الوضع الفاتح/الداكن، والشعار المرسوم لأحدهما لا يظهر على الآخر — فالعلامة البيضاء تختفي على شريط أبيض. حدّد أدناه الوضع الذي رُسمت له الشعارات أعلاه، ثم ارفع نسخ الوضع الآخر. اترك الإعداد على <em>غير محدّد</em> ولن يُستبدل أي شعار: يستخدم الموقع الشعارات أعلاه في كل مكان تمامًا كما كان.';
$string['logosfor'] = 'الشعارات أعلاه مرسومة لـ';
$string['logosfor_desc'] = 'وضع العرض الذي يناسب الشعار والشعار المختصر وأيقونة الموقع أعلاه. تستخدم الصفحات في الوضع الآخر الملفات المرفوعة أدناه — مع الرجوع إلى ما أعلاه لأي خانة تُترك فارغة.';
$string['logosfor_unset'] = 'غير محدّد — لا تستبدل أبدًا';
$string['logosfor_dark'] = 'الوضع الداكن (علامة فاتحة على شريط داكن)';
$string['logosfor_light'] = 'الوضع الفاتح (علامة داكنة على شريط فاتح)';
$string['altlogo'] = 'شعار الوضع الآخر';
$string['altlogo_desc'] = 'الشعار الكامل، مرسومًا للوضع الذي لا تناسبه الشعارات أعلاه. اتركه فارغًا لاستخدام الشعار أعلاه في الوضعين.';
$string['altlogocompact'] = 'الشعار المختصر للوضع الآخر';
$string['altlogocompact_desc'] = 'العلامة التي تظهر في شريط التنقّل، وهي الأهم لأنها الشعار الظاهر في كل صفحة. اتركه فارغًا لاستخدام الشعار المختصر أعلاه في الوضعين.';
$string['altfavicon'] = 'أيقونة الموقع للوضع الآخر';
$string['altfavicon_desc'] = 'أيقونة تبويب المتصفح. اتركها فارغة لاستخدام الأيقونة أعلاه في الوضعين.';
