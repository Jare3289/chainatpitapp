/* ============================================================
   CSRF wrapper around fetch
   - Attaches X-CSRF-Token header on POST/PUT/PATCH/DELETE
   - Token is fetched lazily from /api/csrf.php (or seeded by checkAuth)
   - 401 from /api/csrf.php = not logged in yet → request proceeds with no token
     (login endpoint is exempt from CSRF on the server side)
   ============================================================ */
(function () {
    let csrfToken = null;
    let csrfFetchPromise = null;

    async function ensureCsrfToken() {
        if (csrfToken) return csrfToken;
        if (csrfFetchPromise) return csrfFetchPromise;
        csrfFetchPromise = (async () => {
            try {
                const r = await fetch('../api/csrf.php', { credentials: 'same-origin' });
                if (r.ok) {
                    const j = await r.json();
                    if (j && j.csrf_token) csrfToken = j.csrf_token;
                }
            } catch (e) { /* swallow */ }
            csrfFetchPromise = null;
            return csrfToken;
        })();
        return csrfFetchPromise;
    }

    window.cnpSeedCsrfToken = (t) => { if (t) csrfToken = t; };
    window.cnpClearCsrfToken = () => { csrfToken = null; };

    const origFetch = window.fetch.bind(window);
    window.fetch = async function (input, init = {}) {
        const method = ((init && init.method) || (input && input.method) || 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            const token = await ensureCsrfToken();
            if (token) {
                init = init || {};
                const headers = new Headers(init.headers || {});
                if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
                init.headers = headers;
            }
        }
        if (init && !('credentials' in init)) init.credentials = 'same-origin';
        return origFetch(input, init);
    };
})();

// Inject Global Styles dynamically
(function () {
    if (!document.getElementById('antigravity-custom-styles')) {
        const s = document.createElement('style');
        s.id = 'antigravity-custom-styles';
        s.innerHTML = `
            .noti-dropdown { width: 360px; max-height: 500px; overflow-y: auto; border-radius: 12px !important; padding: 0 !important; }
            .noti-header { padding: 16px; border-bottom: 1px solid #f0f2f5; position: sticky; top: 0; background: white; z-index: 10; }
            .noti-item { padding: 12px 16px; display: flex; align-items: start; gap: 12px; cursor: pointer; transition: 0.2s; border-bottom: 1px solid #f0f2f5; text-decoration: none !important; color: inherit !important; }
            .noti-item:hover { background-color: #f0f2f5; }
            .noti-item.unread { background-color: #ebf5ff; }
            .noti-icon { width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
            .noti-content { flex-grow: 1; }
            .noti-title { font-weight: 700; font-size: 0.9rem; margin-bottom: 2px; line-height: 1.3; }
            .noti-msg { font-size: 0.85rem; color: #65676b; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .noti-item.system-update { background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-left: 3px solid #7c3aed; }
            .noti-item.system-update:hover { background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); }
            .noti-item.system-update .noti-msg { white-space: pre-line; -webkit-line-clamp: 8; color: #4b5563; }
            .noti-dismiss { position: absolute; top: 8px; right: 8px; background: none; border: none; color: #9ca3af; font-size: 1rem; line-height: 1; cursor: pointer; padding: 2px 6px; border-radius: 4px; }
            .noti-dismiss:hover { background: rgba(0,0,0,0.06); color: #374151; }
            .noti-item { position: relative; }
            .noti-time { font-size: 0.75rem; color: #0866ff; font-weight: 600; }
            .noti-dot { width: 12px; height: 12px; background-color: #0866ff; border-radius: 50%; align-self: center; flex-shrink: 0; }
            .noti-badge { position: absolute; top: -5px; right: -5px; background-color: #e41e3f; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; font-weight: 900; border: 2px solid white; }
            .noti-empty { padding: 40px 20px; text-align: center; color: #65676b; }
            .noti-btn-filter { font-size: 0.85rem; font-weight: 600; padding: 6px 12px; border: none; background: transparent; color: #65676b; }
            .noti-btn-filter.active { background: #ebf5ff; color: #0064d1; }
        `;
        document.head.appendChild(s);
    }
    if (!document.querySelector('link[href*="bootstrap-icons"]')) {
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css';
        document.head.appendChild(l);
    }
    if (!document.querySelector('link[href*="font-awesome"]')) {
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
        document.head.appendChild(l);
    }

    // ── PWA: Add to Home Screen support ──
    if (!document.querySelector('link[rel="manifest"]')) {
        const m = document.createElement('link');
        m.rel = 'manifest';
        m.href = '../public/manifest.json';
        document.head.appendChild(m);
    }
    if (!document.querySelector('meta[name="theme-color"]')) {
        const t = document.createElement('meta');
        t.name = 'theme-color';
        t.content = '#0d6efd';
        document.head.appendChild(t);
    }
    if (!document.querySelector('link[rel="apple-touch-icon"]')) {
        const a = document.createElement('link');
        a.rel = 'apple-touch-icon';
        a.href = '../public/img/icons/apple-touch-icon.png';
        document.head.appendChild(a);
    }
    if (!document.querySelector('meta[name="apple-mobile-web-app-capable"]')) {
        const c = document.createElement('meta');
        c.name = 'apple-mobile-web-app-capable';
        c.content = 'yes';
        document.head.appendChild(c);
    }
    if (!document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]')) {
        const s = document.createElement('meta');
        s.name = 'apple-mobile-web-app-status-bar-style';
        s.content = 'default';
        document.head.appendChild(s);
    }
    if (!document.querySelector('meta[name="apple-mobile-web-app-title"]')) {
        const ttl = document.createElement('meta');
        ttl.name = 'apple-mobile-web-app-title';
        ttl.content = 'CNP App';
        document.head.appendChild(ttl);
    }
    if (!document.querySelector('link[rel="icon"][sizes="192x192"]')) {
        const f = document.createElement('link');
        f.rel = 'icon';
        f.type = 'image/png';
        f.setAttribute('sizes', '192x192');
        f.href = '../public/img/icons/icon-192.png';
        document.head.appendChild(f);
    }
})();

/* ── Single nav-link item ── */
function _navItem(href, icon, label, isActive, extraHtml = '') {
    return `<li class="nav-item">
        <a href="${href}" class="nav-link ${isActive ? 'active' : ''}">
            <i class="nav-icon ${icon}"></i><p>${label}${extraHtml}</p>
        </a>
    </li>`;
}

/* ── Treeview group (collapsible) ── */
function _navGroup(icon, label, children, anyActive) {
    const childItems = children.map(c => `
        <li class="nav-item">
            <a href="${c.href}" class="nav-link ${c.active ? 'active' : ''}">
                <i class="nav-icon ${c.icon}"></i><p>${c.label}</p>
            </a>
        </li>`).join('');
    return `<li class="nav-item ${anyActive ? 'menu-open' : ''}">
        <a href="#" class="nav-link ${anyActive ? 'active' : ''}" onclick="toggleTreeview(this); return false;">
            <i class="nav-icon ${icon}"></i>
            <p>${label}<i class="nav-arrow bi bi-chevron-right ms-auto"></i></p>
        </a>
        <ul class="nav nav-treeview submenu-container">${childItems}</ul>
    </li>`;
}

