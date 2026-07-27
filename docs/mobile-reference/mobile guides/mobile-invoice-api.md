# دليل مطوري التطبيقات المحمولة - واجهات برمجة تطبيقات الفواتير

## نظرة عامة

يشرح هذا الدليل كيفية استخدام واجهات برمجة التطبيقات (APIs) الخلفية لإنشاء صفحة الفواتير في التطبيق المحمول. تعتمد هذه الواجهات على نظام Moodle External API وتدعم المصادقة عبر خدمة Moodle الرسمية للتطبيقات المحمولة.

## نقاط نهاية API الرئيسية

### 1. الحصول على سجل المدفوعات (Payment History)

**اسم الوظيفة:** `local_payments_get_payment_history`

**الوصف:** الحصول على قائمة معاملات الدفع للمستخدم الحالي مع الترقيم.

**النوع:** قراءة (read)

**المعلمات:**
- `page` (int, اختياري): رقم الصفحة، الافتراضي 0
- `perpage` (int, اختياري): عدد العناصر في كل صفحة، الافتراضي 20

**مثال على الطلب:**
```json
{
  "page": 0,
  "perpage": 20
}
```

**مثال على الاستجابة:**
```json
[
  {
    "transaction_id": 123,
    "order_id": "ORD-2024-001",
    "courseid": 456,
    "course_name": "دورة البرمجة المتقدمة",
    "amount": 150.00,
    "original_amount": 200.00,
    "currency": "SAR",
    "status": "completed",
    "provider": "Stripe",
    "payment_method": "credit_card",
    "invoice_number": "INV-2024-00123",
    "timecreated": 1704067200
  },
  {
    "transaction_id": 124,
    "order_id": "ORD-2024-002",
    "courseid": 789,
    "course_name": "دورة تصميم واجهات المستخدم",
    "amount": 120.00,
    "original_amount": 120.00,
    "currency": "SAR",
    "status": "completed",
    "provider": "PayPal",
    "payment_method": "paypal",
    "invoice_number": "INV-2024-00124",
    "timecreated": 1704153600
  }
]
```

**حالات الحالة (status):**
- `completed`: الدفع مكتمل
- `pending`: الدفع معلق
- `failed`: فشل الدفع
- `refunded`: تم الاسترداد

---

### 2. الحصول على تفاصيل الفاتورة (Invoice Details)

**اسم الوظيفة:** `local_payments_get_invoice`

**الوصف:** الحصول على تفاصيل الفاتورة الكاملة لمعاملة محددة.

**النوع:** قراءة (read)

**المعلمات:**
- `transaction_id` (int, مطلوب): معرف المعاملة

**مثال على الطلب:**
```json
{
  "transaction_id": 123
}
```

**مثال على الاستجابة:**
```json
{
  "invoice_number": "INV-2024-00123",
  "amount": 150.00,
  "original_amount": 200.00,
  "currency": "SAR",
  "status": "issued",
  "order_id": "ORD-2024-001",
  "course_name": "دورة البرمجة المتقدمة",
  "payment_date": 1704067200,
  "invoice_date": 1704067200
}
```

**حالات الفاتورة (status):**
- `issued`: الفاتورة صادرة
- `void`: الفاتورة ملغاة
- `draft`: مسودة

---

### 3. الحصول على الدورات المشتراة (Purchased Courses)

**اسم الوظيفة:** `local_payments_get_purchased_courses`

**الوصف:** الحصول على قائمة الدورات التي اشتراها المستخدم الحالي.

**النوع:** قراءة (read)

**المعلمات:** لا توجد معلمات مطلوبة

**مثال على الاستجابة:**
```json
[
  {
    "courseid": 456,
    "course_name": "دورة البرمجة المتقدمة",
    "enrolled": true,
    "access_until": 1706745600
  }
]
```

---

### 4. الحصول على حالة الوصول للدورة (Course Access Status)

**اسم الوظيفة:** `local_payments_get_course_access`

**الوصف:** الحصول على حالة التسجيل والدفع لدورة محددة.

**النوع:** قراءة (read)

**المعلمات:**
- `courseid` (int, مطلوب): معرف الدورة

**مثال على الطلب:**
```json
{
  "courseid": 456
}
```

**مثال على الاستجابة:**
```json
{
  "courseid": 456,
  "enrolled": true,
  "has_paid": true,
  "access_until": 1706745600,
  "can_enroll": false,
  "price": 150.00,
  "currency": "SAR"
}
```

