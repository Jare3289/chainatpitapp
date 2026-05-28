/**
 * importModal.js — Reusable Excel/CSV import modal with duplicate detection
 * Usage: initImportModal({ type: 'student' | 'teacher', apiUrl, templateUrl, onSuccess })
 */

function initImportModal({ type, apiUrl, templateUrl, templateBaseUrl, onSuccess }) {
    // templateBaseUrl supersedes templateUrl (enables dual-version buttons)
    const _templateBase = templateBaseUrl || (templateUrl ? templateUrl.replace(/[?&]fields=\w+/, '') : '../api/admin/download-template.php?type=' + type);
    const isStudent    = type === 'student';
    const isTeacher    = type === 'teacher';
    const isTimetable  = type === 'timetable';
    const isCredit     = type === 'credit';
    const isClass      = type === 'class';
    
    let label = 'ตารางสอน';
    let icon = '📅';
    
    if (isStudent) { label = 'นักเรียน'; icon = '🎓'; }
    else if (isTeacher) { label = 'ครูและบุคลากร'; icon = '👩‍🏫'; }
    else if (isCredit) { label = 'คะแนนความประพฤติ'; icon = '📊'; }
    else if (isClass) { label = 'จัดการห้องเรียน'; icon = '🏫'; }
    else if (isTimetable) { label = 'ตารางสอน'; icon = '📅'; }

    // Inject modal HTML once
    if (!document.getElementById('cnp-import-modal')) {
        document.body.insertAdjacentHTML('beforeend', `
        <div class="modal fade" id="cnp-import-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg" style="border-radius:28px; overflow:hidden;">
                    <div class="modal-header border-0 p-4 pb-0">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3 fs-4">📥</div>
                            <div>
                                <h5 class="fw-black text-navy mb-0" id="import-modal-title">นำเข้าข้อมูล</h5>
                                <p class="text-muted small mb-0">นำเข้าข้อมูลจากไฟล์ Excel (.xlsx) หรือ CSV (.csv)</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">

                        <!-- Step 1: Instructions -->
                        <div id="import-step-1">
                            <div class="bg-info bg-opacity-10 border border-info border-opacity-25 rounded-4 p-3 mb-4">
                                <div class="fw-bold text-info mb-2"><i class="bi bi-info-circle-fill me-2"></i>ขั้นตอนการนำเข้า</div>
                                <ol class="mb-0 ps-3 small text-secondary" style="line-height:2;">
                                    <li>ดาวน์โหลดไฟล์ต้นฉบับ (Template) — เลือกเวอร์ชันด้านล่าง</li>
                                    <li>กรอกข้อมูล<span id="import-label-inline" class="fw-bold"></span> (ห้ามเปลี่ยนหัวคอลัมน์)</li>
                                    <li>อัปโหลดไฟล์กลับมาที่นี่ ระบบจะตรวจสอบข้อมูลซ้ำให้อัตโนมัติ</li>
                                </ol>
                            </div>

                            <!-- Template version picker -->
                            <div class="mb-4">
                                <p class="fw-bold small text-secondary mb-2"><i class="bi bi-download me-1"></i>ดาวน์โหลด Template — เลือกเวอร์ชัน:</p>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <a id="import-template-minimal" href="#" download
                                           class="d-block text-decoration-none border border-2 border-primary rounded-3 p-3 text-center position-relative"
                                           style="transition:.2s;" onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background=''">
                                            <div class="fs-2 mb-1">📋</div>
                                            <div class="fw-black text-primary" style="font-size:.9rem;">เวอร์ชันลำลอง</div>
                                            <div class="text-muted" style="font-size:.72rem; line-height:1.4;">เฉพาะข้อมูลจำเป็น<br>รหัส · ชื่อ · ห้อง · ชั้น</div>
                                            <span class="badge bg-primary position-absolute top-0 end-0 m-2" style="font-size:.6rem;">MINIMAL</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a id="import-template-full" href="#" download
                                           class="d-block text-decoration-none border border-2 border-success rounded-3 p-3 text-center position-relative"
                                           style="transition:.2s;" onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background=''">
                                            <div class="fs-2 mb-1">📊</div>
                                            <div class="fw-black text-success" style="font-size:.9rem;">เวอร์ชันเต็ม</div>
                                            <div class="text-muted" style="font-size:.72rem; line-height:1.4;">ทุกคอลัมน์ในระบบ<br>สุขภาพ · ที่อยู่ · ติดต่อ</div>
                                            <span class="badge bg-success position-absolute top-0 end-0 m-2" style="font-size:.6rem;">FULL</span>
                                        </a>
                                    </div>
                                </div>
                                <p class="text-muted mt-2 mb-0" style="font-size:.72rem;">
                                    <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                                    แนะนำ: ใช้ <strong>เวอร์ชันลำลอง</strong> สำหรับนำเข้าครั้งแรก แล้วอัปเดตข้อมูลเพิ่มเติมทีหลัง
                                </p>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold">เลือกไฟล์ Excel หรือ CSV <span class="text-danger">*</span></label>
                                    <input type="file" id="import-file-input" class="form-control border-0 bg-light rounded-3" accept=".xlsx,.xls,.csv">
                                    <div class="form-text">รองรับ .xlsx, .xls, .csv (ขนาดสูงสุด 100 MB)</div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <button class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                                <button class="btn btn-primary rounded-pill px-5 fw-bold" id="import-submit-btn" onclick="importRunCheck()">
                                    <i class="bi bi-search me-2"></i>ตรวจสอบข้อมูล
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Duplicate Review -->
                        <div id="import-step-2" class="d-none">
                            <div id="import-summary" class="rounded-4 p-3 mb-3 bg-light border"></div>
                            <div id="import-dup-table-wrap" class="d-none">
                                <h6 class="fw-black text-danger mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>พบข้อมูลซ้ำในระบบ</h6>
                                <p class="small text-muted mb-3">รายการด้านล่างมีรหัสซ้ำกับข้อมูลในระบบ ระบบจะ<strong>ข้ามรายการเหล่านี้</strong>เมื่อนำเข้าจริง หากต้องการอัปเดตให้แก้ไขแต่ละรายการด้วยตนเอง</p>
                                <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>รหัส</th>
                                                <th>ชื่อในระบบ</th>
                                                <th>ชื่อในไฟล์</th>
                                                <th>ห้อง (ระบบ)</th>
                                                <th>ห้อง (ไฟล์)</th>
                                                <th>สถานะ</th>
                                            </tr>
                                        </thead>
                                        <tbody id="import-dup-tbody"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <button class="btn btn-light rounded-pill px-4" onclick="importGoBack()"><i class="bi bi-arrow-left me-2"></i>กลับ</button>
                                <button class="btn btn-success rounded-pill px-5 fw-bold" onclick="importConfirm()">
                                    <i class="bi bi-cloud-upload me-2"></i>ยืนยันนำเข้าข้อมูล
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Progress -->
                        <div id="import-step-3" class="d-none text-center py-4">
                            <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;"></div>
                            <h5 class="fw-black text-navy">กำลังนำเข้าข้อมูล...</h5>
                            <p class="text-muted small">กรุณารอสักครู่ ห้ามปิดหน้าต่าง</p>
                        </div>

                    </div>
                </div>
            </div>
        </div>`);
    }

    // Set dynamic content
    document.getElementById('import-modal-title').textContent = `นำเข้าข้อมูล${label} ${icon}`;
    document.getElementById('import-label-inline').textContent = label;

    // Set both template download links
    const sep = _templateBase.includes('?') ? '&' : '?';
    document.getElementById('import-template-full').style.display = '';
    
    if (isTimetable) {
        document.getElementById('import-template-minimal').href = _templateBase + sep + 'fields=minimal';
        document.getElementById('import-template-minimal').querySelector('.fw-black').textContent = 'ฉบับย่อ (Minimal)';
        document.getElementById('import-template-minimal').querySelector('.text-muted').textContent = 'ชื่อครู · วัน · คาบ · วิชา';
        
        document.getElementById('import-template-full').href = _templateBase + sep + 'fields=full';
        document.getElementById('import-template-full').querySelector('.fw-black').textContent = 'ฉบับเต็ม (Full)';
        document.getElementById('import-template-full').querySelector('.text-muted').textContent = 'รหัสวิชา · ชั้น/ห้อง · ปีการศึกษา · ภาคเรียน';
    } else {
        document.getElementById('import-template-minimal').href = _templateBase + sep + 'fields=minimal';
        document.getElementById('import-template-minimal').querySelector('.fw-black').textContent = 'ฉบับย่อ (Minimal)';
        document.getElementById('import-template-full').href    = _templateBase + sep + 'fields=full';
        document.getElementById('import-template-full').querySelector('.fw-black').textContent = 'ฉบับเต็ม (Full)';
    }

    document.getElementById('import-file-input').value = '';

    // Handle dynamic direct-run / no dry-run option for behavior credit scores
    const submitBtn = document.getElementById('import-submit-btn');
    if (isCredit) {
        submitBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>นำเข้าข้อมูลทันที';
        submitBtn.className = 'btn btn-success rounded-pill px-5 fw-bold';
        submitBtn.onclick = function() {
            const fileInput = document.getElementById('import-file-input');
            if (!fileInput.files[0]) {
                Swal.fire({ icon: 'warning', title: 'กรุณาเลือกไฟล์ก่อน', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                return;
            }
            importConfirm();
        };
    } else {
        submitBtn.innerHTML = '<i class="bi bi-search me-2"></i>ตรวจสอบข้อมูล';
        submitBtn.className = 'btn btn-primary rounded-pill px-5 fw-bold';
        submitBtn.onclick = importRunCheck;
    }

    window.__importApiUrl = apiUrl;
    window.__importCallback = onSuccess;

    showImportStep(1);
    const modalEl = document.getElementById('cnp-import-modal');
    const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modalInstance.show();
}

function showImportStep(n) {
    [1, 2, 3].forEach(i => document.getElementById(`import-step-${i}`).classList.toggle('d-none', i !== n));
}

async function importRunCheck() {
    const fileInput = document.getElementById('import-file-input');
    if (!fileInput.files[0]) {
        Swal.fire({ icon: 'warning', title: 'กรุณาเลือกไฟล์ก่อน', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
        return;
    }
    showImportStep(3);

    const fd = new FormData();
    fd.append('excelFile', fileInput.files[0]);
    fd.append('dry_run', '1');

    try {
        const res = await fetch(window.__importApiUrl, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.error) { Swal.fire('ผิดพลาด', data.error, 'error'); showImportStep(1); return; }

        // Build summary
        const dups     = data.duplicates || [];
        const inserted = data.inserted ?? data.imported ?? 0;
        const updated  = data.updated  ?? dups.length;
        const deleted  = data.deleted  ?? 0;
        const sumHtml = `
            <div class="row g-2 text-center">
                <div class="col-3"><div class="bg-success bg-opacity-10 rounded-3 p-3">
                    <div class="fw-black text-success fs-3">${inserted}</div>
                    <div class="small fw-bold text-muted">เพิ่มใหม่</div>
                </div></div>
                <div class="col-3"><div class="bg-warning bg-opacity-10 rounded-3 p-3">
                    <div class="fw-black text-warning fs-3">${updated}</div>
                    <div class="small fw-bold text-muted">จะอัปเดต</div>
                </div></div>
                <div class="col-3"><div class="bg-danger bg-opacity-10 rounded-3 p-3">
                    <div class="fw-black text-danger fs-3">${deleted}</div>
                    <div class="small fw-bold text-muted">จะลบออก</div>
                </div></div>
                <div class="col-3"><div class="bg-secondary bg-opacity-10 rounded-3 p-3">
                    <div class="fw-black text-secondary fs-3">${inserted + updated}</div>
                    <div class="small fw-bold text-muted">รวมคงเหลือ</div>
                </div></div>
            </div>`;
        document.getElementById('import-summary').innerHTML = sumHtml;

        if (dups.length > 0) {
            document.getElementById('import-dup-table-wrap').classList.remove('d-none');
            const dupHeading = document.querySelector('#import-dup-table-wrap h6');
            if (dupHeading) dupHeading.innerHTML = '<i class="bi bi-arrow-repeat me-2 text-warning"></i>รายการที่จะอัปเดตข้อมูล';
            const dupNote = document.querySelector('#import-dup-table-wrap p.small');
            if (dupNote) dupNote.innerHTML = 'รายการด้านล่างมีรหัสซ้ำกับข้อมูลในระบบ — ระบบจะ<strong>อัปเดตทันที</strong>เมื่อยืนยันนำเข้า';
            document.getElementById('import-dup-tbody').innerHTML = dups.map(d => `
                <tr class="${d.is_different ? 'table-warning' : ''}">
                    <td class="fw-bold">${d.student_id || d.teacher_id || '-'}</td>
                    <td>${d.old_name}</td>
                    <td>${d.new_name}</td>
                    <td>${d.old_class || '-'}</td>
                    <td>${d.new_class || '-'}</td>
                    <td>${d.is_different ? '<span class="badge bg-warning text-dark">ข้อมูลต่างกัน</span>' : '<span class="badge bg-secondary">เหมือนกัน</span>'}</td>
                </tr>`).join('');
        } else {
            document.getElementById('import-dup-table-wrap').classList.add('d-none');
        }

        // ── Will-delete table ──
        const willDelete   = data.will_delete || [];
        let delWrap = document.getElementById('import-del-table-wrap');
        if (!delWrap) {
            document.getElementById('import-dup-table-wrap').insertAdjacentHTML('afterend', `
            <div id="import-del-table-wrap" class="mt-3 d-none">
                <h6 class="fw-black text-danger mb-2"><i class="bi bi-trash3-fill me-2"></i>รายการที่จะถูกลบออกจากระบบ</h6>
                <p class="small text-muted mb-3">นักเรียนด้านล่างไม่พบในไฟล์ที่อัปโหลด — ระบบจะ<strong class="text-danger">ลบออกถาวร</strong>เมื่อยืนยัน (ยกเว้นรายที่มีข้อมูลเช็คชื่อหรือคะแนนความประพฤติผูกอยู่)</p>
                <div class="table-responsive" style="max-height:240px; overflow-y:auto;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-danger sticky-top">
                            <tr><th>รหัส</th><th>ชื่อ</th><th>ห้อง</th></tr>
                        </thead>
                        <tbody id="import-del-tbody"></tbody>
                    </table>
                </div>
            </div>`);
            delWrap = document.getElementById('import-del-table-wrap');
        }
        if (willDelete.length > 0) {
            delWrap.classList.remove('d-none');
            document.getElementById('import-del-tbody').innerHTML = willDelete.map(d => `
                <tr class="table-danger">
                    <td class="fw-bold">${d.student_id || '-'}</td>
                    <td>${d.name || '-'}</td>
                    <td>${d.class_name || '-'}</td>
                </tr>`).join('');
        } else {
            delWrap.classList.add('d-none');
        }

        showImportStep(2);
    } catch (e) {
        Swal.fire('ผิดพลาด', e.message, 'error');
        showImportStep(1);
    }
}

function importGoBack() { showImportStep(1); }

async function importConfirm() {
    const fileInput = document.getElementById('import-file-input');
    showImportStep(3);
    const fd = new FormData();
    fd.append('excelFile', fileInput.files[0]);
    fd.append('dry_run', '0');

    try {
        const res = await fetch(window.__importApiUrl, { method: 'POST', body: fd });
        const data = await res.json();
        const modalEl = document.getElementById('cnp-import-modal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
        if (data.success) {
            let errorDetails = '';
            if (data.errors && data.errors.length > 0) {
                errorDetails = `<div class="mt-2 text-danger small" style="max-height:150px; overflow-y:auto; border-top:1px dashed #ef4444; padding-top:8px;">
                    <strong>ข้อผิดพลาดบางรายการ:</strong><br>
                    ${data.errors.join('<br>')}
                </div>`;
            }
            const delFailed = data.delete_failed || 0;
            await Swal.fire({
                icon: 'success',
                title: 'นำเข้าสำเร็จ!',
                html: `<div class="text-start">
                    <div>✅ เพิ่มใหม่: <strong>${data.inserted || 0}</strong> รายการ</div>
                    <div>🔄 อัปเดต: <strong>${data.updated || data.duplicate_count || 0}</strong> รายการ</div>
                    <div>🗑️ ลบออก: <strong>${data.deleted || 0}</strong> รายการ${delFailed ? ` <span class="text-warning">(ลบไม่ได้ ${delFailed} รายการ — มีข้อมูลผูกพัน)</span>` : ''}</div>
                    ${errorDetails}
                </div>`,
                confirmButtonText: 'ตกลง'
            });
            if (typeof window.__importCallback === 'function') window.__importCallback();
        } else {
            Swal.fire('ผิดพลาด', data.error || 'เกิดข้อผิดพลาด', 'error');
        }
    } catch (e) {
        Swal.fire('ผิดพลาด', e.message, 'error');
    }
}