/* ── Main sidebar renderer ── */
function renderSidebar(role, user, settings = {}) {
    const sidebar = document.getElementById('mainSidebar');
    if (!sidebar) return;

    // Inject Mobile Floating Toggle & Backdrop if not exist
    if (!document.getElementById('mobile-toggle-btn')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.id = 'mobile-toggle-btn';
        toggleBtn.className = 'floating-toggle d-lg-none';
        toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
        
        // Draggable Logic
        let isDragging = false;
        let startX, startY, initialX, initialY;

        const startDrag = (e) => {
            isDragging = false;
            const clientX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
            const clientY = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY;
            startX = clientX;
            startY = clientY;
            const rect = toggleBtn.getBoundingClientRect();
            initialX = rect.left;
            initialY = rect.top;
        };

        const onDrag = (e) => {
            if (startX === undefined) return;
            const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
            const clientY = e.type === 'touchmove' ? e.touches[0].clientY : e.clientY;
            const dx = clientX - startX;
            const dy = clientY - startY;

            if (Math.abs(dx) > 5 || Math.abs(dy) > 5) {
                if (e.cancelable) e.preventDefault(); // Stop page scroll
                isDragging = true;
                toggleBtn.style.left = (initialX + dx) + 'px';
                toggleBtn.style.top = (initialY + dy) + 'px';
                toggleBtn.style.bottom = 'auto';
                toggleBtn.style.right = 'auto';
            }
        };

        const stopDrag = () => {
            if (!isDragging) {
                toggleSidebar();
            }
        };

        toggleBtn.addEventListener('mousedown', startDrag);
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', () => { if (startX !== undefined) { stopDrag(); startX = undefined; } });

        toggleBtn.addEventListener('touchstart', startDrag, { passive: true });
        toggleBtn.addEventListener('touchmove', onDrag, { passive: false });
        toggleBtn.addEventListener('touchend', stopDrag, { passive: true });

        document.body.appendChild(toggleBtn);
    }
    if (!document.getElementById('sidebar-backdrop')) {
        const backdrop = document.createElement('div');
        backdrop.id = 'sidebar-backdrop';
        backdrop.className = 'sidebar-backdrop';
        backdrop.onclick = toggleSidebar;
        document.body.appendChild(backdrop);
    }

    const p = window.location.pathname.split('/').pop();
    const a = href => p === href;

    const avatarUrl = (user && user.photo) ? `../${user.photo}` : '../public/img/default-avatar.png';

    let html = `
        <!-- Logo Header -->
        <div class="sidebar-logo-header">
            <a class="sidebar-logo-link" href="#">
                <img src="${settings.school_logo ? '../' + settings.school_logo : '../public/img/logo.png'}" class="sidebar-logo-img" onerror="this.src='../public/img/logo.png'">
                <div class="sidebar-logo-text">
                    <span class="sidebar-logo-title">${settings.school_name || 'โรงเรียนชัยนาทพิทยาคม'}</span>
                    <span class="sidebar-logo-sub">CNP Application</span>
                </div>
            </a>
            <button class="sidebar-close-btn" onclick="toggleSidebar()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="user-panel d-flex flex-column">
            <div class="d-flex align-items-center mb-1">
                <div class="image">
                    <img src="${avatarUrl}" class="rounded-circle shadow-sm" alt="User"
                         style="width:42px;height:42px;border:1px solid #f1f5f9;object-fit:cover;">
                </div>
                <div class="info ps-3">
                    ${(() => {
            if (role === 'admin') {
                let name = user.first_name_th || user.username;
                name = name.replace(/^(นาย|นาง|นางสาว|ดร\.|รศ\.|ผศ\.)\s*/, '');
                name = name.split(' ')[0];
                const pos = user.position || 'ผู้ดูแลระบบ';
                return `<div class="user-name">${name}</div>
                                    <div class="user-role">${pos}</div>`;
            } else if (role === 'teacher') {
                let name = user.first_name_th || user.username;
                name = name.replace(/^(นาย|นาง|นางสาว|ดร\.|รศ\.|ผศ\.)\s*/, '');
                name = name.split(' ')[0];
                const pos = user.academic_standing || user.position || 'ครูผู้สอน';
                return `<div class="user-name">${name}</div>
                                    <div class="user-role text-truncate" style="max-width: 130px;">${pos}</div>`;
            } else if (role === 'student') {
                const firstName = (user.first_name_th || '').trim() || user.username;
                const studentId = user.student_id || user.username;
                const classInfo = user.class_name ? ` · ห้อง ${user.class_name}` : '';
                return `<div class="user-name text-truncate" style="max-width: 180px;">${firstName}</div>
                                     <div class="user-role text-truncate" style="max-width: 180px;">ID: ${studentId}${classInfo}</div>`;
            }
            return `<div class="user-name">${user.username}</div><div class="user-role">นักเรียน</div>`;
        })()}
                </div>
            </div>
        </div>
        <div class="sidebar-status-panel">
            <div id="special-day-container"></div>
            <div class="d-flex align-items-center justify-content-center text-muted" style="font-size: 0.68rem;">
                <i class="fa-regular fa-calendar-days text-primary me-2"></i>
                <span id="sidebar-date"></span>
            </div>
            <div class="status-divider"></div>
            <div class="fw-bold text-danger text-center" style="font-size: 0.88rem; letter-spacing: 0.5px;">
                <i class="fa-regular fa-clock me-1"></i>
                <span id="sidebar-time"></span>
            </div>
            <div class="status-divider"></div>
            <div id="current-period-status" class="text-center" style="font-size: 0.68rem; color: #64748b;"></div>
        </div>

        <div class="sidebar-wrapper">
        <nav class="mt-2 px-2">
        <ul class="nav sidebar-menu flex-column">
    `;

    // Static items for all logged-in staff
    const isSupervisionAdmin = (role === 'admin') && (user.sub_role === 'supervision');
    const homeUrl = isSupervisionAdmin ? 'admin_supervision_booking.html' : (role === 'admin') ? 'admin_dashboard.html' : (role === 'teacher') ? 'teacher_dashboard.html' : 'student_dashboard.html';
    const pct = (user.profile_completion_pct !== undefined) ? user.profile_completion_pct : (user.is_profile_complete === false ? 0 : 100);
    const isProfileLocked = (role === 'student' || role === 'teacher') && pct < 90;

    // Warn student once per session if name is still "-"
    if (role === 'student' && !isProfileLocked && !sessionStorage.getItem('cnp_name_warned')) {
        const firstName = (user.first_name_th || '').trim();
        if (firstName === '-' || firstName === '—' || firstName === '') {
            sessionStorage.setItem('cnp_name_warned', '1');
            setTimeout(() => {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'ชื่อของคุณยังไม่ถูกต้อง',
                        html: 'ชื่อของคุณในระบบแสดงเป็น <strong>"-"</strong><br>กรุณาอัปเดตชื่อ-นามสกุลให้ถูกต้องในโปรไฟล์ด้วยนะ',
                        confirmButtonText: 'ไปอัปเดตโปรไฟล์',
                        showCancelButton: true,
                        cancelButtonText: 'ภายหลัง',
                        confirmButtonColor: '#1e3a8a'
                    }).then(r => { if (r.isConfirmed) window.location.href = 'student_profile.html'; });
                }
            }, 1500);
        }
    }

    if (isProfileLocked) {
        const profileUrl = role === 'teacher' ? 'teacher_profile.html' : 'student_profile.html';
        html += `<li class="nav-header text-warning fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> บังคับอัปเดตข้อมูล (${pct}%)</li>`;
        html += _navItem(profileUrl, 'bi bi-person-circle text-warning', 'โปรไฟล์ส่วนตัว (บังคับ)', true);
        html += `<li class="nav-item"><a href="#" class="nav-link text-danger" onclick="logout(); return false;"><i class="nav-icon bi bi-box-arrow-right"></i><p>ออกจากระบบ</p></a></li>`;
    } else if (isSupervisionAdmin) {
        // ─── Supervision-only admin menu ───────────────────────────
        html += `<li class="nav-header">ระบบนิเทศการสอน</li>`;
        html += _navItem('admin_supervision_booking.html', 'bi bi-calendar-check-fill text-primary', 'จัดการคิวและกรรมการ', a('admin_supervision_booking.html'));
        html += _navItem('admin_supervision.html', 'bi bi-bar-chart-fill text-info', 'สถิติภาพรวมนิเทศ', a('admin_supervision.html'));
        html += `<li class="nav-header mt-4">บัญชีผู้ใช้</li>`;
        html += _navItem('admin_profile.html', 'bi bi-person-circle', 'โปรไฟล์ส่วนตัว', a('admin_profile.html'));
        html += `<li class="nav-item"><a href="#" class="nav-link text-danger" onclick="logout(); return false;"><i class="nav-icon bi bi-box-arrow-right"></i><p>ออกจากระบบ</p></a></li>`;
        // ────────────────────────────────────────────────────────────
    } else {
        if (role === 'admin' || role === 'teacher') {
            html += _navItem(homeUrl, 'bi bi-house-door-fill', 'หน้าแรก', a(homeUrl));
            html += _navItem('javascript:showNotificationsModal()', 'bi bi-bell-fill', 'การแจ้งเตือน', false, '<span class="badge bg-danger rounded-pill ms-auto" id="sidebar-noti-badge" style="display:none; font-size: 0.7rem; padding: 0.35em 0.6em;">0</span>');
            html += _navItem('public_relations.html', 'bi bi-megaphone-fill text-warning', 'ประชาสัมพันธ์', a('public_relations.html'));
            const attendanceItems = [
                { href: 'attendance_daily.html', icon: 'bi bi-calendar-check', label: 'เช็คชื่อรายวัน', active: a('attendance_daily.html') },
                { href: 'attendance_subject.html', icon: 'bi bi-qr-code-scan', label: 'เช็คชื่อรายวิชา', active: a('attendance_subject.html') }
            ];

            if (role === 'teacher') {
                attendanceItems.push({ href: 'attendance_report.html', icon: 'bi bi-file-earmark-bar-graph text-primary', label: 'รายงานห้องตนเอง', active: a('attendance_report.html') });
                attendanceItems.push({ href: 'advisory_period_report.html', icon: 'bi bi-grid-3x3-gap-fill text-warning', label: 'เข้าเรียนห้องเรา', active: a('advisory_period_report.html') });
            }

            if (role === 'admin') {
                attendanceItems.push(
                    { href: 'today_overview.html', icon: 'bi bi-laptop text-info', label: 'ภาพรวมวันนี้', active: a('today_overview.html') },
                    { href: 'attendance_report.html', icon: 'bi bi-grid-3x3-gap-fill text-primary', label: 'รายงานรายห้อง', active: a('attendance_report.html') || a('admin_room_report.html') },
                    { href: 'monthly_stats.html', icon: 'bi bi-bar-chart-fill text-success', label: 'สถิติรายเดือน', active: a('monthly_stats.html') },
                    { href: 'at_risk_students.html', icon: 'bi bi-exclamation-triangle-fill text-danger', label: 'นักเรียนกลุ่มเสี่ยง', active: a('at_risk_students.html') },
                    { href: 'admin_executive.html', icon: 'bi bi-bar-chart-line-fill text-primary', label: 'ภาพรวมผู้บริหาร', active: a('admin_executive.html') }
                );
            }

            const isAttendanceActive = ['attendance_daily.html', 'attendance_subject.html', 'attendance_report.html', 'advisory_period_report.html', 'today_overview.html', 'admin_room_report.html', 'monthly_stats.html', 'at_risk_students.html', 'admin_executive.html'].some(x => a(x));
            html += _navGroup('bi bi-calendar-check-fill', 'เช็คชื่อ', attendanceItems, isAttendanceActive);

        const isPublicServiceActive = ['admin_public_service.html', 'admin_public_service_stats.html', 'teacher_public_service.html', 'teacher_public_service_report.html'].some(x => a(x));
        let psItems = [];
        if (role === 'admin') {
            psItems.push({ href: 'admin_public_service.html', icon: 'bi bi-activity', label: 'ภาพรวมกิจกรรม', active: a('admin_public_service.html') });
            psItems.push({ href: 'admin_public_service_stats.html', icon: 'bi bi-bar-chart-line', label: 'รายงานและสถิติ', active: a('admin_public_service_stats.html') });
        }
        if (role === 'teacher') {
            psItems.push({ href: 'teacher_public_service.html', icon: 'bi bi-check2-square', label: 'รอรับรอง', active: a('teacher_public_service.html') });
            psItems.push({ href: 'teacher_public_service_report.html', icon: 'bi bi-file-earmark-bar-graph', label: 'รายงานสาธา', active: a('teacher_public_service_report.html') });
        }
        if (psItems.length > 0) html += _navGroup('bi bi-heart-fill text-danger', 'สาธารณประโยชน์', psItems, isPublicServiceActive);

        const creditItems = [
            { href: 'credit_score_manage.html', icon: 'bi bi-person-plus-fill', label: 'เพิ่ม/ลบ คะแนน', active: a('credit_score_manage.html') },
            { href: 'credit_score_history.html', icon: 'bi bi-clock-history', label: 'ประวัติการให้คะแนน', active: a('credit_score_history.html') },
            { href: 'credit_score_report.html', icon: 'bi bi-file-earmark-bar-graph', label: 'รายงานสรุปคะแนน', active: a('credit_score_report.html') }
        ];
        if (role === 'admin') {
            creditItems.push({ href: 'credit_score_settings.html', icon: 'bi bi-gear-fill', label: 'ตั้งค่าระบบคะแนน', active: a('credit_score_settings.html') });
        }
        html += _navGroup('bi bi-star-fill text-warning', 'ตัดเติมแต้ม', creditItems, creditItems.some(x => x.active));

        if (role === 'admin') {
            html += _navGroup('bi bi-database-fill-gear', 'จัดการข้อมูลพื้นฐาน', [
                { href: 'admin_classes.html', icon: 'bi bi-filter-square', label: 'ข้อมูลชั้นเรียน', active: a('admin_classes.html') },
                { href: 'admin_subjects.html', icon: 'bi bi-book', label: 'ข้อมูลวิชา', active: a('admin_subjects.html') },
                { href: 'admin_departments.html', icon: 'bi bi-building', label: 'ข้อมูลกลุ่มสาระฯ', active: a('admin_departments.html') },
                { href: 'admin_teachers.html', icon: 'bi bi-person-video3', label: 'ข้อมูลครู', active: a('admin_teachers.html') },
                { href: 'admin_students.html', icon: 'bi bi-people', label: 'ข้อมูลนักเรียน', active: a('admin_students.html') }
            ], ['admin_classes.html', 'admin_subjects.html', 'admin_departments.html', 'admin_teachers.html', 'admin_students.html'].some(x => a(x)));

            html += _navGroup('bi bi-tools', 'ตั้งค่าระบบ', [
                { href: 'admin_settings.html', icon: 'bi bi-gear', label: 'ตั้งค่าทั่วไป', active: a('admin_settings.html') },
                { href: 'admin_sysadmins.html', icon: 'bi bi-shield-lock', label: 'ผู้ดูแลระบบ', active: a('admin_sysadmins.html') }
            ], a('admin_settings.html') || a('admin_sysadmins.html'));
            html += _navGroup('bi bi-table text-info', 'ตารางสอน', [
                { href: 'timetable.html',       icon: 'bi bi-calendar-week',   label: 'ดูตารางสอน',        active: a('timetable.html') },
                { href: 'admin_timetable.html', icon: 'bi bi-pencil-square',   label: 'จัดการตารางสอน',   active: a('admin_timetable.html') }
            ], a('timetable.html') || a('admin_timetable.html'));
        }
        if (role === 'teacher') {
            html += _navItem('timetable.html', 'bi bi-table text-info', 'ตารางสอน', a('timetable.html'));
            html += _navItem('admin_students.html', 'bi bi-people-fill', 'นักเรียนที่ปรึกษา', a('admin_students.html'));
        }
        html += _navItem('academic_calendar.html', 'bi bi-calendar3 text-warning', 'ปฏิทินวิชาการ', a('academic_calendar.html'));
        if (role === 'admin') {
            const supervisionItems = [
                { href: 'admin_supervision.html', icon: 'bi bi-bar-chart-fill', label: 'สถิติภาพรวมนิเทศ', active: a('admin_supervision.html') },
                { href: 'admin_supervision_booking.html', icon: 'bi bi-calendar-check-fill', label: 'จัดการคิวและกรรมการ', active: a('admin_supervision_booking.html') }
            ];
            const isSupervisionActive = ['admin_supervision.html', 'admin_supervision_booking.html', 'supervision.html'].some(x => a(x));
            html += _navGroup('bi bi-person-video3 text-primary', 'ระบบนิเทศการสอน', supervisionItems, isSupervisionActive);
        } else if (role === 'teacher') {
            const supervisionItems = [
                { href: 'teacher_supervision.html', icon: 'bi bi-house-door', label: 'ภาพรวมนิเทศ', active: a('teacher_supervision.html') },
                { href: 'supervision_booking.html', icon: 'bi bi-calendar-check', label: 'จองคิวนิเทศ', active: a('supervision_booking.html') },
                { href: 'supervision_docs.html', icon: 'bi bi-file-earmark-pdf', label: 'เอกสารนิเทศ', active: a('supervision_docs.html') },
                { href: 'supervision_evaluate.html', icon: 'bi bi-star', label: 'การประเมินผล', active: a('supervision_evaluate.html') },
                { href: 'supervision_post_teach.html', icon: 'bi bi-pencil-square', label: 'บันทึกหลังแผน', active: a('supervision_post_teach.html') },
                { href: 'supervision_print.html', icon: 'bi bi-printer', label: 'พิมพ์รายงาน', active: a('supervision_print.html') }
            ];

            if (user && user.is_supervision_manager) {
                supervisionItems.push(
                    { href: 'admin_supervision.html', icon: 'bi bi-bar-chart-fill text-warning', label: 'สถิติภาพรวมนิเทศ', active: a('admin_supervision.html') },
                    { href: 'admin_supervision_booking.html', icon: 'bi bi-calendar-check-fill text-warning', label: 'จัดการคิวและกรรมการ', active: a('admin_supervision_booking.html') }
                );
            }

            const isSupervisionActive = ['teacher_supervision.html', 'supervision_booking.html', 'supervision_docs.html', 'supervision_evaluate.html', 'supervision_post_teach.html', 'supervision_print.html', 'admin_supervision.html', 'admin_supervision_booking.html'].some(x => a(x));
            html += _navGroup('bi bi-person-video3 text-primary', 'นิเทศการสอน', supervisionItems, isSupervisionActive);
        }
        }

        if (role === 'student') {
            html += _navItem(homeUrl, 'bi bi-house-door-fill', 'หน้าแรก', a(homeUrl));
            html += _navItem('javascript:showNotificationsModal()', 'bi bi-bell-fill', 'การแจ้งเตือน', false, '<span class="badge bg-danger rounded-pill ms-auto" id="sidebar-noti-badge" style="display:none; font-size: 0.7rem; padding: 0.35em 0.6em;">0</span>');
            html += _navItem('public_relations.html', 'bi bi-megaphone-fill text-warning', 'ประชาสัมพันธ์', a('public_relations.html'));
            html += _navItem('student_attendance_history.html', 'bi bi-calendar-check', 'ประวัติการมาเรียน', a('student_attendance_history.html'));
            html += _navItem('student_credit_history.html', 'bi bi-star', 'คะแนนความประพฤติ', a('student_credit_history.html'));
            html += _navItem('student_public_service.html', 'bi bi-heart-fill text-danger', 'สาธารณประโยชน์', a('student_public_service.html'));
            html += _navItem('timetable.html', 'bi bi-table text-info', 'ตารางสอน', a('timetable.html'));
            html += _navItem('academic_calendar.html', 'bi bi-calendar3 text-warning', 'ปฏิทินวิชาการ', a('academic_calendar.html'));
        }

        html += `<li class="nav-header mt-4">บัญชีผู้ใช้</li>`;
        let profileUrl = role === 'admin' ? 'admin_profile.html' : role === 'teacher' ? 'teacher_profile.html' : 'student_profile.html';
        html += _navItem(profileUrl, 'bi bi-person-circle', 'โปรไฟล์ส่วนตัว', a(profileUrl));
        if (role === 'teacher') {
            html += _navItem('teacher_security.html', 'bi bi-shield-lock', 'ตั้งค่าความปลอดภัย', a('teacher_security.html'));
        }
        html += `<li class="nav-item"><a href="#" class="nav-link text-danger" onclick="logout(); return false;"><i class="nav-icon bi bi-box-arrow-right"></i><p>ออกจากระบบ</p></a></li>`;
    }
    html += `</ul></nav></div>`;
    sidebar.innerHTML = html;
}