---

## المصادقة

تتطلب جميع واجهات برمجة التطبيقات مصادقة عبر خدمة Moodle الرسمية للتطبيقات المحمولة. يجب استخدام:

1. **رمز الوصول (Access Token):** الحصول عليه من خلال تسجيل دخول المستخدم
2. **رأس المصادقة:** إضافة الرمز في رأس الطلب

**مثال على رأس المصادقة:**
```
Authorization: Bearer YOUR_ACCESS_TOKEN
```

---

## تنفيذ صفحة الفواتير

### هيكل الصفحة المقترح

```
صفحة الفواتير
├── شريط البحث والتصفية
│   ├── البحث برقم الفاتورة
│   ├── تصفية حسب النوع (دورة/حصة/اشتراك)
│   ├── تصفية حسب المبلغ
│   └── تصفية حسب التاريخ
├── قائمة الفواتير
│   ├── عنصر الفاتورة
│   │   ├── رقم الفاتورة
│   │   ├── اسم المادة
│   │   ├── المبلغ
│   │   ├── الحالة
│   │   └── التاريخ
│   └── الترقيم (Pagination)
└── صفحة تفاصيل الفاتورة
    ├── معلومات الفاتورة
    ├── معلومات البائع
    ├── معلومات المشتري
    ├── تفاصيل الدفع
    └── زر تحميل PDF
```

### خطوات التنفيذ

#### 1. تحميل قائمة الفواتير

```javascript
async function loadInvoices(page = 0, perPage = 20) {
  try {
    const response = await fetch(
      'https://your-domain.com/webservice/rest/server.php?' +
      'wstoken=YOUR_TOKEN&' +
      'wsfunction=local_payments_get_payment_history&' +
      'moodlewsrestformat=json&' +
      `page=${page}&perpage=${perPage}`
    );
    
    const invoices = await response.json();
    
    // عرض القائمة في واجهة المستخدم
    displayInvoices(invoices);
    
  } catch (error) {
    console.error('خطأ في تحميل الفواتير:', error);
    showError('فشل تحميل الفواتير');
  }
}
```

#### 2. تحميل تفاصيل فاتورة محددة

```javascript
async function loadInvoiceDetails(transactionId) {
  try {
    const response = await fetch(
      'https://your-domain.com/webservice/rest/server.php?' +
      'wstoken=YOUR_TOKEN&' +
      'wsfunction=local_payments_get_invoice&' +
      'moodlewsrestformat=json&' +
      `transaction_id=${transactionId}`
    );
    
    const invoice = await response.json();
    
    // عرض التفاصيل في واجهة المستخدم
    displayInvoiceDetails(invoice);
    
  } catch (error) {
    console.error('خطأ في تحميل تفاصيل الفاتورة:', error);
    showError('فشل تحميل تفاصيل الفاتورة');
  }
}
```

#### 3. تصفية الفواتير

```javascript
async function filterInvoices(filters) {
  // ملاحظة: التصفية تتم على جانب العميل حالياً
  // يمكن تحميل جميع الفواتير ثم تطبيق الفلاتر
  
  const allInvoices = await loadAllInvoices();
  
  const filtered = allInvoices.filter(invoice => {
    if (filters.invoiceNumber && !invoice.invoice_number.includes(filters.invoiceNumber)) {
      return false;
    }
    if (filters.type && !invoice.course_name.includes(filters.type)) {
      return false;
    }
    if (filters.minAmount && invoice.amount < filters.minAmount) {
      return false;
    }
    if (filters.maxAmount && invoice.amount > filters.maxAmount) {
      return false;
    }
    if (filters.dateFrom && invoice.timecreated < filters.dateFrom) {
      return false;
    }
    if (filters.dateTo && invoice.timecreated > filters.dateTo) {
      return false;
    }
    return true;
  });
  
  displayInvoices(filtered);
}
```

#### 4. تنسيق التواريخ والمبالغ

