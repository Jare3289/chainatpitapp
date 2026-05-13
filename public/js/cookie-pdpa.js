/**
 * cookie-pdpa.js - ระบบแจ้งเตือนคุกกี้ (PDPA) แบบ Premium Design
 * ติดตั้งง่าย เพียงเพิ่ม <script src="public/js/cookie-pdpa.js"></script> ในหน้า HTML
 */

(function() {
    // 1. ตรวจสอบว่าเคยยอมรับไปแล้วหรือยัง
    if (localStorage.getItem('cookie_accepted') === 'true') {
        return;
    }

    // 2. สร้าง CSS แบบ Injected เพื่อให้ไม่ต้องแก้ไฟล์ CSS แยก
    const style = document.createElement('style');
    style.innerHTML = `
        .pdpa-banner {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            width: 90%;
            max-width: 600px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            padding: 24px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            z-index: 99999;
            transition: all 0.6s cubic-bezier(0.19, 1, 0.22, 1);
            opacity: 0;
            font-family: "Google Sans", "Sarabun", sans-serif;
            font-variation-settings: "GRAD" 0;
            font-optical-sizing: auto;
        }
        .pdpa-banner.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        .pdpa-content {
            flex: 1;
            padding-right: 20px;
        }
        .pdpa-content h5 {
            margin: 0 0 5px 0;
            font-weight: 800;
            color: #1e3c72;
            font-size: 1.1rem;
        }
        .pdpa-content p {
            margin: 0;
            font-size: 0.85rem;
            color: #475569;
            line-height: 1.5;
            font-weight: 500;
        }
        .pdpa-content a {
            color: #db2777;
            text-decoration: none;
            font-weight: 700;
        }
        .pdpa-content a:hover {
            text-decoration: underline;
        }
        .pdpa-actions {
            display: flex;
            gap: 12px;
        }
        .pdpa-btn {
            padding: 12px 24px;
            border-radius: 14px;
            font-size: 0.9rem;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            border: none;
            white-space: nowrap;
        }
        .btn-pdpa-accept {
            background: #1e3c72;
            color: white;
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.2);
        }
        .btn-pdpa-accept:hover {
            background: #2a5298;
            transform: scale(1.05);
        }
        .btn-pdpa-decline {
            background: transparent;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .btn-pdpa-decline:hover {
            background: #f1f5f9;
        }

        @media (max-width: 600px) {
            .pdpa-banner {
                flex-direction: column;
                text-align: center;
                padding: 20px;
                bottom: 20px;
            }
            .pdpa-content {
                padding-right: 0;
                margin-bottom: 20px;
            }
            .pdpa-actions {
                width: 100%;
                justify-content: center;
            }
            .pdpa-btn {
                flex: 1;
            }
        }
    `;
    document.head.appendChild(style);

    // 3. สร้าง HTML Elements
    const banner = document.createElement('div');
    banner.className = 'pdpa-banner';
    banner.innerHTML = `
        <div class="pdpa-content">
            <h5>🍪 คุกกี้และความเป็นส่วนตัว</h5>
            <p>เราใช้คุกกี้ที่จำเป็นเพื่อให้ระบบลงทะเบียนและเข้าใช้งานฟีเจอร์ต่างๆ ทำงานได้อย่างสมบูรณ์ การใช้งานต่อถือเป็นการยอมรับ <a href="#">นโยบายความเป็นส่วนตัว</a> ของเรา</p>
        </div>
        <div class="pdpa-actions">
            <button class="pdpa-btn btn-pdpa-decline" id="pdpaDecline">ปฏิเสธ</button>
            <button class="pdpa-btn btn-pdpa-accept" id="pdpaAccept">ยอมรับทั้งหมด</button>
        </div>
    `;
    document.body.appendChild(banner);

    // 4. แสดงผล Banner หลังจากโหลดหน้า 1 วินาที (เพื่อให้ดูพรีเมียม)
    setTimeout(() => {
        banner.classList.add('show');
    }, 1000);

    // 5. จัดการ Event การกดปุ่ม
    document.getElementById('pdpaAccept').addEventListener('click', function() {
        localStorage.setItem('cookie_accepted', 'true');
        banner.classList.remove('show');
        setTimeout(() => banner.remove(), 600);
    });

    document.getElementById('pdpaDecline').addEventListener('click', function() {
        banner.classList.remove('show');
        setTimeout(() => banner.remove(), 600);
    });

})();