/* ── Treeview Toggle ── */
function toggleTreeview(link) {
    link.closest('.nav-item').classList.toggle('menu-open');
}

/* ── Sidebar Toggle ── */
function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const mainPanel = document.getElementById('main');

    if (!sidebar || !mainPanel) return;

    if (window.innerWidth >= 992) {
        // Desktop: Toggle Mini vs Full
        sidebar.classList.toggle('collapsed');
        mainPanel.classList.toggle('expanded');
    } else {
        // Mobile: Toggle Show/Hide
        sidebar.classList.toggle('mobile-show');
        const backdrop = document.getElementById('sidebar-backdrop');
        if (backdrop) backdrop.classList.toggle('show');
    }
}

/* ── Auth ── */
async function checkAuth(expectedRole) {
    try {
        const [meRes, settingsRes] = await Promise.all([
            fetch('../api/me.php'),
            fetch('../api/settings.php').catch(() => null)
        ]);

        if (!meRes.ok) { window.location.href = '../'; return null; }
        const data = await meRes.json();
        if (data.csrf_token) window.cnpSeedCsrfToken(data.csrf_token);

        let sysSettings = {};
        if (settingsRes && settingsRes.ok) {
            const setJson = await settingsRes.json();
            if (setJson.data) sysSettings = setJson.data;
        }

        let roles = [];
        if (expectedRole) roles = Array.isArray(expectedRole) ? expectedRole : [expectedRole];

        let hasAccess = false;
        if (roles.length === 0) {
            hasAccess = true;
        } else {
            if (roles.includes(data.user.role)) {
                hasAccess = true;
            }
            // ผู้ดูแลระบบนิเทศ (role=teacher) เข้าหน้า admin supervision ได้
            if (roles.includes('admin') && data.user.role === 'teacher' && data.user.is_supervision_manager) {
                hasAccess = true;
            }
        }

        if (!hasAccess) {
            window.location.href = '../';
            return null;
        }
        window._cnpRole = data.user.role;
        renderSidebar(data.user.role, data.user, sysSettings);
        renderHeader(data.user.role, data.user, sysSettings);
        renderFooter(sysSettings);

        if (data.user.role === 'student' && !data.user.is_profile_complete) {
            const currentPage = window.location.pathname.split('/').pop() || 'student_dashboard.html';
            if (currentPage !== 'student_profile.html') {
                if (typeof Swal === 'undefined') {
                    await new Promise((resolve) => {
                        const link = document.createElement('link');
                        link.rel = 'stylesheet';
                        link.href = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
                        document.head.appendChild(link);

                        const script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                        script.onload = () => resolve();
                        document.body.appendChild(script);
                    });
                }
                
                await Swal.fire({
                    icon: 'warning',
                    title: 'กรุณากรอกประวัติส่วนตัวให้ครบถ้วน',
                    text: 'กรุณาบันทึกแก้ไขข้อมูลโปรไฟล์ของท่านให้เรียบร้อยทุกช่องก่อน จึงจะสามารถเข้าดูอย่างอื่นได้',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    confirmButtonText: 'ไปหน้าแก้ไขโปรไฟล์'
                });
                window.location.href = 'student_profile.html';
                return null;
            } else {
                if (!sessionStorage.getItem('cnp_profile_tip_shown')) {
                    sessionStorage.setItem('cnp_profile_tip_shown', '1');
                    if (typeof Swal === 'undefined') {
                        await new Promise((resolve) => {
                            const link = document.createElement('link');
                            link.rel = 'stylesheet';
                            link.href = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
                            document.head.appendChild(link);

                            const script = document.createElement('script');
                            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                            script.onload = () => resolve();
                            document.body.appendChild(script);
                        });
                    }
                    Swal.fire({
                        icon: 'info',
                        title: 'คำแนะนำการบันทึกข้อมูล',
                        text: 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง (หากช่องใดไม่มีข้อมูลให้ใส่เครื่องหมาย "-" เท่านั้น) จากนั้นกดปุ่ม "บันทึกข้อมูล" ด้านล่าง เพื่อเปิดสิทธิ์การใช้งานส่วนอื่น ๆ ของระบบ',
                        confirmButtonText: 'รับทราบ'
                    });
                }
            }
        } else if (data.user.role === 'teacher' && !data.user.is_profile_complete) {
            const currentPage = window.location.pathname.split('/').pop() || 'teacher_dashboard.html';
            if (currentPage !== 'teacher_profile.html' && currentPage !== 'teacher_security.html') {
                if (typeof Swal === 'undefined') {
                    await new Promise((resolve) => {
                        const link = document.createElement('link');
                        link.rel = 'stylesheet';
                        link.href = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
                        document.head.appendChild(link);

                        const script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                        script.onload = () => resolve();
                        document.body.appendChild(script);
                    });
                }

                const pct = data.user.profile_completion_pct ?? 0;
                const filled = data.user.profile_filled_count ?? 0;
                const total = data.user.profile_required_total ?? 0;
                await Swal.fire({
                    icon: 'warning',
                    title: '⚠️ บังคับอัปเดตข้อมูลส่วนตัว (ช่วงนิเทศการสอน)',
                    html: `ข้อมูลของคุณกรอกแล้ว <strong style="color:#d97706">${pct}%</strong> (${filled}/${total} รายการ)<br><br>ต้องการอย่างน้อย <strong>90%</strong> จึงจะสามารถใช้งานระบบได้<br><span style="font-size:0.9em;color:#64748b">กรุณากรอกข้อมูลที่ยังขาดให้ครบ แล้วกดบันทึก</span>`,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    confirmButtonColor: '#d97706',
                    confirmButtonText: 'ไปอัปเดตข้อมูลเลย →'
                });
                window.location.href = 'teacher_profile.html';
                return null;
            } else {
                if (!sessionStorage.getItem('cnp_teacher_profile_tip_shown')) {
                    sessionStorage.setItem('cnp_teacher_profile_tip_shown', '1');
                    if (typeof Swal === 'undefined') {
                        await new Promise((resolve) => {
                            const link = document.createElement('link');
                            link.rel = 'stylesheet';
                            link.href = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
                            document.head.appendChild(link);

                            const script = document.createElement('script');
                            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                            script.onload = () => resolve();
                            document.body.appendChild(script);
                        });
                    }
                    const pct2 = data.user.profile_completion_pct ?? 0;
                    Swal.fire({
                        icon: 'warning',
                        title: `⚠️ ข้อมูลครบ ${pct2}% — ต้องการ 90%`,
                        html: 'กรุณากรอกข้อมูลที่ยังขาดให้ครบ แล้วกดปุ่ม <strong>"บันทึกและอัปเดตข้อมูล"</strong> ด้านล่าง เพื่อเปิดสิทธิ์การใช้งานระบบ',
                        confirmButtonColor: '#d97706',
                        confirmButtonText: 'รับทราบ'
                    });
                }
            }
        }

        if (data.user && data.user.role === 'admin') {
            setTimeout(initAdminBookingSelector, 100);
        }

        return data.user;
    } catch (err) {
        window.location.href = '../';
        return null;
    }
}

