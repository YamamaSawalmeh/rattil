/* RATTIL — main.js | الدوال المشتركة بين كل الصفحات */

/* -------------------------------------------------------
   Toast — رسائل الإشعار السريعة
------------------------------------------------------- */
function showToast(msg, type = 'ok') {
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.className = 'toast';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.background = type === 'err' ? '#dc2626' : type === 'warn' ? '#d97706' : '#1a1a1a';
    toast.classList.add('show');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.remove('show'), 3000);
}

/* -------------------------------------------------------
   Sidebar — Mobile Toggle
------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', () => {
    const sidebar  = document.getElementById('sidebar');
    const menuBtn  = document.getElementById('menuBtn');
    if (!sidebar || !menuBtn) return;

    if (window.innerWidth <= 860) {
        menuBtn.style.display = 'block';
    }

    menuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });

    // إغلاق عند النقر خارج الـ sidebar في الموبايل
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 860 &&
            !e.target.closest('#sidebar') &&
            !e.target.closest('#menuBtn')) {
            sidebar.classList.remove('open');
        }
    });

    window.addEventListener('resize', () => {
        menuBtn.style.display = window.innerWidth <= 860 ? 'block' : 'none';
    });
});

/* -------------------------------------------------------
   Modal — فتح وإغلاق
------------------------------------------------------- */
function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('open');
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('open');
}

// إغلاق Modal عند النقر خارجه
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// إغلاق بـ Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
});

/* -------------------------------------------------------
   Notifications — تحديث عداد الإشعارات
------------------------------------------------------- */
async function updateNotifBadge() {
    try {
        const r = await fetch('/rattil/api/notifications.php?action=count');
        const d = await r.json();
        let badge = document.getElementById('notifBadge');
        if (d.success && d.count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.id = 'notifBadge';
                badge.className = 'notif-badge';
                const btn = document.querySelector('.notif-btn');
                if (btn) btn.appendChild(badge);
            }
            badge.textContent = d.count;
        } else if (badge) {
            badge.remove();
        }
    } catch (e) {}
}

async function markAllNotifRead() {
    try {
        await fetch('/rattil/api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_all_read' })
        });
        document.querySelectorAll('.ndot').forEach(d => d.classList.add('read'));
        const badge = document.getElementById('notifBadge');
        if (badge) badge.remove();
        showToast('✓ تم تحديد الكل كمقروء');
    } catch (e) {
        showToast('خطأ في الاتصال', 'err');
    }
}

// Polling كل 20 ثانية
if (document.querySelector('.notif-btn')) {
    setInterval(updateNotifBadge, 20000);
}

/* -------------------------------------------------------
   Form — Loading State
------------------------------------------------------- */
function setFormLoading(formId, loading, btnText = 'جارٍ الإرسال...') {
    const form = document.getElementById(formId);
    if (!form) return;
    const btn     = form.querySelector('[type="submit"]');
    const spinner = form.querySelector('.spinner');
    const text    = form.querySelector('#btnText');
    if (!btn) return;
    btn.disabled = loading;
    if (spinner) spinner.style.display = loading ? 'block' : 'none';
    if (text)    text.textContent = loading ? btnText : text.dataset.original || 'إرسال';
    if (!text && loading) btn.textContent = btnText;
}

/* -------------------------------------------------------
   Confirm — تأكيد قبل الحذف
------------------------------------------------------- */
function confirmAction(msg, callback) {
    if (window.confirm(msg)) callback();
}

/* -------------------------------------------------------
   Format Date — تنسيق التاريخ
------------------------------------------------------- */
function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' });
}

function timeAgo(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
    if (diff < 60)   return 'منذ لحظات';
    if (diff < 3600) return `منذ ${Math.floor(diff/60)} دقيقة`;
    if (diff < 86400)return `منذ ${Math.floor(diff/3600)} ساعة`;
    return `منذ ${Math.floor(diff/86400)} يوم`;
}

/* -------------------------------------------------------
   Progress Bar — تحديث شريط التقدم
------------------------------------------------------- */
function updateProgress(fillId, percentage) {
    const el = document.getElementById(fillId);
    if (el) el.style.width = Math.min(100, percentage) + '%';
}

/* -------------------------------------------------------
   Copy to Clipboard
------------------------------------------------------- */
function copyText(text) {
    navigator.clipboard.writeText(text)
        .then(() => showToast('✓ تم النسخ'))
        .catch(() => showToast('فشل النسخ', 'err'));
}

/* -------------------------------------------------------
   Scroll Animation
------------------------------------------------------- */
const scrollObserver = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
            scrollObserver.unobserve(e.target);
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.animate-on-scroll').forEach(el => scrollObserver.observe(el));

/* -------------------------------------------------------
   Table Search — بحث في جداول البيانات
------------------------------------------------------- */
function filterTable(inputId, tableId) {
    const query = document.getElementById(inputId)?.value.toLowerCase().trim();
    const rows  = document.querySelectorAll(`#${tableId} tbody tr`);
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
    });
}

/* -------------------------------------------------------
   Grade Color — لون درجة التسميع
------------------------------------------------------- */
function gradeClass(grade) {
    const map = {
        'ممتاز':         'grade-ممتاز',
        'جيد جداً':      'grade-جيد-جداً',
        'جيد':           'grade-جيد',
        'مقبول':         'grade-مقبول',
        'يحتاج مراجعة': 'grade-يحتاج-مراجعة',
    };
    return map[grade] || '';
}

/* -------------------------------------------------------
   Arabic Numerals
------------------------------------------------------- */
function toArabicNum(n) {
    return String(n).replace(/[0-9]/g, d => '٠١٢٣٤٥٦٧٨٩'[d]);
}
