<style>
    :root {
        --sp-olive-950: #091a10;
        --sp-olive-900: #0f2d1f;
        --sp-olive-700: #1d4a32;
        --sp-olive-600: #246040;
        --sp-bg-beige: #fdfbf7;
        --sp-border-beige: #f0e6d2;
        --sp-card-bg: #ffffff;
        --sp-text-main: #2d3748;
        --sp-shadow: -10px 0 30px rgba(15, 45, 31, 0.12);
        --sp-transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .sp-profile-panel {
        position: fixed;
        top: 0;
        right: -520px;
        width: 490px;
        height: 100vh;
        background: var(--sp-bg-beige);
        box-shadow: var(--sp-shadow);
        z-index: 1100;
        transition: var(--sp-transition);
        display: flex;
        flex-direction: column;
        font-family: 'Tajawal', sans-serif;
        border-left: 1px solid var(--sp-border-beige);
        direction: rtl;
        text-align: right;
    }

    .sp-profile-panel.open { right: 0; }

    .sp-panel-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(9, 26, 16, 0.5);
        backdrop-filter: blur(5px);
        z-index: 1090;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sp-panel-backdrop.show { display: block; opacity: 1; }

    .sp-header {
        background: linear-gradient(135deg, var(--sp-olive-950) 0%, var(--sp-olive-900) 100%);
        color: #fdfbf7;
        padding: 2rem 1.5rem;
        position: relative;
        border-bottom: 3px solid #d4a359;
    }

    .sp-close-btn {
        position: absolute;
        top: 1rem;
        left: 1rem;
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: #fdfbf7;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        font-size: 1.25rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sp-header-profile-box { display: flex; align-items: center; gap: 1.25rem; margin-top: 0.5rem; }

    .sp-avatar-circle {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #d4a359;
        color: var(--sp-olive-950);
        font-size: 1.8rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 3px solid var(--sp-border-beige);
    }

    .sp-header-info h2 { margin: 0 0 0.35rem 0; font-size: 1.4rem; font-weight: 700; color: #fdfbf7; }

    .sp-sub-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.8rem;
        background: rgba(255, 255, 255, 0.1);
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        color: #e2e8f0;
    }

    .sp-body { padding: 1.5rem; overflow-y: auto; flex: 1; }

    .sp-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem; }

    .sp-stat-card {
        background: var(--sp-card-bg);
        border: 1px solid var(--sp-border-beige);
        padding: 1rem;
        border-radius: 14px;
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }

    .sp-stat-icon-wrapper {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: rgba(15, 45, 31, 0.05);
        color: var(--sp-olive-700);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sp-stat-num { display: block; font-size: 1.2rem; font-weight: 800; color: var(--sp-olive-950); }
    .sp-stat-lbl { font-size: 0.75rem; color: #718096; }

    .sp-section-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--sp-olive-900);
        margin: 1.5rem 0 0.85rem 0;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        border-bottom: 1px solid var(--sp-border-beige);
        padding-bottom: 0.4rem;
    }

    .sp-timeline { position: relative; padding-right: 1.25rem; margin-top: 0.5rem; }
    .sp-timeline::before {
        content: ''; position: absolute; right: 4px; top: 10px; height: calc(100% - 20px); width: 2px; background: var(--sp-border-beige);
    }

    .sp-timeline-item { position: relative; margin-bottom: 1.25rem; }
    .sp-timeline-dot { position: absolute; right: -1.2rem; top: 6px; width: 10px; height: 10px; border-radius: 50%; background: #d4a359; border: 2px solid var(--sp-bg-beige); }
    .sp-timeline-content { background: var(--sp-card-bg); border: 1px solid var(--sp-border-beige); border-radius: 12px; padding: 0.85rem 1rem; }
    .sp-timeline-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; }
    .sp-surah-title { font-size: 0.9rem; font-weight: 700; color: var(--sp-olive-950); }
    .sp-badge-grade { font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: 10px; font-weight: 700; }

    .sp-note-card {
        background: var(--sp-card-bg); border-right: 4px solid var(--sp-olive-700); border-top: 1px solid var(--sp-border-beige); border-bottom: 1px solid var(--sp-border-beige); border-left: 1px solid var(--sp-border-beige); padding: 0.85rem; border-radius: 8px; margin-bottom: 0.75rem;
    }

    .sp-badges-flex { display: flex; flex-wrap: wrap; gap: 0.75rem; }
    .sp-badge-pill { background: var(--sp-card-bg); border: 1px solid var(--sp-border-beige); border-radius: 30px; padding: 0.4rem 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; }

    .sp-progress-container { background: #e9e5db; border-radius: 8px; height: 8px; overflow: hidden; margin-top: 0.5rem; }
    .sp-progress-bar { background: var(--sp-olive-600); height: 100%; width: 0%; transition: width 0.6s ease; }

    .sp-quick-note-box { background: #fff; border: 1px solid var(--sp-border-beige); padding: 1rem; border-radius: 12px; margin-top: 0.5rem; }
    .sp-textarea { width: 100%; height: 60px; border: 1px solid var(--sp-border-beige); border-radius: 6px; padding: 0.5rem; font-family: inherit; font-size: 0.85rem; resize: none; box-sizing: border-box; }
    .sp-submit-note-btn { background: var(--sp-olive-700); color: white; border: none; padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.8rem; cursor: pointer; margin-top: 0.4rem; font-weight: bold; }

    .sp-footer { padding: 1rem 1.5rem; background: #ffffff; border-top: 1px solid var(--sp-border-beige); display: flex; gap: 0.75rem; }
    .sp-btn { flex: 1; padding: 0.65rem; border-radius: 8px; font-weight: 600; font-size: 0.88rem; text-align: center; cursor: pointer; text-decoration: none; border: none; }
    .sp-btn-primary { background: var(--sp-olive-900); color: #fdfbf7; }
    .sp-btn-secondary { background: rgba(15, 45, 31, 0.05); color: var(--sp-olive-900); }
</style>

<div class="sp-panel-backdrop" id="spBackdrop" onclick="closeStudentProfile()"></div>
<div class="sp-profile-panel" id="spProfilePanel">
    
    <div class="sp-header">
        <button class="sp-close-btn" onclick="closeStudentProfile()">&times;</button>
        <div class="sp-header-profile-box">
            <div id="spAvatar" class="sp-avatar-circle"></div>
            <div class="sp-header-info">
                <h2 id="spStudentName">جاري التحميل...</h2>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <span class="sp-sub-pill">
                        <span id="spHalqaText">جاري جلب الحلقة...</span>
                    </span>
                    <span class="sp-sub-pill" id="spGeneralBadge">متابعة</span>
                </div>
            </div>
        </div>
    </div>

    <div class="sp-body">
        <div class="sp-stats-grid">
            <div class="sp-stat-card">
                <div class="sp-stat-icon-wrapper">📊</div>
                <div>
                    <span class="sp-stat-num" id="spStatRecitations">0</span>
                    <span class="sp-stat-lbl">إجمالي التسميعات</span>
                </div>
            </div>
            <div class="sp-stat-card">
                <div class="sp-stat-icon-wrapper">✨</div>
                <div>
                    <span class="sp-stat-num" id="spStatPoints">0</span>
                    <span class="sp-stat-lbl">مجموع النقاط</span>
                </div>
            </div>
            <div class="sp-stat-card">
                <div class="sp-stat-icon-wrapper">👑</div>
                <div>
                    <span class="sp-stat-num" id="spStatLevel">1</span>
                    <span class="sp-stat-lbl">المستوى الحالي</span>
                </div>
            </div>
            <div class="sp-stat-card">
                <div class="sp-stat-icon-wrapper">🕒</div>
                <div>
                    <span class="sp-stat-num" style="font-size:0.85rem;" id="spStatLastActive">-</span>
                    <span class="sp-stat-lbl">آخر نشاط</span>
                </div>
            </div>
        </div>

        <div class="sp-section-title">✍️ إضافة توجيه/ملاحظة فورية للطالب</div>
        <div class="sp-quick-note-box">
            <input type="hidden" id="spTargetStudentId">
            <textarea id="spQuickNoteText" class="sp-textarea" placeholder="اكتب الملاحظة أو التنبيه هنا ليظهر في حساب الطالب فورا..."></textarea>
            <button class="sp-submit-note-btn" onclick="submitQuickNote()">إرسال الملاحظة وحفظها</button>
        </div>

        <div class="sp-section-title">📈 مستوى الحفظ والتقدم التراكمي</div>
        <div style="background:var(--sp-card-bg); border:1px solid var(--sp-border-beige); padding:1rem; border-radius:12px;">
            <div style="display:flex; justify-content:space-between; font-size:0.8rem; font-weight:600; color:var(--sp-olive-950);">
                <span>نسبة التميز والمواظبة</span>
                <span id="spProgressText">0%</span>
            </div>
            <div class="sp-progress-container">
                <div class="sp-progress-bar" id="spProgressBar"></div>
            </div>
        </div>

        <div class="sp-section-title">🏅 الشارات والأوسمة الممنوحة</div>
        <div class="sp-badges-flex" id="spBadgesContainer"></div>

        <div class="sp-section-title">💬 توجيهات وملاحظات الشيوخ السابقة</div>
        <div id="spNotesContainer"></div>

        <div class="sp-section-title">📜 سجل التسميع الزمني (Timeline)</div>
        <div class="sp-timeline" id="spTimelineContainer"></div>
    </div>

    <div class="sp-footer">
        <button class="sp-btn sp-btn-secondary" onclick="closeStudentProfile()">إغلاق الشاشة</button>
    </div>
</div>

<script>
let currentOpenStudentId = null;

async function openStudentProfile(studentId) {
    if(!studentId) return;
    currentOpenStudentId = studentId;
    document.getElementById('spTargetStudentId').value = studentId;
    document.getElementById('spQuickNoteText').value = '';
    
    const panel = document.getElementById('spProfilePanel');
    const backdrop = document.getElementById('spBackdrop');
    panel.classList.add('open');
    backdrop.classList.add('show');

    try {
        // استخدام مسار مطلق يبدأ بـ /rattil/ لضمان عدم حدوث خطأ 404 نهائياً
        const response = await fetch(`/rattil/api/student_profile_api.php?action=get_profile&id=${studentId}`);
        const result = await response.json();

        if(!result.success) {
            alert('⚠️ خطأ: ' + result.error);
            closeStudentProfile();
            return;
        }

        const data = result.data;
        
        document.getElementById('spStudentName').innerText = data.profile.name;
        document.getElementById('spAvatar').innerText = data.profile.name.charAt(0);
        
        if (data.halaqat && data.halaqat.length > 0) {
            document.getElementById('spHalqaText').innerText = `حلقة ${data.halaqat[0].halqa_name} (الشيخ: ${data.halaqat[0].teacher_name || 'غير محدد'})`;
        } else {
            document.getElementById('spHalqaText').innerText = 'غير مسجل بحلقات حالياً';
        }

        const genBadge = document.getElementById('spGeneralBadge');
        genBadge.innerText = data.general_grade;
        if(data.general_grade === 'ممتاز' || data.general_grade === 'جيد جداً') {
            genBadge.style.background = '#246040'; genBadge.style.color = '#fff';
        } else {
            genBadge.style.background = '#e53e3e'; genBadge.style.color = '#fff';
        }

        document.getElementById('spStatRecitations').innerText = data.total_recitations;
        document.getElementById('spStatPoints').innerText = parseInt(data.profile.points).toLocaleString();
        document.getElementById('spStatLevel').innerText = data.profile.level;
        
        const progressPercent = Math.min(100, (parseInt(data.profile.points) % 100));
        document.getElementById('spProgressBar').style.width = `${progressPercent}%`;
        document.getElementById('spProgressText').innerText = `${progressPercent}%`;

        // بناء التايم لاين
        const timelineContainer = document.getElementById('spTimelineContainer');
        timelineContainer.innerHTML = '';
        
        if(data.recitations.length === 0) {
            timelineContainer.innerHTML = '<p style="text-align:center; font-size:0.8rem; color:#a0aec0; padding:1rem;">لا توجد سجلات تسميع بعد.</p>';
            document.getElementById('spStatLastActive').innerText = 'لا يوجد';
        } else {
            document.getElementById('spStatLastActive').innerText = data.recitations[0].recorded_at.split(' ')[0];

            data.recitations.forEach(rec => {
                let gradeColor = '#2d7a52';
                if(rec.grade === 'إعادة' || rec.grade === 'تنبيه') gradeColor = '#e53e3e';
                else if(rec.grade !== 'ممتاز' && rec.grade !== 'جيد جداً') gradeColor = '#d4a359';

                timelineContainer.innerHTML += `
                    <div class="sp-timeline-item">
                        <div class="sp-timeline-dot" style="background:${gradeColor}"></div>
                        <div class="sp-timeline-content">
                            <div class="sp-timeline-head">
                                <span class="sp-surah-title">${rec.surah_name}</span>
                                <span class="sp-badge-grade" style="background:${gradeColor}15; color:${gradeColor};">${rec.grade}</span>
                            </div>
                            <div style="font-size:0.75rem; color:#4a5568;">
                                آيات: ${rec.ayah_from} - ${rec.ayah_to} | نقاط: +${rec.points_awarded}
                            </div>
                            ${rec.notes ? `<div style="font-size:0.78rem; color:#2d3748; background:#f7fafc; padding:0.4rem; border-radius:6px; margin-top:0.3rem;">${rec.notes}</div>` : ''}
                            <div style="font-size:0.68rem; color:#a0aec0; margin-top:0.25rem;">
                                الشيخ: ${rec.teacher_name || 'الإدارة'} - ${rec.recorded_at}
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        // بناء الملاحظات
        const notesContainer = document.getElementById('spNotesContainer');
        notesContainer.innerHTML = '';
        const recWithNotes = data.recitations.filter(r => r.notes && r.notes.trim() !== '');
        
        if(recWithNotes.length === 0) {
            notesContainer.innerHTML = '<p style="text-align:center; font-size:0.8rem; color:#a0aec0; padding:0.5rem;">لا توجد ملاحظات سابقة.</p>';
        } else {
            recWithNotes.slice(0, 3).forEach(rec => {
                notesContainer.innerHTML += `
                    <div class="sp-note-card">
                        <div style="font-size:0.78rem; font-weight:700; color:var(--sp-olive-950);">الشيخ: ${rec.teacher_name || 'المشرف'}</div>
                        <div style="font-size:0.8rem; color:#4a5568;">"${rec.notes}"</div>
                    </div>
                `;
            });
        }

        // بناء الشارات
        const badgesContainer = document.getElementById('spBadgesContainer');
        badgesContainer.innerHTML = '';
        if(data.badges.length === 0) {
            badgesContainer.innerHTML = '<p style="text-align:center; font-size:0.8rem; color:#a0aec0; width:100%;">لم يحصل على أوسمة بعد.</p>';
        } else {
            data.badges.forEach(b => {
                badgesContainer.innerHTML += `
                    <div class="sp-badge-pill" title="${b.description || b.name}">
                        <span>${b.icon || '🏅'}</span>
                        <strong>${b.name}</strong>
                    </div>
                `;
            });
        }

    } catch (err) {
        console.error(err);
        alert('❌ فشل في جلب البيانات من الخادم.');
        closeStudentProfile();
    }
}

async function submitQuickNote() {
    const text = document.getElementById('spQuickNoteText').value.trim();
    if(!text) { alert('الرجاء كتابة نص الملاحظة أولاً'); return; }

    try {
        const formData = new FormData();
        formData.append('student_id', currentOpenStudentId);
        formData.append('note', text);

        const response = await fetch('/rattil/api/student_profile_api.php?action=add_note', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();

        if(res.success) {
            alert('✓ ' + res.message);
            // إعادة تحديث البيانات التايم لاين فوراً لرؤية الملاحظة المضافة حديثاً
            openStudentProfile(currentOpenStudentId);
        } else {
            alert('⚠️ ' + res.error);
        }
    } catch(e) {
        alert('حدث خطأ في الاتصال أثناء حفظ الملاحظة.');
    }
}

function closeStudentProfile() {
    document.getElementById('spProfilePanel').classList.remove('open');
    document.getElementById('spBackdrop').classList.remove('show');
}
</script>