function logout() {
    fetch('../api/logout.php').then(() => window.location.href = '../');
}

/* ── System settings fetcher (used by views that need settings outside of checkAuth) ── */
async function fetchSettings() {
    try {
        const res = await fetch('../api/settings.php');
        if (!res.ok) return {};
        const json = await res.json();
        return json.data || json.settings || json || {};
    } catch (e) {
        return {};
    }
}

/* ── House Badge Helper ── */
function getHouseBadge(houseName) {
    if (!houseName) return '-';
    let name = houseName.trim().replace('ขุุน', 'ขุน');
    let clz = 'badge bg-light text-dark border'; // default
    if (name === 'ขุนสรรค์') clz = 'badge badge-house-khunsan rounded-pill px-3 fw-normal';
    else if (name === 'ขุนศรี') clz = 'badge badge-house-khunsri rounded-pill px-3 fw-normal';
    else if (name === 'เจ้ายี่') clz = 'badge badge-house-chaoyee rounded-pill px-3 fw-normal';
    else if (name === 'ธรรมจักร') clz = 'badge badge-house-dhammachak rounded-pill px-3 fw-normal';
    return `<span class="${clz}">${name}</span>`;
}
/* ── Formatter Helpers ── */
function formatPhone(phone) {
    if (!phone) return '-';
    let p = phone.toString().trim();
    if (p.length >= 8 && !p.startsWith('0')) {
        p = '0' + p;
    }
    return p;
}

function thDate(dateInput, includeTime = false) {
    if (!dateInput) return '-';
    const date = new Date(dateInput);
    if (isNaN(date.getTime())) return dateInput;

    const thMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    const d = date.getDate();
    const m = thMonths[date.getMonth()];
    const y = date.getFullYear() + 543;

    let str = `${d} ${m} ${y}`;
    if (includeTime) {
        const h = date.getHours().toString().padStart(2, '0');
        const min = date.getMinutes().toString().padStart(2, '0');
        str += ` ${h}:${min} น.`;
    }
    return str;
}

/* ── Global Header Renderer ── */
const SCHEDULE_CONFIG = {
    normal: {
        name: 'คาบปกติ',
        periods: [
            ['08:00', '08:30'], // คาบ 0 (Homeroom)
            ['08:30', '09:25'], ['09:25', '10:20'], ['10:20', '11:15'],
            ['11:15', '12:10'], ['12:10', '13:05'], ['13:05', '14:00'],
            ['14:00', '14:55'], ['14:55', '15:50'], ['15:50', '16:45'],
            ['16:45', '17:40'], ['17:40', '18:35']
        ]
    },
    friday: {
        name: 'คาบทด',
        periods: [
            ['08:00', '08:30'], // คาบ 0 (Homeroom)
            ['09:00', '09:50'], ['09:50', '10:40'], ['10:40', '11:35'],
            ['11:35', '12:25'], ['12:25', '13:15'], ['13:15', '14:05'],
            ['14:05', '15:00'], ['15:00', '15:50'], ['15:50', '16:45'],
            ['16:45', '17:35'], ['17:35', '18:25']
        ]
    },
    sport_1: {
        name: 'คาบกีฬาสี',
        periods: [
            ['08:00', '08:30'], // คาบ 0 (Homeroom)
            ['08:30', '09:15'], ['09:15', '10:00'], ['10:00', '10:45'],
            ['10:45', '11:30'], ['11:30', '12:15'], ['12:15', '13:00'],
            ['13:00', '13:45'], ['13:45', '14:30'], ['14:30', '15:15'],
            ['15:15', '16:00'], ['16:00', '16:45']
        ]
    },
    sport_2: {
        name: 'คาบกีฬาสีทด',
        periods: [
            ['08:00', '08:30'], // คาบ 0 (Homeroom)
            ['09:00', '09:45'], ['09:45', '10:30'], ['10:30', '11:15'],
            ['11:15', '12:00'], ['12:00', '12:45'], ['12:45', '13:30'],
            ['13:30', '14:15'], ['14:15', '15:00'], ['15:00', '15:45'],
            ['15:45', '16:30'], ['16:30', '17:15']
        ]
    }
};

