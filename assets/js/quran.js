/* RATTIL — quran.js | منطق المصحف التفاعلي */

const QuranApp = {
    currentSurah: 114,
    ayahMarks: {},
    audioQueue: [],
    queueIdx: 0,
    isPlaying: false,
    currentAyah: null,
    audio: null,

    init() {
        this.audio = document.getElementById('audioEl') || new Audio();
        this.bindAudioEvents();
        this.loadSurahList();
    },

    /* ---- قائمة السور ---- */
    async loadSurahList() {
        try {
            const r = await fetch('https://api.alquran.cloud/v1/surah');
            const d = await r.json();
            const sel = document.getElementById('surahSelect');
            if (!sel) return;
            sel.innerHTML = '';
            d.data.forEach(s => {
                const o = document.createElement('option');
                o.value = s.number;
                o.textContent = `${s.number}. ${s.name} (${s.numberOfAyahs})`;
                if (s.number === this.currentSurah) o.selected = true;
                sel.appendChild(o);
            });
            this.loadSurah(this.currentSurah);
        } catch (e) {
            this.showLoadError('تعذّر تحميل قائمة السور');
        }
    },

    /* ---- تحميل سورة ---- */
    async loadSurah(num) {
        this.currentSurah = parseInt(num);
        this.showLoading(true);
        try {
            const [surahRes, marksRes] = await Promise.all([
                fetch(`https://api.alquran.cloud/v1/surah/${this.currentSurah}`),
                fetch(`/rattil/api/quran.php?action=get_marks&surah=${this.currentSurah}`)
            ]);
            const surahData = await surahRes.json();
            const marksData = await marksRes.json();

            this.ayahMarks = {};
            if (marksData.success) {
                marksData.data.forEach(m => this.ayahMarks[m.ayah_number] = m.status);
            }
            this.renderSurah(surahData.data);
            this.audioQueue = surahData.data.ayahs.map(a => a.numberInSurah);
        } catch (e) {
            this.showLoadError('تعذّر تحميل السورة');
        }
    },

    /* ---- عرض السورة ---- */
    renderSurah(surah) {
        const wrap = document.getElementById('ayahsWrap');
        const nameEl = document.getElementById('surahName');
        const metaEl = document.getElementById('surahMeta');
        const bisEl  = document.getElementById('bismillah');
        if (!wrap) return;

        if (nameEl) nameEl.textContent = surah.name;
        if (metaEl) metaEl.textContent = `${surah.numberOfAyahs} آية — ${surah.revelationType === 'Meccan' ? 'مكية' : 'مدنية'}`;
        if (bisEl)  bisEl.style.display = (surah.number === 1 || surah.number === 9) ? 'none' : 'block';

        wrap.innerHTML = '';
        surah.ayahs.forEach(ayah => {
            const span = document.createElement('span');
            span.className = 'ayah-span ' + (this.ayahMarks[ayah.numberInSurah] || '');
            span.dataset.ayah = ayah.numberInSurah;
            span.innerHTML = ` ${ayah.text} `;

            // Click = تلوين، Double Click = تشغيل
            let clicks = 0;
            span.addEventListener('click', (e) => {
                clicks++;
                if (clicks === 1) {
                    setTimeout(() => {
                        if (clicks === 1) this.showContextMenu(e, ayah.numberInSurah);
                        clicks = 0;
                    }, 220);
                } else {
                    clicks = 0;
                    this.playAyah(ayah.numberInSurah, surah.name);
                }
            });

            const numSpan = document.createElement('span');
            numSpan.className = 'ayah-num';
            numSpan.textContent = this.toAr(ayah.numberInSurah);

            wrap.appendChild(span);
            wrap.appendChild(numSpan);
            wrap.appendChild(document.createTextNode(' '));
        });

        this.showLoading(false);
    },

    /* ---- Context Menu ---- */
    showContextMenu(e, ayahNum) {
        this._selectedAyah = ayahNum;
        const menu = document.getElementById('ctxMenu');
        if (!menu) return;
        menu.style.display = 'block';
        menu.style.top  = (e.clientY + window.scrollY) + 'px';
        menu.style.right= (window.innerWidth - e.clientX) + 'px';
        menu.style.left = 'auto';
    },

    hideContextMenu() {
        const menu = document.getElementById('ctxMenu');
        if (menu) menu.style.display = 'none';
    },

    async markAyah(status) {
        this.hideContextMenu();
        if (!this._selectedAyah) return;
        const ayah = this._selectedAyah;
        try {
            const r = await fetch('/rattil/api/quran.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_ayah', surah: this.currentSurah, ayah, status })
            });
            const d = await r.json();
            if (d.success) {
                const span = document.querySelector(`[data-ayah="${ayah}"]`);
                if (span) span.className = 'ayah-span ' + (status === 'none' ? '' : status);
                if (status !== 'none') this.ayahMarks[ayah] = status;
                else delete this.ayahMarks[ayah];
                const labels = { memorized:'محفوظة', reviewing:'مراجعة', last_stop:'آخر توقف', none:'تم إزالة التلوين' };
                if (typeof showToast === 'function') showToast('✓ ' + (labels[status] || ''));
            }
        } catch (e) {
            if (typeof showToast === 'function') showToast('خطأ في الاتصال', 'err');
        }
    },

    /* ---- Audio ---- */
    getAudioUrl(ayahNum) {
        const reciter = document.getElementById('reciterSelect')?.value || 'ar.alafasy';
        return `https://cdn.islamic.network/quran/audio/128/${reciter}/${ayahNum}.mp3`;
    },

    playAyah(ayahNum, surahName) {
        this.currentAyah = ayahNum;
        document.querySelectorAll('.ayah-span').forEach(s => s.classList.remove('playing'));
        const span = document.querySelector(`[data-ayah="${ayahNum}"]`);
        if (span) { span.classList.add('playing'); span.scrollIntoView({ behavior: 'smooth', block: 'center' }); }

        const titleEl = document.getElementById('audioTitle');
        const subEl   = document.getElementById('audioSub');
        if (titleEl) titleEl.textContent = `سورة ${surahName} — آية ${this.toAr(ayahNum)}`;
        if (subEl)   subEl.textContent   = span ? span.textContent.trim().substring(0, 50) + '...' : '';

        this.audio.src = this.getAudioUrl(ayahNum);
        this.audio.play().catch(() => {});
        this.isPlaying = true;

        const player = document.getElementById('audioPlayer');
        if (player) player.style.display = 'flex';
        const playBtn = document.getElementById('playBtn');
        if (playBtn) playBtn.textContent = '⏸';
    },

    playAll() {
        this.queueIdx = 0;
        this.isPlaying = true;
        if (this.audioQueue.length) this.playAyah(this.audioQueue[0], document.getElementById('surahName')?.textContent || '');
    },

    togglePlay() {
        if (!this.audio) return;
        const playBtn = document.getElementById('playBtn');
        if (this.audio.paused) {
            this.audio.play();
            this.isPlaying = true;
            if (playBtn) playBtn.textContent = '⏸';
        } else {
            this.audio.pause();
            this.isPlaying = false;
            if (playBtn) playBtn.textContent = '▶';
        }
    },

    bindAudioEvents() {
        if (!this.audio) return;
        this.audio.addEventListener('ended', () => {
            document.querySelectorAll('.ayah-span').forEach(s => s.classList.remove('playing'));
            this.queueIdx++;
            if (this.queueIdx < this.audioQueue.length && this.isPlaying) {
                this.playAyah(this.audioQueue[this.queueIdx], document.getElementById('surahName')?.textContent || '');
            } else {
                const playBtn = document.getElementById('playBtn');
                if (playBtn) playBtn.textContent = '▶';
                this.isPlaying = false;
            }
        });

        this.audio.addEventListener('timeupdate', () => {
            const fill = document.getElementById('audioFill');
            const cur  = document.getElementById('audioCurrent');
            const dur  = document.getElementById('audioDuration');
            if (this.audio.duration) {
                if (fill) fill.style.width = (this.audio.currentTime / this.audio.duration * 100) + '%';
                if (cur)  cur.textContent  = this.fmtTime(this.audio.currentTime);
                if (dur)  dur.textContent  = this.fmtTime(this.audio.duration);
            }
        });
    },

    seekAudio(e) {
        const bar = document.getElementById('audioBar');
        if (!bar || !this.audio.duration) return;
        this.audio.currentTime = (e.offsetX / bar.offsetWidth) * this.audio.duration;
    },

    /* ---- Helpers ---- */
    showLoading(state) {
        const l = document.getElementById('loadingQ');
        const c = document.getElementById('quranContent');
        if (l) l.style.display = state ? 'flex' : 'none';
        if (c) c.style.display = state ? 'none' : 'block';
    },

    showLoadError(msg) {
        const l = document.getElementById('loadingQ');
        if (l) l.innerHTML = `<p style="color:#dc2626">${msg}</p>`;
    },

    fmtTime(s) {
        const m = Math.floor(s / 60);
        return `${m}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
    },

    toAr(n) {
        return String(n).replace(/[0-9]/g, d => '٠١٢٣٤٥٦٧٨٩'[d]);
    }
};

// Context Menu — إغلاق عند النقر خارجه
document.addEventListener('click', (e) => {
    if (!e.target.closest('#ctxMenu') && !e.target.closest('.ayah-span')) {
        QuranApp.hideContextMenu();
    }
});