```javascript
function formatDate(timestamp) {
  const date = new Date(timestamp * 1000);
  return date.toLocaleDateString('ar-SA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
}

function formatAmount(amount, currency) {
  return `${amount.toFixed(2)} ${currency}`;
}

function getStatusLabel(status) {
  const labels = {
    'completed': 'مكتمل',
    'pending': 'معلق',
    'failed': 'فشل',
    'refunded': 'مسترد',
    'issued': 'صادرة',
    'void': 'ملغاة',
    'draft': 'مسودة'
  };
  return labels[status] || status;
}

function getStatusColor(status) {
  const colors = {
    'completed': 'green',
    'issued': 'green',
    'pending': 'orange',
    'failed': 'red',
    'refunded': 'blue',
    'void': 'gray',
    'draft': 'yellow'
  };
  return colors[status] || 'gray';
}
```

---

## معالجة الأخطاء

### أنواع الأخطاء الشائعة

1. **خطأ المصادقة (401):**
   - السبب: رمز الوصول منتهي أو غير صالح
   - الحل: طلب تسجيل دخول جديد من المستخدم

2. **خطأ الصلاحية (403):**
   - السبب: المستخدم ليس لديه صلاحية الوصول
   - الحل: التحقق من صلاحيات المستخدم

3. **خطأ في المعلمات (400):**
   - السبب: معلمات غير صحيحة
   - الحل: التحقق من صحة المعلمات المرسلة

4. **خطأ في الخادم (500):**
   - السبب: خطأ داخلي في الخادم
   - الحل: محاولة الطلب مرة أخرى لاحقاً

### مثال على معالجة الأخطاء

```javascript
async function apiCall(functionName, params = {}) {
  const baseUrl = 'https://your-domain.com/webservice/rest/server.php';
  const token = await getAccessToken();
  
  const url = new URL(baseUrl);
  url.searchParams.append('wstoken', token);
  url.searchParams.append('wsfunction', functionName);
  url.searchParams.append('moodlewsrestformat', 'json');
  
  Object.keys(params).forEach(key => {
    url.searchParams.append(key, params[key]);
  });
  
  try {
    const response = await fetch(url);
    
    if (response.status === 401) {
      // رمز الوصول منتهي
      await refreshToken();
      return apiCall(functionName, params);
    }
    
    if (!response.ok) {
      throw new Error(`خطأ في الخادم: ${response.status}`);
    }
    
    const data = await response.json();
    
    if (data.exception) {
      throw new Error(data.message);
    }
    
    return data;
    
  } catch (error) {
    console.error('خطأ في استدعاء API:', error);
    throw error;
  }
}
```

---

## أمثلة على السيناريوهات

### السيناريو 1: عرض قائمة الفواتير عند تحميل الصفحة

```javascript
useEffect(() => {
  loadInvoices(0, 20);
}, []);
```

### السيناريو 2: النقر على فاتورة لعرض التفاصيل

```javascript
function handleInvoiceClick(invoice) {
  navigation.navigate('InvoiceDetails', {
    transactionId: invoice.transaction_id
  });
}

// في صفحة التفاصيل
useEffect(() => {
  if (route.params.transactionId) {
    loadInvoiceDetails(route.params.transactionId);
  }
}, [route.params.transactionId]);
```

### السيناريو 3: تحميل المزيد من الفواتير (الترقيم)

```javascript
function loadMoreInvoices() {
  const nextPage = currentPage + 1;
  loadInvoices(nextPage, 20).then(newInvoices => {
    setInvoices([...invoices, ...newInvoices]);
    setCurrentPage(nextPage);
  });
}
```

---

## ملاحظات مهمة

1. **الترقيم:** يتم الترقيم على أساس 0 (الصفحة الأولى هي 0)
2. **الترتيب:** يتم ترتيب الفواتير من الأحدث إلى الأقدم
3. **العملة:** يجب عرض العملة كما هي مستلمة من الخادم
4. **التواريخ:** يتم إرسال التواريخ كطوابع زمنية Unix (بالثواني)
5. **الأخطاء:** يجب معالجة جميع الأخطاء بشكل مناسب وعرض رسائل واضحة للمستخدم
6. **الأداء:** يُنصح بتنفيذ التخزين المؤقت (caching) للفواتير لتحسين الأداء
7. **التحديث:** يمكن تنفيذ تحديث تلقائي للقائمة عند سحب المستخدم للأسفل (pull-to-refresh)

---

## الدعم

للحصول على الدعم الفني أو الإبلاغ عن مشاكل، يرجى التواصل مع فريق التطوير.

---

## التحديثات

- **الإصدار 1.0:** الإصدار الأولي من دليل API للفواتير
- **تاريخ الإنشاء:** يوليو 2024