function getCurrentPeriod(scheduleKey) {
    const config = SCHEDULE_CONFIG[scheduleKey];
    if (!config) return null;

    const now = new Date();
    const currentTime = now.getHours() * 60 + now.getMinutes();

    for (let i = 0; i < config.periods.length; i++) {
        const [startStr, endStr] = config.periods[i];
        const [sH, sM] = startStr.split(':').map(Number);
        const [eH, eM] = endStr.split(':').map(Number);

        const startTime = sH * 60 + sM;
        const endTime = eH * 60 + eM;

        if (currentTime >= startTime && currentTime < endTime) {
            return i;
        }
    }
    return null;
}

function renderHeader(role, user, settings = {}) {
    const mainPanel = document.querySelector('.main-panel');
    if (!mainPanel || document.getElementById('globalHeader')) return;

    let fullName = 'ผู้ใช้งานระบบ';
    if (user) {
        fullName = user.full_name_th || (user.first_name_th + ' ' + user.last_name_th) || user.username || fullName;
    }

    const scheduleKey = settings.active_schedule || 'normal';
    const scheduleName = SCHEDULE_CONFIG[scheduleKey]?.name || 'คาบปกติ';

    let prefix = (role === 'teacher' || role === 'admin') ? 'ครู' : 'นักเรียน';
    if (role === 'student') prefix = '';

    let displayName = prefix + fullName;

    const headerHtml = `
<nav class="navbar navbar-expand navbar-light bg-white sticky-top shadow-sm px-3" id="globalHeader">
    <div class="container-fluid p-0 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <!-- ปุ่ม Toggle Hamburger -->
            <div id="menu-toggle" class="text-primary me-2" style="cursor: pointer; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: rgba(15, 23, 42, 0.05); border-radius: 10px;" onclick="toggleSidebar();">
                <i class="bi bi-list fs-3"></i>
            </div>
            <div class="ms-3 d-none d-md-block">
                <div class="fw-bold text-dark fs-5" style="line-height: 1.2; letter-spacing: -0.5px;">CNP <span class="text-primary">APP</span></div>
            </div>
        </div>

        <!-- ส่วนด้านขวา: แจ้งเตือน และ ชื่อผู้ใช้ -->
        <div class="d-flex align-items-center gap-3">
            <!-- Notification Bell -->
            <div class="dropdown">
                <a class="nav-link px-0 position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="fetchNotifications()">
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;">
                        <i class="bi bi-bell-fill text-secondary fs-5"></i>
                    </div>
                    <div id="noti-unread-badge" class="noti-badge d-none">0</div>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow border-0 noti-dropdown">
                    <div class="noti-header">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-black mb-0">การแจ้งเตือน</h5>
                            <button class="btn btn-link btn-sm text-decoration-none p-0" onclick="markAllAsRead()">ทำเครื่องหมายว่าอ่านแล้วทั้งหมด</button>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="noti-btn-filter active" id="noti-filter-all" onclick="filterNotifications('all')">ทั้งหมด</button>
                            <button class="noti-btn-filter" id="noti-filter-unread" onclick="filterNotifications('unread')">ยังไม่ได้อ่าน</button>
                        </div>
                    </div>
                    <div id="noti-list-container">
                        <div class="noti-empty">กำลังโหลด...</div>
                    </div>
                    <div class="p-3 text-center border-top">
                        <a href="#" class="small fw-bold text-primary text-decoration-none">ดูการแจ้งเตือนทั้งหมด</a>
                    </div>
                </div>
            </div>

            <div class="dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center px-0" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="${user.photo ? '../' + user.photo : '../public/img/default-avatar.png'}" class="rounded-circle avatar-img shadow-sm" width="36" height="36" style="object-fit:cover; border: 2px solid white;" alt="User">
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 rounded-4 p-0 overflow-hidden" style="min-width: 260px; animation: dropdownFade .2s ease-out;">
                    <li class="p-4 text-center position-relative" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);">
                        <img src="${user.photo ? '../' + user.photo : '../public/img/default-avatar.png'}" class="rounded-circle shadow-sm border border-3 border-white mb-2" width="64" height="64" style="object-fit:cover;" alt="User">
                        <h6 class="mb-0 fw-bold text-white">${user.first_name_th || 'ไม่ระบุชื่อ'} ${user.last_name_th || ''}</h6>
                        <div class="small text-white-50 mt-1">${role === 'student' ? '<i class="bi bi-mortarboard me-1"></i> นักเรียน' + (user.student_id ? ' (' + user.student_id + ')' : '') : role === 'teacher' ? '<i class="bi bi-person-workspace me-1"></i> บุคลากรครู' : '<i class="bi bi-shield-lock me-1"></i> ผู้ดูแลระบบ'}</div>
                    </li>
                    <li class="px-3 py-2 bg-light text-center border-bottom">
                        <small class="text-muted fw-bold" style="font-size: 0.75rem;"><i class="bi bi-calendar3 me-1"></i> ภาคเรียนที่ ${settings.current_semester || '1'}/${settings.current_academic_year || '2569'}</small>
                    </li>
                    <li class="p-2">
                        <a class="dropdown-item py-2 rounded-3 fw-medium mb-1 transition-all" href="${role === 'admin' ? 'admin_profile.html' : role === 'teacher' ? 'teacher_profile.html' : 'student_profile.html'}">
                            <i class="bi bi-person-circle text-primary me-2"></i> ข้อมูลส่วนตัว
                        </a>
                        <a class="dropdown-item py-2 rounded-3 text-danger fw-bold transition-all" href="#" onclick="event.preventDefault(); logout();">
                            <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
    `;
    mainPanel.insertAdjacentHTML('afterbegin', headerHtml);

    // Clean up duplicate hamburger menus from the legacy headers
    document.querySelectorAll('.app-content-header .fa-bars, .app-content-header .fa-bars-staggered, .app-content-header .bi-list').forEach(icon => {
        let btn = icon.closest('button, .btn, a');
        if (btn) btn.remove();
    });

    // Sidebar Clock & Period Status Update
    function updateClock() {
        const sidebarDate = document.getElementById('sidebar-date');
        const sidebarTime = document.getElementById('sidebar-time');
        const specialDayContainer = document.getElementById('special-day-container');

        const now = new Date();
        const d = now.getDate();
        const m = now.getMonth() + 1;
        const y = now.getFullYear();

        // Check for Special Days (Holidays)
        const holidays = {
            '1-1': 'วันขึ้นปีใหม่',
            '16-1': 'วันครู',
            '6-4': 'วันจักรี',
            '13-4': 'วันสงกรานต์',
            '14-4': 'วันสงกรานต์',
            '15-4': 'วันสงกรานต์',
            '1-5': 'วันแรงงานแห่งชาติ',
            '4-5': 'วันฉัตรมงคล',
            '28-7': 'วันเฉลิมพระชนมพรรษา ร.10',
            '12-8': 'วันแม่แห่งชาติ',
            '13-10': 'วันคล้ายวันสวรรคต ร.9',
            '23-10': 'วันปิยมหาราช',
            '5-12': 'วันพ่อแห่งชาติ',
            '10-12': 'วันรัฐธรรมนูญ',
            '31-12': 'วันสิ้นปี'
        };

        const key = `${d}-${m}`;
        if (specialDayContainer) {
            if (holidays[key]) {
                specialDayContainer.innerHTML = `<span class="special-day-badge"><i class="bi bi-star-fill me-1"></i> ${holidays[key]}</span>`;
            } else {
                specialDayContainer.innerHTML = '';
            }
        }

        const thMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        const thDays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

        const dateStr = `วัน${thDays[now.getDay()]}ที่ ${d} ${thMonths[now.getMonth()]} ${y + 543}`;
        const timeStr = now.toLocaleTimeString('th-TH', { hour12: false });

        if (sidebarDate) sidebarDate.textContent = dateStr;
        if (sidebarTime) sidebarTime.textContent = timeStr + ' น.';

        const periodEl = document.getElementById('current-period-status');
        if (periodEl) {
            // อ่านค่า schedule ปัจจุบัน — ใช้ค่าจาก window.__cnpActiveSchedule ที่อัปเดตจาก settings.php
            const activeSchedule = window.__cnpActiveSchedule || settings.active_schedule || 'normal';
            const cfg = SCHEDULE_CONFIG[activeSchedule] || SCHEDULE_CONFIG['normal'];
            const cfgName = cfg ? cfg.name : 'คาบปกติ';
            const currentPeriod = getCurrentPeriod(activeSchedule);
            periodEl.innerHTML = currentPeriod != null
                ? `<span class="text-primary fw-bold"><i class="bi bi-clock-history me-1"></i> ${cfgName} · คาบ ${currentPeriod}</span>`
                : `<span><i class="bi bi-moon-stars me-1"></i> ${cfgName} · นอกเวลาเรียน</span>`;
        }
    }

    // Seed initial value
    window.__cnpActiveSchedule = settings.active_schedule || 'normal';
    updateClock();
    setInterval(updateClock, 1000);

    // Refresh schedule from server every 60s — กรณี admin เปลี่ยน schedule
    setInterval(async () => {
        try {
            const r = await fetch('../api/settings.php', { credentials: 'same-origin' });
            if (!r.ok) return;
            const j = await r.json();
            const newSchedule = (j && j.data && j.data.active_schedule) || null;
            if (newSchedule && newSchedule !== window.__cnpActiveSchedule) {
                window.__cnpActiveSchedule = newSchedule;
            }
        } catch (e) { /* swallow */ }
    }, 60000);
}

