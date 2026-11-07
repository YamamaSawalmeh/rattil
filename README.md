# 📚 رتّل - منصة إدارة حلقات القرآن الكريم

> منصة حديثة متخصصة في إدارة وتنظيم حلقات تحفيظ القرآن الكريم

## 🎯 نظرة عامة

**رتّل** هي منصة ويب تقليدية (PHP/MySQL) مصممة لتسهيل إدارة حلقات تحفيظ القرآن الكريم. تجمع بين سهولة الاستخدام والقوة الفنية، وتوفر حلاً شاملاً لجميع أطراف المعنية: المدراء والمعلمين والطلاب.

## 🏗️ البنية التقنية

### Frontend
- **HTML5**: Semantic markup مع دعم RTL
- **CSS3**: نظام تصميم متقدم مع CSS variables
- **Vanilla JavaScript**: ES6+ بدون frameworks
- **Responsive**: Mobile-first design على جميع الأجهزة

### Backend
- **PHP 8.x**: OOP مع patterns عملية
- **PDO**: Database access آمن مع prepared statements
- **Session-based Auth**: تحقق آمن من المستخدمين
- **CSRF Protection**: حماية من attacks

### Database
- **MySQL 5.7+**: UTF8MB4 collation
- **10 Tables**: Fully normalized schema
- **Foreign Keys**: مع ON DELETE CASCADE
- **Proper Indexing**: على الأعمدة المهمة

## 📁 هيكل المشروع

```
rattil/
├── index.php                    # الصفحة الرئيسية
├── login.php                    # صفحة تسجيل الدخول
├── register.php                 # صفحة التسجيل
├── logout.php                   # تسجيل الخروج
│
├── admin/
│   ├── dashboard.php            # لوحة تحكم المدير
│   ├── students.php             # إدارة الطلاب
│   ├── teachers.php             # إدارة المعلمين
│   ├── halaqat.php              # إدارة الحلقات
│   ├── reports.php              # التقارير
│   └── settings.php             # الإعدادات
│
├── teacher/
│   ├── dashboard.php            # لوحة المعلم
│   ├── halaqat.php              # إدارة الحلقات
│   ├── students.php             # قائمة الطلاب
│   ├── attendance.php           # تسجيل الحضور
│   ├── recitations.php          # التسميعات
│   ├── reports.php              # التقارير
│   └── profile.php              # الملف الشخصي
│
├── student/
│   ├── dashboard.php            # لوحة الطالب
│   ├── progress.php             # تقدم الطالب
│   ├── achievements.php         # الإنجازات
│   ├── quran.php                # متتبع القرآن
│   ├── schedule.php             # الجدول الأسبوعي
│   └── profile.php              # الملف الشخصي
│
├── api/
│   ├── login.php                # API endpoint للدخول
│   ├── register.php             # API endpoint للتسجيل
│   ├── logout.php               # API endpoint للخروج
│   └── [other endpoints]        # نقاط نهاية إضافية
│
├── includes/
│   ├── config.php               # الإعدادات والثوابت
│   ├── db.php                   # فئة قاعدة البيانات
│   ├── auth.php                 # نظام المصادقة
│   ├── functions.php            # دوال الفائدة
│   ├── header.php               # قالب الرأس
│   └── footer.php               # قالب التذييل
│
├── database/
│   └── schema.sql               # خطة قاعدة البيانات
│
├── assets/
│   ├── css/
│   │   ├── style.css            # الأنماط الرئيسية
│   │   ├── responsive.css       # الأنماط المتجاوبة
│   │   └── dashboard.css        # أنماط لوحة التحكم
│   │
│   └── js/
│       ├── app.js               # التطبيق الرئيسي
│       ├── utils.js             # دوال الفائدة
│       ├── dashboard.js         # منطق لوحة التحكم
│       └── charts.js            # مساعدات الرسوم البيانية
│
└── README.md                    # هذا الملف
```

## 🚀 البدء السريع

### المتطلبات
- PHP 8.0+
- MySQL 5.7+
- Apache/XAMPP
- Modern Web Browser

### التثبيت

1. **استنساخ المستودع**
```bash
git clone <repo-url>
cd rattil
```

2. **إنشاء قاعدة البيانات**
```bash
mysql -u root -p < database/schema.sql
```

3. **إعدادات الاتصال**
عدّل `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'rattil');
```

4. **تشغيل التطبيق**
```
http://localhost/rattil/
```

### بيانات تجريبية
```
مدير:      admin@rattil.com / password123
معلم:      abdullah@rattil.com / password123
طالب:      ahmed@rattil.com / password123
```

## 🎨 نظام التصميم

### الألوان الأساسية
- **Green Dark**: #1B4332 (الأساسي)
- **Gold**: #D4A373 (التمييز)
- **Cream**: #F5F0E8 (الخلفية)
- **White**: #FFFFFF (البيضاء)

### الخطوط
- **Amiri**: للعناوين والواجهة الرئيسية
- **Lora**: لنصوص المحتوى

### نقاط الاستجابة
- 576px: هواتف
- 768px: أجهزة لوحية
- 1024px: أجهزة محمولة أفقية
- 1440px: سطح مكتب كبير

## 🔐 الأمان

### ميزات الأمان
- ✅ Password hashing مع BCRYPT
- ✅ Prepared statements ضد SQL injection
- ✅ CSRF token protection
- ✅ Session management مع timeout
- ✅ Role-based access control
- ✅ Input sanitization

## 📊 قاعدة البيانات

