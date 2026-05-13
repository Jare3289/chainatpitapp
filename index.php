<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <title>เข้าสู่ระบบ | ระบบสารสนเทศโรงเรียนชัยนาทพิทยาคม</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />

    <!-- PWA: Add to Home Screen -->
    <link rel="manifest" href="public/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="apple-touch-icon" href="public/img/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CNP App">
    <link rel="icon" type="image/png" sizes="192x192" href="public/img/icons/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="public/img/icons/icon-512.png">

    <!-- Google Sans Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Sarabun:wght@400;500;700;800&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="public/css/style.css">

    <style>
        /* tokens อยู่ใน public/css/tokens.css (โหลดผ่าน style.css ด้านบน) */
        body {
            font-family: var(--font-sans);
            background-color: #f5f3ff;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            font-variation-settings: "GRAD" 0;
            font-optical-sizing: auto;
        }

        /* Main Login Card */
        .login-master-card {
            background: white;
            border-radius: 40px;
            overflow: hidden;
            width: 100%;
            max-width: 1100px;
            min-height: 650px;
            display: flex;
            box-shadow: 0 40px 100px rgba(30, 60, 114, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        /* Left Side: Branding with Navy Gradient */
        .login-branding {
            width: 42%;
            background: #1e3c72;
            background-image: 
                linear-gradient(135deg, #1e3c72 0%, #2a5298 100%),
                repeating-linear-gradient(45deg, rgba(255,255,255,0.03) 0, rgba(255,255,255,0.03) 1px, transparent 0, transparent 50%);
            background-size: cover, 15px 15px;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            color: white;
            position: relative;
        }

        .login-branding::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(225deg, rgba(109, 40, 217, 0.4) 0%, transparent 70%);
            pointer-events: none;
        }

        .login-branding .logo-main {
            width: 100px;
            margin-bottom: 40px;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));
            position: relative;
            z-index: 2;
        }

        .login-branding h1 {
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 15px;
            letter-spacing: -1px;
            position: relative;
            z-index: 2;
        }

        .login-branding p.en-sub {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.7;
            font-weight: 600;
            margin-bottom: 40px;
            position: relative;
            z-index: 2;
        }

        .badge-system {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            width: fit-content;
            margin-bottom: 12px;
            position: relative;
            z-index: 2;
        }

        .badge-system.active {
            background: #dc2626;
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
            animation: pulse-red 2s infinite;
        }
        @keyframes pulse-red {
            0%, 100% { box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4); }
            50% { box-shadow: 0 4px 25px rgba(220, 38, 38, 0.7); }
        }

        /* Secure Connection Box */
        .secure-box {
            margin-top: auto;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 20px;
            position: relative;
            z-index: 2;
        }
        .secure-box .status-dot {
            width: 8px;
            height: 8px;
            background: #60a5fa; 
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
            box-shadow: 0 0 10px #60a5fa;
        }
        .secure-box .title {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #60a5fa;
            margin-bottom: 8px;
            display: block;
        }
        .secure-box p {
            font-size: 0.7rem;
            margin: 0;
            opacity: 0.6;
            line-height: 1.5;
        }

        /* Right Side: Form Area */
        .login-form-side {
            flex: 1;
            padding: 60px 70px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
            position: relative;
        }

        .login-form-side h2 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--navy-700);
            margin-bottom: 10px;
        }

        .login-form-side p.desc {
            color: #64748b;
            font-weight: 500;
            margin-bottom: 40px;
        }

        /* Input Styles */
        .input-group-modern { margin-bottom: 22px; }
        .input-group-modern label { display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 10px; color: #475569; }
        .input-group-modern label span { color: var(--primary); }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper i { position: absolute; left: 20px; color: #94a3b8; font-size: 1.1rem; }
        .input-wrapper input { width: 100%; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 18px; padding: 18px 20px 18px 55px; font-weight: 600; color: #1e293b; transition: 0.3s; }
        .input-wrapper input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1); }

        .btn-signin { background: var(--navy-700); color: white; border: none; border-radius: 18px; padding: 20px; width: 100%; font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 15px; margin-top: 15px; transition: 0.3s; }
        .btn-signin:hover { background: var(--navy-500); transform: translateY(-2px); box-shadow: 0 20px 40px rgba(30, 60, 114, 0.2); }

        .login-footer { margin-top: 35px; text-align: center; }
        .contact-btns { display: flex; justify-content: center; gap: 12px; }
        .contact-btn { padding: 12px 20px; border-radius: 14px; font-size: 0.8rem; font-weight: 700; text-decoration: none !important; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-line { background: #ecfdf5; color: #06c755; }
        .btn-phone { background: var(--primary-soft); color: var(--primary); }

        /* Role Selection Overlay (Updated Hierarchy) */
        .role-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            z-index: 10;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: transform 0.5s cubic-bezier(0.77, 0, 0.175, 1);
        }
        .role-overlay.hidden { transform: translateX(100%); }

        .role-primary-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* Side-by-side squares */
            gap: 20px;
            margin-bottom: 30px;
        }

        .role-item-primary {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            padding: 30px 20px;
            border-radius: 28px;
            display: flex;
            flex-direction: column; /* Icon top, Text bottom */
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            aspect-ratio: 1 / 1; /* Square shape */
        }
        .role-item-primary:hover {
            background: white;
            border-color: var(--primary);
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(30, 60, 114, 0.08);
        }
        .role-item-primary i {
            width: 80px; /* Bigger Icon Container */
            height: 80px;
            background: white;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: var(--navy-500);
            font-size: 2.2rem; /* Much bigger icon */
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            transition: 0.3s;
        }
        .role-item-primary:hover i {
            background: var(--navy-500);
            color: white;
            transform: scale(1.1);
        }
        .role-item-primary .role-text-wrap h5 {
            margin: 0;
            font-weight: 800;
            font-size: 0.95rem; /* Smaller text */
            color: #0f172a;
            line-height: 1.4;
        }
        .role-item-primary .role-text-wrap p {
            margin: 5px 0 0;
            font-size: 0.65rem; /* Much smaller english sub */
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-item-secondary {
            background: #fff;
            border: 1px solid #f1f5f9;
            padding: 12px 20px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: 0.2s;
            max-width: fit-content;
            margin: 0 auto;
            opacity: 0.6;
        }
        .role-item-secondary:hover { opacity: 1; background: #f8fafc; border-color: var(--primary); }
        .role-item-secondary i { font-size: 0.9rem; margin-right: 12px; color: #94a3b8; }
        .role-item-secondary span { font-size: 0.85rem; font-weight: 700; color: #64748b; }

        @media (max-width: 991px) {
            body { padding: 10px; align-items: flex-start; }
            .login-master-card { flex-direction: column; border-radius: 24px; min-height: auto; margin-top: 10px; }
            .login-branding { width: 100%; padding: 30px 20px; min-height: auto; text-align: center; align-items: center; }
            .login-branding .logo-main { width: 60px; margin-bottom: 15px; }
            .login-branding h1 { font-size: 1.5rem; margin-bottom: 8px; }
            .login-branding p.en-sub { font-size: 0.7rem; margin-bottom: 15px; }
            .login-branding .badge-system { display: none; }
            .secure-box { display: none; } /* Hide secure box on mobile to save space */
            
            .login-form-side { padding: 30px 20px; }
            .role-overlay { padding: 30px 20px; }
            .login-form-side h2 { font-size: 1.8rem; text-align: center; }
            .login-form-side p.desc { text-align: center; margin-bottom: 25px; }
        }

        @media (max-width: 480px) {
            .role-primary-container { gap: 10px; }
            .role-item-primary { padding: 15px 10px; border-radius: 20px; }
            .role-item-primary i { width: 50px; height: 50px; font-size: 1.4rem; margin-bottom: 10px; }
            .role-item-primary .role-text-wrap h5 { font-size: 0.8rem; }
            .role-item-primary .role-text-wrap p { font-size: 0.6rem; }
            .btn-signin { padding: 16px; font-size: 1rem; }
        }
    </style>
</head>

<body>
    <div class="login-master-card">
        <!-- Left Branding (True Purple Pattern) -->
        <div class="login-branding">
            <img src="public/img/logo.png" alt="Logo" class="logo-main" onerror="this.style.display='none'">
            <h1>โรงเรียนชัยนาทพิทยาคม</h1>
            <p class="en-sub">Chainat Pitthayakom School Information System</p>
            
            <div class="badge-system"><i class="fas fa-users-gear"></i> ระบบบริหารงานสารสนเทศ</div>
            <div class="badge-system active"><i class="fas fa-flask me-1"></i> ช่วงทดลองใช้ V1</div>

            <div class="secure-box">
                <span class="title"><span class="status-dot"></span> SECURE CONNECTION</span>
                <p>ข้อมูลส่วนบุคคลของท่านถูกจัดเก็บและเข้ารหัสอย่างปลอดภัยตามมาตรฐาน พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล (PDPA)</p>
            </div>
        </div>

        <!-- Right Form Area -->
        <div class="login-form-side position-relative overflow-hidden">
            <!-- Role Selection View -->
            <div id="roleView" class="role-overlay">
                <div class="mb-5 text-center">
                    <h2 class="mb-1">เข้าสู่ระบบ</h2>
                    <p class="desc mb-0">กรุณาเลือกประเภทผู้ใช้งาน</p>
                </div>
                
                <div class="role-primary-container">
                    <div class="role-item-primary" onclick="selectRole('student')">
                        <i class="fas fa-user-graduate"></i>
                        <div class="role-text-wrap">
                            <h5>นักเรียน</h5>
                            <p>STUDENT</p>
                        </div>
                    </div>
                    
                    <div class="role-item-primary" onclick="selectRole('teacher')">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <div class="role-text-wrap">
                            <h5>ครู / บุคลากร</h5>
                            <p>STAFF</p>
                        </div>
                    </div>
                </div>

                <div class="role-item-secondary" onclick="selectRole('admin')">
                    <i class="fas fa-user-shield"></i>
                    <span>สำหรับผู้ดูแลระบบ</span>
                </div>
            </div>

            <!-- Login Form View -->
            <div id="loginView">
                <div class="mb-4">
                    <button class="btn btn-link p-0 text-decoration-none small fw-bold text-muted" onclick="showRoles()">
                        <i class="fas fa-arrow-left me-2"></i> กลับหน้าเลือกประเภท
                    </button>
                </div>
                <h2 id="loginTitle">เข้าสู่ระบบ</h2>
                <p class="desc">กรุณายืนยันตัวตนเพื่อเข้าใช้งานระบบ</p>

                <form id="loginForm">
                    <input type="hidden" id="selectedRole" value="">
                    
                    <div class="input-group-modern">
                        <label id="usernameLabel">ชื่อผู้ใช้งาน <span>*</span></label>
                        <div class="input-wrapper">
                            <i class="far fa-user"></i>
                            <input type="text" id="username" placeholder="ระบุบัญชีผู้ใช้งาน" required>
                        </div>
                    </div>

                    <div class="input-group-modern">
                        <label>รหัสผ่าน <span>*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="form-check d-flex align-items-center gap-2 mb-3">
                        <input class="form-check-input" type="checkbox" id="rememberMe" checked
                               style="width:1.1rem; height:1.1rem; border-color:var(--border); cursor:pointer; flex-shrink:0;">
                        <label class="form-check-label" for="rememberMe"
                               style="font-size:0.85rem; color:var(--text-muted); font-weight:600; cursor:pointer; user-select:none;">
                            จำการเข้าสู่ระบบไว้ <span style="color:var(--text-faint); font-weight:500;">(30 วัน)</span>
                        </label>
                    </div>

                    <div id="errorMsg" class="alert alert-danger border-0 rounded-4 py-3 small mb-4 text-center" style="display: none; background: #fef2f2; color: #b91c1c; font-weight: 700;"></div>

                    <button type="submit" id="submitBtn" class="btn-signin">
                        เข้าสู่ระบบ (Sign In) <i class="fas fa-arrow-right"></i>
                    </button>
                </form>


            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/cookie-pdpa.js"></script>
    <script>
        function selectRole(role) {
            document.getElementById('selectedRole').value = role;
            const titles = { 'student': 'นักเรียน', 'teacher': 'ครู / บุคลากร', 'admin': 'ผู้ดูแลระบบ' };
            const labels = { 'student': 'รหัสประจำตัวนักเรียน', 'teacher': 'บัญชีบุคลากร', 'admin': 'ชื่อผู้ใช้งาน' };
            
            document.getElementById('loginTitle').innerText = 'เข้าใช้งานสำหรับ' + titles[role];
            document.getElementById('usernameLabel').innerHTML = labels[role] + ' <span>*</span>';
            document.getElementById('username').placeholder = 'ระบุ' + labels[role];
            
            document.getElementById('roleView').classList.add('hidden');
        }

        function showRoles() {
            document.getElementById('roleView').classList.remove('hidden');
            document.getElementById('errorMsg').style.display = 'none';
        }

        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const role     = document.getElementById('selectedRole').value;
            const remember = document.getElementById('rememberMe').checked;
            const errorMsg = document.getElementById('errorMsg');
            const submitBtn = document.getElementById('submitBtn');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> กำลังเข้าสู่ระบบ...';

            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password, role, remember })
                });
                const data = await response.json();

                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorMsg.innerText = data.error || 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง';
                    errorMsg.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'เข้าสู่ระบบ (Sign In) <i class="fas fa-arrow-right"></i>';
                }
            } catch (err) {
                errorMsg.innerText = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้';
                errorMsg.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'เข้าสู่ระบบ (Sign In) <i class="fas fa-arrow-right"></i>';
            }
        });
    </script>
</body>
</html>