/* ── Global Footer Renderer ── */
function renderFooter(settings = {}) {
    const mainPanel = document.querySelector('.main-panel');
    if (!mainPanel || document.getElementById('globalFooter')) return;

    const schName = settings.school_name || 'โรงเรียนชัยนาทพิทยาคม';
    const schAffiliation = settings.school_affiliation || 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาอุทัยธานี ชัยนาท';
    const schAddress = settings.school_address || 'เลขที่ 55/30 ถนนลูกเสือ 1 ตำบลบ้านกล้วย อำเภอเมืองชัยนาท จังหวัดชัยนาท 17000';
    const schPhone = settings.school_phone || '056-411645';
    const schEmail = settings.school_email || 'natchanan@chainatpit.ac.th';

    const footerHtml = `
<footer id="globalFooter" class="app-footer text-center text-md-start mt-auto">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-5 mb-3 mb-md-0">
                <div class="d-flex flex-column">
                    <span class="fw-bold text-dark mb-1" style="font-size: 0.8rem;">
                        2026 
                        <a href="#" class="text-decoration-none footer-link fw-bold" style="color: #1e3c72;">
                            CNPAPP<sup>©</sup>
                        </a>
                        ระบบสารสนเทศนักเรียน${schName}
                    </span>
                    <span class="text-secondary" style="font-size: 0.8rem;">
                        แอปพลิเคชันเพื่อการบริหารจัดการข้อมูล${schName}
                    </span>
                </div>
            </div>

            <div class="col-md-7 text-md-end">
                <div class="mb-1 text-secondary" style="font-size: 0.75rem;">
                    ${schAffiliation}<br>
                    งานพิธีการและประชาสัมพันธ์การศึกษา : ผู้พัฒนา ${schEmail}
                </div>
                
                <div class="d-inline-flex flex-wrap align-items-center gap-3 justify-content-center justify-content-md-end text-secondary mb-1" style="font-size: 0.75rem;">
                    <span class="d-flex align-items-center text-nowrap" style="color: #3b82f6;">
                        <i class="bi bi-telephone-fill me-1"></i> ${schPhone}
                    </span>
                    
                    <a href="mailto:${schEmail}" target="_blank" class="text-decoration-none d-flex align-items-center text-nowrap" style="color: #3b82f6;">
                        <i class="bi bi-envelope-fill me-1"></i> Email
                    </a>
                    
                    <span class="text-muted opacity-50 d-none d-md-inline">|</span>
                    
                    <a href="https://cnp.clubth.com/privacy-policy" class="text-decoration-none text-secondary text-nowrap d-flex align-items-center">
                        <i class="bi bi-shield-check me-1"></i> นโยบายคุ้มครองข้อมูลส่วนบุคคล
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>
    `;
    mainPanel.insertAdjacentHTML('beforeend', footerHtml);
}

/* ── Notification Logic ── */
let lastUnreadCount = 0;

function showNotificationPopup(id) {
    const n = (window._notiDataMap || {})[String(id)];
    if (!n) { markAsRead(id); return; }

    const colorToIcon = c => {
        if (!c) return 'info';
        if (c.includes('dc35') || c.includes('e11d') || c.includes('ef44')) return 'warning';
        if (c.includes('16a3') || c.includes('059669') || c.includes('22c5')) return 'success';
        return 'info';
    };
    const swalIcon = (n.type === 'system_update') ? 'info'
        : (n.type === 'warning' || n.type === 'error') ? n.type
        : colorToIcon(n.color || '');

    // ลบออกจาก DOM ทันที — ไม่รอ API
    const notiEl = document.querySelector(`.noti-item[data-id="${id}"]`);
    if (notiEl) notiEl.remove();
    // อัปเดต badge ทันที
    const remaining = document.querySelectorAll('#noti-list-container .noti-item').length;
    updateNotiBadge(remaining);
    if (remaining === 0) {
        const c = document.getElementById('noti-list-container');
        if (c) c.innerHTML = '<div class="noti-empty">ไม่มีการแจ้งเตือน</div>';
    }

    Swal.fire({
        icon: swalIcon,
        title: n.title || 'การแจ้งเตือน',
        html: `<div style="text-align:left;line-height:1.8;font-size:0.95rem;">${n.message || ''}</div>`,
        confirmButtonText: 'รับทราบ',
        confirmButtonColor: '#1e3a8a',
        allowOutsideClick: true,
    }).then(() => {
        markAsRead(id);
    });
}

async function fetchNotifications() {
    try {
        const res = await fetch('../api/notifications.php');
        const json = await res.json();
        if (json.success) {
            // Apply localStorage read states to dynamic alerts
            const readAlerts = JSON.parse(localStorage.getItem('cnp_read_alerts') || '[]');
            const dismissed  = JSON.parse(localStorage.getItem('cnp_dismissed_alerts') || '[]');
            window._notiDataMap = {};
            let unreadCount = 0;
            json.data.forEach(n => {
                window._notiDataMap[String(n.id)] = n;
                if (typeof n.id === 'string' && n.id.startsWith('alert_')) {
                    if (readAlerts.includes(n.id) || dismissed.includes(n.id)) {
                        n.is_read = 1;
                    }
                }
                if (dismissed.includes(String(n.id))) {
                    n.is_read = 1; // treat dismissed DB notifications as read
                }
                if (n.is_read == 0 || n.is_read === '0') {
                    unreadCount++;
                }
            });

            renderNotifications(json.data);
            updateNotiBadge(unreadCount);
            updateSidebarActionDots(json.data); // Inject glowing indicators

            // Check if there is an unread urgent attendance reminder for teacher
            if (window._cnpRole === 'teacher') {
                const urgentNoti = json.data.find(n => 
                    (n.is_read == 0 || n.is_read === '0') && 
                    n.title && n.title.includes('เตือนให้เช็คชื่อโฮมรูมประจำวัน')
                );

                if (urgentNoti) {
                    markAsRead(urgentNoti.id); // Mark as read immediately to prevent spam
                    
                    const currentPage = window.location.pathname.split('/').pop();
                    const triggerAlert = () => {
                        if (currentPage !== 'attendance_daily.html') {
                            if (!window.__cnpUrgentReminderShowing) {
                                window.__cnpUrgentReminderShowing = true;
                                Swal.fire({
                                    icon: 'error',
                                    title: 'คำสั่งด่วนที่สุด! ⚠️',
                                    text: 'ผู้ดูแลระบบส่งคำเตือนด่วนถึงคุณ: กรุณาเช็คชื่อนักเรียนในที่ปรึกษาโดยด่วนที่สุด!',
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,
                                    confirmButtonText: 'ไปหน้าเช็คชื่อทันที',
                                    confirmButtonColor: '#dc3545'
                                }).then(() => {
                                    window.__cnpUrgentReminderShowing = false;
                                    window.location.href = 'attendance_daily.html';
                                });
                            }
                        } else {
                            if (!window.__cnpUrgentReminderShowing) {
                                window.__cnpUrgentReminderShowing = true;
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'คำเตือนด่วนที่สุดจากแอดมิน! ⚠️',
                                    text: 'กรุณาเช็คชื่อนักเรียนโฮมรูมประจำวันของท่านโดยด่วนที่สุด!',
                                    confirmButtonText: 'ตกลง',
                                    confirmButtonColor: '#ffc107'
                                }).then(() => {
                                    window.__cnpUrgentReminderShowing = false;
                                });
                            }
                        }
                    };

                    if (window.Swal) {
                        triggerAlert();
                    } else {
                        const script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                        script.onload = triggerAlert;
                        document.head.appendChild(script);
                    }
                }
            }
        }
    } catch (e) { console.error("Error fetching notifications", e); }
}

function updateNotiBadge(count) {
    const badge = document.getElementById('noti-unread-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 9 ? '9+' : count;
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }
    }

    // Update sidebar badge too!
    const sidebarBadge = document.getElementById('sidebar-noti-badge');
    if (sidebarBadge) {
        if (count > 0) {
            sidebarBadge.textContent = count > 9 ? '9+' : count;
            sidebarBadge.style.display = 'inline-block';
        } else {
            sidebarBadge.style.display = 'none';
        }
    }
    
    lastUnreadCount = count;
}

function dismissSystemAlert(id, event) {
    event.preventDefault();
    event.stopPropagation();
    const dismissed = JSON.parse(localStorage.getItem('cnp_dismissed_alerts') || '[]');
    if (!dismissed.includes(id)) dismissed.push(id);
    localStorage.setItem('cnp_dismissed_alerts', JSON.stringify(dismissed));
    const el = event.target.closest('.noti-item');
    if (el) el.remove();
    const badge = document.getElementById('noti-unread-badge');
    if (badge && !badge.classList.contains('d-none')) {
        const next = (parseInt(badge.textContent) || 1) - 1;
        if (next <= 0) badge.classList.add('d-none');
        else badge.textContent = next > 9 ? '9+' : next;
    }
}

function renderNotifications(items) {
    const container = document.getElementById('noti-list-container');
    if (!container) return;

    const dismissed = JSON.parse(localStorage.getItem('cnp_dismissed_alerts') || '[]');

    // Admin ไม่เห็น alert รอรับรองสาธา; กรองเฉพาะที่ยังไม่ได้อ่าน/ปิด
    let filtered = (items || []).filter(n =>
        !(window._cnpRole === 'admin' && n.id === 'alert_ps_pending') &&
        !dismissed.includes(String(n.id)) &&
        (n.is_read == 0 || n.is_read === '0')
    );
    if (filtered.length === 0) {
        container.innerHTML = '<div class="noti-empty">ไม่มีการแจ้งเตือน</div>';
        return;
    }

    let html = '';
    filtered.forEach(n => {
        const isAlert = typeof n.id === 'string' && n.id.startsWith('alert_');
        const color   = n.color || (isAlert ? '#e11d48' : '#0d6efd');

        html += `
            <div data-id="${n.id}" class="noti-item unread" onclick="showNotificationPopup('${n.id}')">
                <div class="noti-icon" style="background: ${color}15; color: ${color};">
                    <i class="${n.icon || 'bi bi-bell'}"></i>
                </div>
                <div class="noti-content">
                    <div class="noti-title">${n.title}</div>
                    <div class="noti-msg">${n.message}</div>
                    <div class="noti-time">${n.time_ago}</div>
                </div>
                <div class="noti-dot"></div>
            </div>
        `;
    });
    container.innerHTML = html;
}

