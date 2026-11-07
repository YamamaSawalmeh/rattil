/* RATTIL — notifications.js | إدارة الإشعارات */

const NotifManager = {
    panel: null,
    list:  null,
    badge: null,
    polling: null,
    panelId: 'notifPanelDropdown',
    eventsAttached: false,

    init() {
        this.badge = document.getElementById('notifBadge');
        this.createPanel();
        this.attachEvents();
        this.poll();
        this.polling = setInterval(() => this.poll(), 20000);
    },

    createPanel() {
        const existing = document.getElementById(this.panelId);
        if (existing) {
            this.panel = existing;
            this.list = existing.querySelector('#npList');
            return;
        }

        const panel = document.createElement('div');
        panel.id = this.panelId;
        panel.innerHTML = `
            <div class="np-header">
                <span>🔔 الإشعارات</span>
                <button type="button" onclick="NotifManager.markAll()" class="np-mark-all">تحديد الكل كمقروء</button>
            </div>
            <div class="np-list" id="npList">
                <div class="np-loading">جارٍ التحميل...</div>
            </div>`;
        panel.style.cssText = `
            position:fixed;top:65px;left:16px;width:320px;max-height:420px;
            background:#fff;border-radius:16px;box-shadow:0 8px 28px rgba(0,0,0,.15);
            border:1px solid #eee;z-index:500;display:none;overflow:hidden;
            flex-direction:column;`;
        document.body.appendChild(panel);
        this.panel = panel;
        this.list  = document.getElementById('npList');

        // CSS للـ panel
        const style = document.createElement('style');
        style.textContent = `
            #notifPanelDropdown .np-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #f0f0f0;font-weight:700;color:var(--p,#1B4332);font-size:.95rem;}
            #notifPanelDropdown .np-mark-all{background:none;border:none;color:var(--gold,#D4AF37);cursor:pointer;font-size:.82rem;font-family:inherit;}
            #notifPanelDropdown .np-list{overflow-y:auto;max-height:360px;}
            #notifPanelDropdown .np-item{display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid #f8f8f8;cursor:pointer;transition:.2s;text-align:right;}
            #notifPanelDropdown .np-item:hover{background:#fafafa;}
            #notifPanelDropdown .np-item.unread{background:rgba(212,175,55,.05);}
            #notifPanelDropdown .np-dot{width:8px;height:8px;border-radius:50%;background:var(--gold,#D4AF37);flex-shrink:0;margin-top:5px;}
            #notifPanelDropdown .np-dot.read{background:#ddd;}
            #notifPanelDropdown .np-title{font-size:.88rem;color:#222;font-weight:700;margin-bottom:2px;}
            #notifPanelDropdown .np-body{font-size:.8rem;color:#666;line-height:1.4;}
            #notifPanelDropdown .np-time{font-size:.74rem;color:#9ca3af;margin-top:3px;}
            #notifPanelDropdown .np-empty{padding:32px;text-align:center;color:#9ca3af;font-size:.9rem;}
            #notifPanelDropdown .np-loading{padding:24px;text-align:center;color:#9ca3af;font-size:.9rem;}`;
        document.head.appendChild(style);
    },

    attachEvents() {
        if (!this.panel || this.eventsAttached) return;
        this.eventsAttached = true;

        document.addEventListener('click', (e) => {
            const trigger = this.getTrigger(e.target);
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                this.toggle();
                return;
            }

            if (!e.target.closest(`#${this.panelId}`)) this.panel.style.display = 'none';
        }, true);
    },

    getTrigger(target) {
        if (!target?.closest) return null;
        const direct = target.closest('.notif-btn, #notifLink, [data-notif-trigger]');
        if (direct) return direct;

        const navLink = target.closest('a.nav-link[href="#"]');
        if (navLink && navLink.textContent.includes('الإشعارات')) return navLink;
        return null;
    },

    toggle(forceOpen = null) {
        if (!this.panel) return;
        const isOpen = this.panel.style.display === 'flex';
        const shouldOpen = forceOpen === null ? !isOpen : Boolean(forceOpen);
        this.panel.style.display = shouldOpen ? 'flex' : 'none';
        if (shouldOpen) this.loadList();
    },

    async poll() {
        try {
            const r = await fetch('/rattil/api/notifications.php?action=count');
            const d = await r.json();
            if (!d.success) return;
            let badge = document.getElementById('notifBadge');
            if (d.count > 0) {
                document.querySelectorAll('.notif-btn .notif-dot').forEach((dot) => dot.remove());
                if (!badge) {
                    badge = document.createElement('span');
                    badge.id = 'notifBadge';
                    badge.className = 'notif-badge';
                    document.querySelector('.notif-btn')?.appendChild(badge);
                }
                badge.textContent = d.count;
                const navBadge = document.querySelector('#notifLink .badge-count');
                if (navBadge) navBadge.textContent = d.count;
            } else if (badge) {
                badge.remove();
                this.clearBadges();
            } else {
                this.clearBadges();
            }
        } catch (e) {}
    },

    async loadList() {
        if (!this.list) return;
        this.list.innerHTML = '<div class="np-loading">جارٍ التحميل...</div>';
        try {
            const r = await fetch('/rattil/api/notifications.php?action=list');
            const d = await r.json();
            if (!d.success || !d.data.length) {
                this.list.innerHTML = '<div class="np-empty">لا توجد إشعارات</div>';
                return;
            }
            this.list.innerHTML = d.data.map(n => `
                <div class="np-item ${n.is_read ? '' : 'unread'}" onclick="NotifManager.markRead(${n.id}, this)">
                    <div class="np-dot ${n.is_read ? 'read' : ''}"></div>
                    <div>
                        <div class="np-title">${this.escape(n.title)}</div>
                        ${n.body ? `<div class="np-body">${this.escape(n.body)}</div>` : ''}
                        <div class="np-time">${this.timeAgo(n.created_at)}</div>
                    </div>
                </div>`).join('');
        } catch (e) {
            this.list.innerHTML = '<div class="np-empty">تعذّر التحميل</div>';
        }
    },

    async markRead(id, el) {
        try {
            await fetch('/rattil/api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', id })
            });
            el?.classList.remove('unread');
            el?.querySelector('.np-dot')?.classList.add('read');
            this.poll();
        } catch (e) {}
    },

    async markAll() {
        try {
            await fetch('/rattil/api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_all_read' })
            });
            document.querySelectorAll('.np-item').forEach(el => {
                el.classList.remove('unread');
                el.querySelector('.np-dot')?.classList.add('read');
            });
            document.querySelectorAll('.ndot').forEach(d => d.classList.add('read'));
            this.clearBadges();
            if (typeof showToast === 'function') showToast('✓ تم تحديد الكل كمقروء');
        } catch (e) {}
    },

    timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)    return 'منذ لحظات';
        if (diff < 3600)  return `منذ ${Math.floor(diff/60)} دقيقة`;
        if (diff < 86400) return `منذ ${Math.floor(diff/3600)} ساعة`;
        return `منذ ${Math.floor(diff/86400)} يوم`;
    },

    escape(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    },

    clearBadges() {
        document.querySelectorAll('#notifBadge, .notif-btn .notif-badge, .notif-btn .notif-dot, #notifLink .badge-count')
            .forEach((badge) => badge.remove());
    }
};

// تشغيل تلقائي عند وجود زر الإشعارات
document.addEventListener('DOMContentLoaded', () => {
    NotifManager.init();
});

window.NotifManager = NotifManager;
window.openNotif = function openNotif(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    NotifManager.toggle(true);
    return false;
};
