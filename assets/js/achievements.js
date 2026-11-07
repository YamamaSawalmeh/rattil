/* RATTIL — achievements.js | إدارة الإنجازات والشارات */

const AchievementsApp = {

    /* ---- تحميل الشارات ---- */
    async loadBadges(studentId, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '<p style="color:#9ca3af;padding:16px;">جارٍ التحميل...</p>';
        try {
            const r = await fetch(`/rattil/api/achievements.php?action=badges&student_id=${studentId}`);
            const d = await r.json();
            if (!d.success || !d.data.length) {
                container.innerHTML = '<p style="color:#9ca3af;padding:16px;">لا توجد شارات بعد</p>';
                return;
            }
            container.innerHTML = d.data.map(b => `
                <div class="badge-item">
                    <div class="bi-icon">${b.icon}</div>
                    <div class="bi-info">
                        <div class="bi-name">${b.name}</div>
                        <div class="bi-desc">${b.description || ''}</div>
                        ${b.granter_name ? `<div class="bi-granter">من: ${b.granter_name}</div>` : ''}
                    </div>
                </div>`).join('');
        } catch (e) {
            container.innerHTML = '<p style="color:#dc2626;padding:16px;">تعذّر تحميل الشارات</p>';
        }
    },

    /* ---- Leaderboard ---- */
    async loadLeaderboard(halqaId, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        try {
            const url = halqaId
                ? `/rattil/api/achievements.php?action=leaderboard&halqa_id=${halqaId}`
                : '/rattil/api/achievements.php?action=leaderboard';
            const r = await fetch(url);
            const d = await r.json();
            if (!d.success || !d.data.length) {
                container.innerHTML = '<p style="color:#9ca3af;padding:16px;">لا توجد بيانات</p>';
                return;
            }
            const medals = ['🥇', '🥈', '🥉'];
            container.innerHTML = d.data.map((s, i) => `
                <div class="lb-item">
                    <div class="lb-rank ${i<3?'rank-'+(i+1):'rank-n'}">${medals[i] || (i+1)}</div>
                    <div class="lb-name">${s.name}</div>
                    <div class="lb-pts">${Number(s.total_earned).toLocaleString()} نقطة</div>
                </div>`).join('');
        } catch (e) {}
    },

    /* ---- حديقة الإنجازات ---- */
    async loadGarden(studentId, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        try {
            const r = await fetch(`/rattil/api/achievements.php?action=garden&student_id=${studentId}`);
            const d = await r.json();
            const emoji = { shrub:'🌿', palm:'🌴', oak:'🌳' };
            if (!d.success || !d.data.length) {
                container.innerHTML = `<div style="text-align:center;padding:32px;color:#9ca3af"><div style="font-size:2.5rem;margin-bottom:8px">🌱</div><p>الحديقة فارغة — احفظ سورة كاملة لتنمو أول شجرة!</p></div>`;
                return;
            }
            container.innerHTML = `<div class="garden-grid">${d.data.map(t => `
                <div class="tree-item" title="سورة ${t.surah_name}">
                    <div style="font-size:2rem;margin-bottom:6px">${emoji[t.tree_type]||'🌱'}</div>
                    <div style="font-size:.78rem;color:var(--p,#1B4332);font-weight:700">${t.surah_name}</div>
                    <div style="font-size:.7rem;color:#9ca3af;margin-top:2px">${t.planted_at?.substring(0,10)||''}</div>
                </div>`).join('')}</div>`;
        } catch (e) {}
    },

    /* ---- منح شارة (معلم) ---- */
    async grantBadge(studentId, badgeId, callback) {
        try {
            const r = await fetch('/rattil/api/achievements.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'grant_badge', student_id: studentId, badge_id: badgeId })
            });
            const d = await r.json();
            if (d.success) {
                if (typeof showToast === 'function') showToast(`✓ تم منح شارة: ${d.badge?.name||''}`);
                if (callback) callback(d);
            } else {
                if (typeof showToast === 'function') showToast(d.error || 'فشل منح الشارة', 'err');
            }
        } catch (e) {
            if (typeof showToast === 'function') showToast('خطأ في الاتصال', 'err');
        }
    },

    /* ---- تحميل كل الشارات المتاحة ---- */
    async loadAllBadges(containerId, onSelect) {
        const container = document.getElementById(containerId);
        if (!container) return;
        try {
            const r = await fetch('/rattil/api/achievements.php?action=all_badges');
            const d = await r.json();
            if (!d.success) return;
            const teacherBadges = d.data.filter(b => b.type === 'teacher_granted');
            container.innerHTML = teacherBadges.map(b => `
                <div class="badge-option" onclick="(${onSelect.toString()})(${b.id},'${b.name}')"
                     style="display:flex;align-items:center;gap:10px;padding:10px;border-radius:10px;border:1.5px solid #eee;cursor:pointer;margin-bottom:8px;transition:.2s;"
                     onmouseover="this.style.borderColor='var(--gold,#D4AF37)'"
                     onmouseout="this.style.borderColor='#eee'">
                    <span style="font-size:1.5rem">${b.icon}</span>
                    <div><div style="font-weight:700;color:var(--p,#1B4332)">${b.name}</div><div style="font-size:.8rem;color:#9ca3af">${b.description||''}</div></div>
                </div>`).join('');
        } catch (e) {}
    }
};

// CSS للـ achievements components
(function injectStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .badge-item{display:flex;align-items:center;gap:10px;padding:10px;border-radius:10px;background:var(--cream,#FAF7F0);margin-bottom:8px;}
        .bi-icon{font-size:1.8rem;flex-shrink:0;}
        .bi-name{font-size:.93rem;color:var(--p,#1B4332);font-weight:700;margin-bottom:2px;}
        .bi-desc{font-size:.8rem;color:#6B7280;}
        .bi-granter{font-size:.76rem;color:var(--s,#2D6A4F);margin-top:2px;}
        .garden-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:10px;}
        .tree-item{background:var(--cream,#FAF7F0);border-radius:12px;padding:12px 8px;text-align:center;cursor:pointer;transition:.25s;}
        .tree-item:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1);}`;
    document.head.appendChild(style);
})();