async function markAsRead(id) {
    // เพิ่มใน dismissed เสมอ — อ่านแล้วหายไปไม่กลับมาอีก
    const dismissed = JSON.parse(localStorage.getItem('cnp_dismissed_alerts') || '[]');
    if (!dismissed.includes(String(id))) {
        dismissed.push(String(id));
        localStorage.setItem('cnp_dismissed_alerts', JSON.stringify(dismissed));
    }

    if (typeof id === 'string' && id.startsWith('alert_')) {
        const readAlerts = JSON.parse(localStorage.getItem('cnp_read_alerts') || '[]');
        if (!readAlerts.includes(id)) readAlerts.push(id);
        localStorage.setItem('cnp_read_alerts', JSON.stringify(readAlerts));
        fetchNotifications();
        return;
    }
    try {
        await fetch('../api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', id: id })
        });
        fetchNotifications();
    } catch (e) { console.error(e); }
}

async function markAllAsRead() {
    try {
        const allIds = Array.from(document.querySelectorAll('#noti-list-container .noti-item'))
            .map(el => el.getAttribute('data-id')).filter(Boolean);

        // Dismiss all permanently
        const dismissed = JSON.parse(localStorage.getItem('cnp_dismissed_alerts') || '[]');
        const readAlerts = JSON.parse(localStorage.getItem('cnp_read_alerts') || '[]');
        allIds.forEach(id => {
            if (!dismissed.includes(String(id))) dismissed.push(String(id));
            if (id.startsWith('alert_') && !readAlerts.includes(id)) readAlerts.push(id);
        });
        localStorage.setItem('cnp_dismissed_alerts', JSON.stringify(dismissed));
        localStorage.setItem('cnp_read_alerts', JSON.stringify(readAlerts));

        await fetch('../api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read' })
        });
        fetchNotifications();
    } catch (e) { console.error(e); }
}

function filterNotifications(type) {
    document.querySelectorAll('.noti-btn-filter').forEach(b => b.classList.remove('active'));
    const btn = document.getElementById(`noti-filter-${type}`);
    if (btn) btn.classList.add('active');
    
    const items = document.querySelectorAll('.noti-item');
    if (type === 'unread') {
        items.forEach(it => it.style.display = it.classList.contains('unread') ? 'flex' : 'none');
    } else {
        items.forEach(it => it.style.display = 'flex');
    }
}

/* ── Dynamic Action Indicator Dots for Sidebar ── */
function updateSidebarActionDots(items) {
    // Reset all sidebar action dots
    document.querySelectorAll('.sidebar-action-dot').forEach(el => el.remove());

    if (!items || items.length === 0) return;

    const isUnread = n => (n.is_read == 0 || n.is_read === '0');
    const hasPsPending = items.some(n => n.id === 'alert_ps_pending' && isUnread(n));
    const hasDailyNotChecked = items.some(n => n.id === 'alert_not_checked' && isUnread(n));
    const hasMyPsPending = items.some(n => n.id === 'alert_my_ps_pending' && isUnread(n));
    const hasTodayAttendance = items.some(n => n.id === 'alert_today_attendance' && isUnread(n));

    const dotHtml = `<span class="sidebar-action-dot bg-danger ms-2" style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.4); animation: pulse-dot 1.5s infinite;"></span>`;
    
    // Custom keyframe animation for the pulse effect if not exists
    if (!document.getElementById('sidebar-dot-style')) {
        const style = document.createElement('style');
        style.id = 'sidebar-dot-style';
        style.innerHTML = `
            @keyframes pulse-dot {
                0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
                70% { box-shadow: 0 0 0 5px rgba(239, 68, 68, 0); }
                100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
            }
        `;
        document.head.appendChild(style);
    }

    // 1. Pending Public Service Approvals (Teacher only — Admin doesn't need dot)
    if (hasPsPending && window._cnpRole !== 'admin') {
        const psGroupLink = Array.from(document.querySelectorAll('#mainSidebar .nav-link')).find(el => el.textContent.includes('สาธารณประโยชน์'));
        if (psGroupLink && !psGroupLink.querySelector('.sidebar-action-dot')) {
            const pTag = psGroupLink.querySelector('p');
            if (pTag) pTag.insertAdjacentHTML('beforeend', dotHtml);
        }
        const approveSubLink = Array.from(document.querySelectorAll('#mainSidebar .submenu-container .nav-link')).find(el => el.textContent.includes('รอรับรอง'));
        if (approveSubLink && !approveSubLink.querySelector('.sidebar-action-dot')) {
            const pTag = approveSubLink.querySelector('p');
            if (pTag) pTag.insertAdjacentHTML('beforeend', dotHtml);
        }
    }

    // 2. Attendance Not Checked Today (Teacher)
    if (hasDailyNotChecked) {
        const attGroupLink = Array.from(document.querySelectorAll('#mainSidebar .nav-link')).find(el => el.textContent.includes('เช็คชื่อ'));
        if (attGroupLink && !attGroupLink.querySelector('.sidebar-action-dot')) {
            const pTag = attGroupLink.querySelector('p');
            if (pTag) pTag.insertAdjacentHTML('beforeend', dotHtml);
        }
        const dailySubLink = Array.from(document.querySelectorAll('#mainSidebar .submenu-container .nav-link')).find(el => el.textContent.includes('เช็คชื่อรายวัน'));
        if (dailySubLink && !dailySubLink.querySelector('.sidebar-action-dot')) {
            const pTag = dailySubLink.querySelector('p');
            if (pTag) pTag.insertAdjacentHTML('beforeend', dotHtml);
        }
    }

    // 3. My Pending Public Service Request (Student)
    if (hasMyPsPending) {
        const studentPsLink = Array.from(document.querySelectorAll('#mainSidebar .nav-link')).find(el => el.textContent.includes('สาธารณประโยชน์'));
        if (studentPsLink && !studentPsLink.querySelector('.sidebar-action-dot')) {
            const pTag = studentPsLink.querySelector('p');
            if (pTag) pTag.insertAdjacentHTML('beforeend', dotHtml);
        }
    }

    // 4. Today's Attendance Alert (Student)
    if (hasTodayAttendance) {
        const studentAttLink = Array.from(document.querySelectorAll('#mainSidebar .nav-link')).find(el => el.textContent.includes('ประวัติการมาเรียน'));
        if (studentAttLink && !studentAttLink.querySelector('.sidebar-action-dot')) {
            const pTag = studentAttLink.querySelector('p');
            if (pTag) pTag.insertAdjacentHTML('beforeend', dotHtml);
        }
    }
}