### الجداول الرئيسية
1. **users**: المستخدمون الأساسيون
2. **students**: تفاصيل الطلاب الموسعة
3. **teachers**: تفاصيل المعلمين الموسعة
4. **halaqat**: الحلقات/الدوائس
5. **halaqa_students**: ارتباط الطلاب بالحلقات
6. **attendance**: سجل الحضور
7. **recitations**: سجل التسميعات
8. **achievements**: الإنجازات والجوائز
9. **notifications**: الإشعارات
10. **quran_progress**: تقدم حفظ القرآن

## 👥 الأدوار والأذونات

### المدير (Admin)
- ✅ إدارة المستخدمين (الطلاب والمعلمين)
- ✅ إدارة الحلقات
- ✅ عرض التقارير الشاملة
- ✅ إعدادات النظام
- ✅ لوحة تحكم شاملة

### المعلم (Teacher)
- ✅ إدارة حلقاته الخاصة
- ✅ تسجيل الحضور
- ✅ تقييم التسميعات
- ✅ متابعة تقدم الطلاب
- ✅ تقارير أداء الطلاب

### الطالب (Student)
- ✅ متابعة تقدمه الشخصي
- ✅ عرض جدول الحلقات
- ✅ متتبع القرآن التفاعلي
- ✅ عرض الإنجازات
- ✅ الملف الشخصي

## 📝 الصفحات المنفذة

### الصفحات الأساسية ✅
- [x] الصفحة الرئيسية (index.php)
- [x] تسجيل الدخول (login.php)
- [x] التسجيل (register.php)
- [x] تسجيل الخروج (logout.php)

### صفحات المدير ✅
- [x] لوحة التحكم (admin/dashboard.php)
- [x] إدارة الطلاب (admin/students.php)
- [x] إدارة المعلمين (admin/teachers.php)
- [x] إدارة الحلقات (admin/halaqat.php)
- [ ] التقارير (admin/reports.php)
- [ ] الإعدادات (admin/settings.php)

### صفحات المعلم ✅
- [x] لوحة التحكم (teacher/dashboard.php)
- [x] إدارة الحلقات (teacher/halaqat.php)
- [x] قائمة الطلاب (teacher/students.php)
- [x] تسجيل الحضور (teacher/attendance.php)
- [x] التسميعات (teacher/recitations.php)
- [ ] التقارير (teacher/reports.php)
- [ ] الملف الشخصي (teacher/profile.php)

### صفحات الطالب ✅
- [x] لوحة التحكم (student/dashboard.php)
- [x] تقدمي (student/progress.php)
- [x] الإنجازات (student/achievements.php)
- [x] متتبع القرآن (student/quran.php)
- [x] الجدول الأسبوعي (student/schedule.php)
- [x] الملف الشخصي (student/profile.php)

### نقاط نهاية API ✅
- [x] api/login.php
- [x] api/register.php
- [x] api/logout.php

## 🔌 نقاط نهاية API

### المصادقة
```php
POST /api/login.php
POST /api/register.php
POST /api/logout.php
```

### إدارة الطلاب (Admin)
```php
GET /api/admin/students/list.php
POST /api/admin/students/create.php
PUT /api/admin/students/update.php
DELETE /api/admin/students/delete.php
```

*نقاط نهاية إضافية قيد الإنشاء*

## 🛠️ الأدوات المستخدمة

- **Version Control**: Git
- **Package Manager**: -
- **Build Tools**: -
- **Testing**: Manual + PHPUnit (مخطط)
- **Deployment**: XAMPP/Apache

## 📈 الميزات المخطط إضافتها

- [ ] نظام إشعارات البريد الإلكتروني
- [ ] تطبيق جوال (Mobile App)
- [ ] نظام التقارير المتقدمة
- [ ] دعم التصدير (PDF/Excel)
- [ ] نظام التقييم الآلي
- [ ] Analytics متقدمة
- [ ] Dark Mode
- [ ] Multi-language support

## 📚 التوثيق الإضافية

### إنشاء مستخدم جديد
```php
$result = registerUser(
    'email@example.com',
    'password123',
    'Full Name',
    'student' // or 'teacher'
);
```

### التحقق من الأذونات
```php
if (hasPermission('manage_students')) {
    // السماح بالوصول
}
```

### إضافة إشعار
```php
createNotification(
    $user_id,
    'عنوان الإشعار',
    'محتوى الإشعار',
    'info'
);
```

## 🐛 معالجة الأخطاء

تم تنفيذ معالجة شاملة للأخطاء:
- Error logging في database
- User-friendly error messages
- Graceful error handling

## 🤝 المساهمة

نرحب بالمساهمات! يرجى:
1. Fork المستودع
2. إنشاء فرع جديد
3. Commit التغييرات
4. Push إلى الفرع
5. فتح Pull Request

## 📄 الترخيص

هذا المشروع مرخص تحت MIT License

## 📧 التواصل

للأسئلة والاقتراحات، يرجى التواصل عبر:
- البريد: support@rattil.com
- الموقع: www.rattil.com

---

**تم الإنشاء بـ ❤️ لخدمة حفظ كتاب الله الكريم**

## 🌱 حديقة الإنجازات (Achievement Garden)

في مجلد [`achievement-garden/`](achievement-garden/) نموذج تفاعلي (React + TypeScript + Vite) لفكرة تحفيز الطلاب: كل سورة يحفظها الطالب تُزرع شجرة في حديقته. التفاصيل وطريقة التشغيل في ملف README داخل المجلد.

## ⚙️ إعداد قاعدة البيانات

1. استورد ملف SQL من مجلد `database/` إلى MySQL.
2. عدّل بيانات الاتصال في `config/db.php` حسب جهازك أو استضافتك.