/* ── Mobile-Responsive Notifications Modal ── */
function showNotificationsModal() {
    let modal = document.getElementById('notiModal');
    if (!modal) {
        if (!document.getElementById('_notiModalStyle')) {
            const s = document.createElement('style');
            s.id = '_notiModalStyle';
            s.textContent = `
                #notiModal .modal-content { background:#fff; }
                .nmod-header {
                    background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 100%);
                    color:#fff; padding:18px 22px;
                    display:flex; align-items:center; justify-content:space-between;
                }
                .nmod-title { font-weight:800; font-size:.95rem; display:flex; align-items:center; gap:8px; }
                .nmod-title .nmod-badge {
                    background:rgba(255,255,255,.22); color:#fff;
                    font-size:.65rem; font-weight:700; padding:2px 8px; border-radius:20px;
                    backdrop-filter:blur(4px);
                }
                .nmod-actions { display:flex; align-items:center; gap:14px; }
                .nmod-mark { color:rgba(255,255,255,.85); font-size:.74rem; text-decoration:none; font-weight:600; transition:.2s; cursor:pointer; }
                .nmod-mark:hover { color:#fff; text-decoration:underline; }
                .nmod-close { background:transparent; border:none; color:rgba(255,255,255,.8); font-size:1.1rem; cursor:pointer; padding:0; line-height:1; transition:.2s; }
                .nmod-close:hover { color:#fff; transform:rotate(90deg); }

                .nmod-tabs { display:flex; padding:8px 14px; gap:6px; border-bottom:1px solid #f1f5f9; background:#fafbfc; }
                .nmod-tab {
                    flex:none; padding:6px 14px; border-radius:50px; border:none; background:transparent;
                    font-size:.74rem; font-weight:700; color:#64748b; cursor:pointer; transition:.2s;
                }
                .nmod-tab.active { background:#1e3a8a; color:#fff; box-shadow:0 4px 10px rgba(30,58,138,.25); }
                .nmod-tab:not(.active):hover { background:#e2e8f0; color:#1e293b; }

                .nmod-list { max-height:440px; overflow-y:auto; padding:6px 0; }
                .nmod-list::-webkit-scrollbar { width:6px; }
                .nmod-list::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:3px; }

                .nmi {
                    display:flex; align-items:flex-start; gap:12px;
                    padding:12px 20px 12px 17px;
                    text-decoration:none; color:inherit;
                    position:relative; transition:.2s; cursor:pointer;
                    border-left:3px solid transparent;
                }
                .nmi:hover { background:#f8fafc; transform:translateX(2px); }
                .nmi.unread { background:linear-gradient(90deg,rgba(37,99,235,.04),transparent 60%); border-left-color:#2563eb; }
                .nmi-icon {
                    width:38px; height:38px; border-radius:11px;
                    display:flex; align-items:center; justify-content:center;
                    flex-shrink:0; font-size:1rem;
                }
                .nmi-body { flex:1; min-width:0; }
                .nmi-title { font-weight:700; font-size:.82rem; color:#0f172a; line-height:1.3; margin-bottom:2px; }
                .nmi-msg {
                    font-size:.76rem; color:#64748b; line-height:1.4;
                    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
                }
                .nmi-time { font-size:.68rem; color:#94a3b8; margin-top:5px; display:flex; align-items:center; gap:3px; }
                .nmi-dot { width:7px; height:7px; background:#2563eb; border-radius:50%; flex-shrink:0; margin-top:6px; }

                .nmod-empty {
                    text-align:center; padding:50px 20px; color:#94a3b8;
                }
                .nmod-empty i { font-size:2.5rem; opacity:.3; display:block; margin-bottom:10px; }
                .nmod-empty p { font-size:.82rem; margin:0; font-weight:500; }
            `;
            document.head.appendChild(s);
        }
        const modalHtml = `
        <div class="modal fade" id="notiModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:410px;">
                <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                    <div class="nmod-header">
                        <div class="nmod-title">
                            <i class="bi bi-bell-fill"></i>
                            <span>การแจ้งเตือน</span>
                            <span class="nmod-badge" id="nmodUnreadCount" style="display:none;"></span>
                        </div>
                        <div class="nmod-actions">
                            <a class="nmod-mark" onclick="markAllAsReadModal()">อ่านทั้งหมด</a>
                            <button class="nmod-close" data-bs-dismiss="modal" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                    <div class="nmod-tabs">
                        <button class="nmod-tab active" data-filter="all" onclick="filterModalNotifications('all')">ทั้งหมด</button>
                        <button class="nmod-tab" data-filter="unread" onclick="filterModalNotifications('unread')">ยังไม่ได้อ่าน</button>
                    </div>
                    <div id="noti-modal-list-container" class="nmod-list">
                        <div class="nmod-empty"><i class="bi bi-hourglass-split"></i><p>กำลังโหลด...</p></div>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('notiModal');
    }
    
    // Fetch and render
    fetchModalNotifications();
    
    // Show using bootstrap
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

async function fetchModalNotifications() {
    try {
        const res = await fetch('../api/notifications.php');
        const json = await res.json();
        if (json.success) {
            // Apply localStorage read states to dynamic alerts
            const readAlerts = JSON.parse(localStorage.getItem('cnp_read_alerts') || '[]');
            json.data.forEach(n => {
                if (typeof n.id === 'string' && n.id.startsWith('alert_')) {
                    if (readAlerts.includes(n.id)) {
                        n.is_read = 1;
                    }
                }
            });

            renderModalNotifications(json.data);
            
            // Count total unread to update the badge
            const unreadCount = json.data.filter(n => n.is_read == 0 || n.is_read === '0').length;
            updateNotiBadge(unreadCount);
        }
    } catch (e) { console.error(e); }
}

function renderModalNotifications(items) {
    const container = document.getElementById('noti-modal-list-container');
    if (!container) return;

    // Admin ไม่เห็น alert รอรับรองสาธา (ดูข้อมูลได้แต่ไม่ต้องแจ้งเตือน)
    let filtered = (items || []).filter(n =>
        !(window._cnpRole === 'admin' && n.id === 'alert_ps_pending')
    );

    // อัปเดต unread badge ใน header
    const unreadCount = filtered.filter(n => n.is_read == 0 || n.is_read === '0').length;
    const badge = document.getElementById('nmodUnreadCount');
    if (badge) {
        if (unreadCount > 0) { badge.textContent = unreadCount; badge.style.display = 'inline-block'; }
        else badge.style.display = 'none';
    }

    if (filtered.length === 0) {
        container.innerHTML = `<div class="nmod-empty">
            <i class="bi bi-bell-slash"></i>
            <p>ไม่มีการแจ้งเตือน</p>
        </div>`;
        return;
    }

    container.innerHTML = filtered.map(n => {
        const isUnread = n.is_read == 0 || n.is_read === '0';
        const isAlert = typeof n.id === 'string' && n.id.startsWith('alert_');
        const color = n.color || (isAlert ? '#e11d48' : '#2563eb');
        return `
            <a href="${n.link || '#'}" data-id="${n.id}" class="nmi${isUnread ? ' unread' : ''}"
               onclick="markAsReadModal('${n.id}','${n.link || '#'}')">
                <div class="nmi-icon" style="background:${color}18;color:${color};">
                    <i class="${n.icon || 'bi bi-bell'}"></i>
                </div>
                <div class="nmi-body">
                    <div class="nmi-title">${n.title}</div>
                    <div class="nmi-msg">${n.message}</div>
                    <div class="nmi-time"><i class="bi bi-clock"></i>${n.time_ago}</div>
                </div>
                ${isUnread ? '<span class="nmi-dot"></span>' : ''}
            </a>`;
    }).join('');
}

async function markAsReadModal(id, link) {
    if (typeof id === 'string' && id.startsWith('alert_')) {
        const readAlerts = JSON.parse(localStorage.getItem('cnp_read_alerts') || '[]');
        if (!readAlerts.includes(id)) readAlerts.push(id);
        localStorage.setItem('cnp_read_alerts', JSON.stringify(readAlerts));
        fetchModalNotifications();
        fetchNotifications();
        if (link && link !== '#') {
            window.location.href = link;
        }
        return;
    }
    try {
        await fetch('../api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', id: id })
        });
        fetchModalNotifications();
        fetchNotifications();
        if (link !== '#') {
            window.location.href = link;
        }
    } catch (e) { console.error(e); }
}

async function markAllAsReadModal() {
    try {
        // Mark all dynamic alerts as read in localStorage
        const alerts = Array.from(document.querySelectorAll('#noti-modal-list-container .nmi')).map(el => el.getAttribute('data-id')).filter(id => id && id.startsWith('alert_'));
        if (alerts.length > 0) {
            const readAlerts = JSON.parse(localStorage.getItem('cnp_read_alerts') || '[]');
            alerts.forEach(id => {
                if (!readAlerts.includes(id)) readAlerts.push(id);
            });
            localStorage.setItem('cnp_read_alerts', JSON.stringify(readAlerts));
        }

        await fetch('../api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read' })
        });
        fetchModalNotifications();
        fetchNotifications();
    } catch (e) { console.error(e); }
}

function filterModalNotifications(type) {
    document.querySelectorAll('#notiModal .nmod-tab').forEach(b =>
        b.classList.toggle('active', b.dataset.filter === type)
    );
    const items = document.querySelectorAll('#noti-modal-list-container .nmi');
    let visibleCount = 0;
    items.forEach(it => {
        const show = (type !== 'unread' || it.classList.contains('unread'));
        it.style.display = show ? 'flex' : 'none';
        if (show) visibleCount++;
    });
    // ถ้าไม่มี item ที่ตรงเงื่อนไข ให้แสดง empty state ใน list
    const existingEmpty = document.querySelector('#noti-modal-list-container .nmod-empty-filtered');
    if (visibleCount === 0 && items.length > 0) {
        if (!existingEmpty) {
            const empty = document.createElement('div');
            empty.className = 'nmod-empty nmod-empty-filtered';
            empty.innerHTML = '<i class="bi bi-check-all"></i><p>อ่านครบทุกรายการแล้ว</p>';
            document.getElementById('noti-modal-list-container').appendChild(empty);
        }
    } else if (existingEmpty) {
        existingEmpty.remove();
    }
}

setInterval(fetchNotifications, 60000 * 2); 
setTimeout(fetchNotifications, 1000);   

async function initAdminBookingSelector() {
    const role = window._cnpRole;
    if (role !== 'admin') return;

    const container = document.querySelector('.container-fluid');
    if (!container) return;

    const path = window.location.pathname.split('/').pop();
    const supervisionPages = [
        'supervision_booking.html',
        'supervision_docs.html',
        'supervision_evaluate.html',
        'supervision_post_teach.html',
        'supervision_print.html',
        'supervision_evaluate_doc.html',
        'supervision_evaluate_class.html'
    ];
    if (!supervisionPages.includes(path)) return;

    const backBtn = document.querySelector('a[href="teacher_supervision.html"]');
    if (backBtn) {
        backBtn.setAttribute('href', 'supervision.html');
    }

    try {
        const res = await fetch('../api/admin/supervision_admin.php?action=get_bookings');
        const data = await res.json();
        if (!data.success || !data.bookings) return;

        const urlParams = new URLSearchParams(window.location.search);
        const currentBookingId = parseInt(urlParams.get('booking_id')) || 0;

        const wrapperDiv = document.createElement('div');
        wrapperDiv.id = 'admin-booking-selector-container';
        wrapperDiv.className = 'card border-0 shadow-sm rounded-4 mb-4 no-print';
        
        let optionsHtml = '<option value="">-- เลือกรายการคิวนิเทศของครู --</option>';
        data.bookings.forEach(b => {
            const activeMark = (b.id === currentBookingId) ? 'selected' : '';
            optionsHtml += `<option value="${b.id}" ${activeMark}>${b.teacher_name} - ${b.subject_code} ${b.subject_name} (ม.${b.classroom} คาบ ${b.booking_period}) [${b.status}]</option>`;
        });

        wrapperDiv.innerHTML = `
            <div class="card-body p-3 rounded-4 border border-primary border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-3" style="background-color: #ebf5ff;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock-fill text-primary fs-4"></i>
                    <div>
                        <div class="fw-bold small text-navy">ผู้บริหาร / ผู้ดูแลระบบ</div>
                        <div class="text-muted" style="font-size: 0.72rem;">เข้าถึงข้อมูลและแก้ไขสิทธิ์สำหรับคิวนิเทศนี้</div>
                    </div>
                </div>
                <div style="min-width: 300px; max-width: 100%;">
                    <select id="admin-booking-select" class="form-select border-primary" style="border-radius: 10px; font-size: 0.85rem;" onchange="window.location.search = this.value ? '?booking_id=' + this.value : ''">
                        ${optionsHtml}
                    </select>
                </div>
            </div>
        `;
        
        container.insertBefore(wrapperDiv, container.firstChild);
    } catch (err) {
        console.error("Error creating admin selector:", err);
    }
}

