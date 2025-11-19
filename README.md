คู่มือการติดตั้งระบบ IT Service Portal (Complete Edition)

1. โครงสร้างไฟล์ (File Structure)

กรุณาสร้างไฟล์ตามโครงสร้างนี้ในโฟลเดอร์ Web Server ของคุณ (เช่น htdocs/it_service):

it_service/
├── api/
│   ├── save_ticket.php     (API: แจ้งซ่อมใหม่)
│   └── update_ticket.php   (API: อัปเดตสถานะ - สำหรับเจ้าหน้าที่)
├── db_connect.php          (Config: เชื่อมต่อฐานข้อมูล)
├── index.php               (Frontend: หน้าจอหลักรวมทุกฟังก์ชัน)
└── database.sql            (Database: โครงสร้างตาราง)


2. ฐานข้อมูล (database.sql)

Run คำสั่ง SQL นี้ใน phpMyAdmin:

CREATE DATABASE IF NOT EXISTS it_service_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE it_service_db;

CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(20) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    department VARCHAR(50) NOT NULL,
    category VARCHAR(50) NOT NULL,
    details TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'approved', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ข้อมูลทดสอบ
INSERT INTO tickets (ticket_no, subject, department, category, priority, status, created_at) VALUES
('TK-2309001', 'Printer กระดาษติด', 'ACC', 'Printer', 'medium', 'pending', NOW()),
('TK-2309002', 'WiFi ชั้น 3 ใช้งานไม่ได้', 'IT', 'Network', 'high', 'in_progress', NOW());


3. การเชื่อมต่อฐานข้อมูล (db_connect.php)

<?php
$servername = "localhost";
$username = "root";        // แก้ไข username ของคุณ
$password = "";            // แก้ไข password ของคุณ
$dbname = "it_service_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>


4. API (Backend Logic)

4.1 บันทึกการแจ้งซ่อม (api/save_ticket.php)

<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Generate ID: TK-YYMMxxx
        $ym = date('ym');
        $stmt = $conn->query("SELECT COUNT(*) FROM tickets WHERE ticket_no LIKE 'TK-$ym%'");
        $count = $stmt->fetchColumn();
        $ticketNo = "TK-$ym" . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        $sql = "INSERT INTO tickets (ticket_no, subject, department, category, priority, details, status) 
                VALUES (:t_no, :subj, :dept, :cat, :prio, :det, 'pending')";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':t_no' => $ticketNo,
            ':subj' => $data['subject'],
            ':dept' => $data['department'],
            ':cat' => $data['category'],
            ':prio' => $data['priority'],
            ':det' => $data['details']
        ]);

        echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>


4.2 อัปเดตสถานะ (api/update_ticket.php)

ไฟล์ใหม่: ใช้สำหรับเปลี่ยนสถานะงานซ่อม

<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $sql = "UPDATE tickets SET status = :status WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':status' => $data['status'],
            ':id' => $data['id']
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>


5. หน้าจอหลัก (index.php)

โค้ดฉบับเต็ม: รวม PHP Fetch Data และ UI ทั้งหมด

<?php
require_once 'db_connect.php';

// 1. ดึง Stats
$stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM tickets WHERE MONTH(created_at) = MONTH(CURRENT_DATE())")->fetchColumn(),
    'pending' => $conn->query("SELECT COUNT(*) FROM tickets WHERE status IN ('pending')")->fetchColumn(),
    'inprogress' => $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'in_progress'")->fetchColumn(),
    'completed' => $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'completed'")->fetchColumn()
];

// 2. ดึงรายการทั้งหมด (สำหรับหน้า My Tickets และ Manage)
$stmt = $conn->query("SELECT * FROM tickets ORDER BY created_at DESC");
$allTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. ดึงข้อมูลกราฟ (7 วันย้อนหลัง)
$chartDataNew = [];
$chartDates = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartDates[] = date('d/m', strtotime($date));
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $chartDataNew[] = $stmt->fetchColumn();
}

// Helper Function: แสดงสถานะสวยงาม
function getStatusBadge($status) {
    switch($status) {
        case 'pending': return '<span class="px-2.5 py-1 rounded-lg text-xs font-semibold border bg-orange-50 text-orange-600 border-orange-100">รอดำเนินการ</span>';
        case 'in_progress': return '<span class="px-2.5 py-1 rounded-lg text-xs font-semibold border bg-blue-50 text-blue-600 border-blue-100">กำลังซ่อม</span>';
        case 'approved': return '<span class="px-2.5 py-1 rounded-lg text-xs font-semibold border bg-purple-50 text-purple-600 border-purple-100">อนุมัติแล้ว</span>';
        case 'completed': return '<span class="px-2.5 py-1 rounded-lg text-xs font-semibold border bg-emerald-50 text-emerald-600 border-emerald-100">เสร็จสิ้น</span>';
        default: return '-';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Service Portal</title>
    <script src="[https://cdn.tailwindcss.com](https://cdn.tailwindcss.com)"></script>
    <link href="[https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap](https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap)" rel="stylesheet">
    <link rel="stylesheet" href="[https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css](https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css)">
    <script src="[https://cdn.jsdelivr.net/npm/apexcharts](https://cdn.jsdelivr.net/npm/apexcharts)"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Prompt', 'sans-serif'] }, colors: { brand: { 500: '#0ea5e9', 600: '#0284c7' } } } }
        }
    </script>
    <style>.fade-in { animation: fadeIn 0.3s ease-in-out; } @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }</style>
</head>
<body class="bg-slate-50 text-slate-800 h-screen flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-white border-r border-slate-200 flex flex-col hidden md:flex z-30">
        <div class="h-16 flex items-center px-6 border-b border-slate-100 text-brand-600 font-bold text-xl gap-2">
            <i class="fa-solid fa-laptop-medical"></i> IT Service
        </div>
        <nav class="flex-1 py-6 px-3 space-y-1">
            <p class="px-3 text-xs font-semibold text-slate-400 uppercase mb-2">เมนูหลัก</p>
            <button onclick="switchPage('dashboard')" id="nav-dashboard" class="nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium bg-brand-50 text-brand-600"><i class="fa-solid fa-chart-pie w-5"></i> ภาพรวมระบบ</button>
            <button onclick="switchPage('create-ticket')" id="nav-create-ticket" class="nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="fa-solid fa-circle-plus w-5"></i> แจ้งซ่อมใหม่</button>
            <button onclick="switchPage('my-tickets')" id="nav-my-tickets" class="nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="fa-solid fa-list-check w-5"></i> รายการทั้งหมด</button>
            
            <p class="px-3 text-xs font-semibold text-slate-400 uppercase mt-6 mb-2">สำหรับเจ้าหน้าที่</p>
            <button onclick="switchPage('manage-tickets')" id="nav-manage-tickets" class="nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50"><i class="fa-solid fa-screwdriver-wrench w-5"></i> จัดการงานซ่อม</button>
        </nav>
        <div class="p-4 border-t border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-brand-100 text-brand-600 flex items-center justify-center"><i class="fa-solid fa-user"></i></div>
            <div><p class="text-sm font-semibold">คุณไพโรจน์</p><p class="text-xs text-slate-500">IT Manager</p></div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col h-full relative overflow-hidden">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 z-20">
            <h2 class="text-lg font-bold text-slate-800" id="page-title">ภาพรวมระบบ</h2>
            <span class="text-sm text-slate-500"><?php echo date('d F Y'); ?></span>
        </header>

        <div class="flex-1 overflow-y-auto p-6" id="main-container">
            
            <!-- 1. DASHBOARD -->
            <div id="page-dashboard" class="page-content fade-in">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <!-- Stat Cards -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <div class="flex justify-between mb-4"><div class="p-3 bg-blue-100 text-blue-600 rounded-xl"><i class="fa-solid fa-ticket text-xl"></i></div></div>
                        <h3 class="text-3xl font-bold"><?php echo $stats['total']; ?></h3><p class="text-slate-500 text-sm">งานทั้งหมด (เดือนนี้)</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <div class="flex justify-between mb-4"><div class="p-3 bg-orange-100 text-orange-600 rounded-xl"><i class="fa-solid fa-clock text-xl"></i></div></div>
                        <h3 class="text-3xl font-bold"><?php echo $stats['pending']; ?></h3><p class="text-slate-500 text-sm">รอดำเนินการ</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <div class="flex justify-between mb-4"><div class="p-3 bg-blue-100 text-blue-600 rounded-xl"><i class="fa-solid fa-tools text-xl"></i></div></div>
                        <h3 class="text-3xl font-bold"><?php echo $stats['inprogress']; ?></h3><p class="text-slate-500 text-sm">กำลังซ่อม</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <div class="flex justify-between mb-4"><div class="p-3 bg-emerald-100 text-emerald-600 rounded-xl"><i class="fa-solid fa-check-circle text-xl"></i></div></div>
                        <h3 class="text-3xl font-bold"><?php echo $stats['completed']; ?></h3><p class="text-slate-500 text-sm">เสร็จสิ้น</p>
                    </div>
                </div>
                
                <!-- Chart & Table -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <h3 class="font-bold text-lg mb-4">สถิติการแจ้งซ่อม (7 วันล่าสุด)</h3>
                        <div id="activityChart" class="w-full h-64"></div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 font-bold">รายการล่าสุด</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach (array_slice($allTickets, 0, 5) as $ticket): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($ticket['ticket_no']); ?></td>
                                        <td class="px-4 py-3 text-slate-500 truncate max-w-[150px]"><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                        <td class="px-4 py-3 text-right"><?php echo getStatusBadge($ticket['status']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. CREATE TICKET -->
            <div id="page-create-ticket" class="page-content hidden fade-in max-w-3xl mx-auto">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
                    <h3 class="font-bold text-xl mb-6">แจ้งซ่อมอุปกรณ์ใหม่</h3>
                    <form id="repairForm" onsubmit="submitTicket(event)" class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold mb-2">แผนก</label>
                                <select name="department" class="w-full p-3 border rounded-xl bg-slate-50" required>
                                    <option value="IT">IT</option><option value="HR">HR</option><option value="ACC">Account</option><option value="SALE">Sale</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">ประเภท</label>
                                <select name="category" class="w-full p-3 border rounded-xl bg-slate-50" required>
                                    <option value="Hardware">Hardware</option><option value="Software">Software</option><option value="Network">Network</option><option value="Printer">Printer</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">หัวข้อปัญหา</label>
                            <input type="text" name="subject" class="w-full p-3 border rounded-xl bg-slate-50" required placeholder="เช่น เปิดคอมไม่ติด">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">รายละเอียด</label>
                            <textarea name="details" rows="3" class="w-full p-3 border rounded-xl bg-slate-50"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">ความเร่งด่วน</label>
                            <div class="flex gap-4">
                                <label><input type="radio" name="priority" value="low" class="mr-2">ทั่วไป</label>
                                <label><input type="radio" name="priority" value="medium" checked class="mr-2">ปานกลาง</label>
                                <label><input type="radio" name="priority" value="high" class="mr-2 text-red-500">ด่วน</label>
                            </div>
                        </div>
                        <div class="pt-4 flex justify-end gap-3">
                            <button type="button" onclick="switchPage('dashboard')" class="px-6 py-2 border rounded-xl">ยกเลิก</button>
                            <button type="submit" class="px-6 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 shadow-lg">ส่งแจ้งซ่อม</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 3. MY TICKETS (READ ONLY) -->
            <div id="page-my-tickets" class="page-content hidden fade-in">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <h3 class="font-bold text-lg">รายการแจ้งซ่อมทั้งหมด</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b">
                                <tr>
                                    <th class="px-6 py-4">ID</th>
                                    <th class="px-6 py-4">หัวข้อ</th>
                                    <th class="px-6 py-4">แผนก</th>
                                    <th class="px-6 py-4">วันที่</th>
                                    <th class="px-6 py-4">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($allTickets as $ticket): ?>
                                <tr>
                                    <td class="px-6 py-4 font-medium"><?php echo $ticket['ticket_no']; ?></td>
                                    <td class="px-6 py-4"><?php echo $ticket['subject']; ?></td>
                                    <td class="px-6 py-4"><?php echo $ticket['department']; ?></td>
                                    <td class="px-6 py-4 text-slate-500"><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></td>
                                    <td class="px-6 py-4"><?php echo getStatusBadge($ticket['status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 4. MANAGE TICKETS (ADMIN UPDATE) -->
            <div id="page-manage-tickets" class="page-content hidden fade-in">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 bg-slate-800 text-white flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-lg">จัดการงานซ่อม (Admin)</h3>
                            <p class="text-slate-400 text-sm">เปลี่ยนสถานะงานซ่อมได้ที่นี่</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b">
                                <tr>
                                    <th class="px-6 py-4">ID</th>
                                    <th class="px-6 py-4">หัวข้อ</th>
                                    <th class="px-6 py-4">สถานะปัจจุบัน</th>
                                    <th class="px-6 py-4">จัดการสถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($allTickets as $ticket): ?>
                                <tr id="row-<?php echo $ticket['id']; ?>">
                                    <td class="px-6 py-4 font-medium"><?php echo $ticket['ticket_no']; ?></td>
                                    <td class="px-6 py-4"><?php echo $ticket['subject']; ?></td>
                                    <td class="px-6 py-4 status-cell"><?php echo getStatusBadge($ticket['status']); ?></td>
                                    <td class="px-6 py-4">
                                        <select onchange="updateStatus(<?php echo $ticket['id']; ?>, this.value)" class="border border-slate-300 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-brand-500">
                                            <option value="pending" <?php echo $ticket['status']=='pending'?'selected':''; ?>>รอ</option>
                                            <option value="in_progress" <?php echo $ticket['status']=='in_progress'?'selected':''; ?>>กำลังซ่อม</option>
                                            <option value="completed" <?php echo $ticket['status']=='completed'?'selected':''; ?>>เสร็จสิ้น</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Scripts -->
    <script>
        // 1. Switch Pages
        function switchPage(pageId) {
            document.querySelectorAll('.page-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('page-' + pageId).classList.remove('hidden');
            
            // Update Sidebar
            document.querySelectorAll('.nav-item').forEach(el => {
                el.classList.remove('bg-brand-50', 'text-brand-600');
                el.classList.add('text-slate-600');
            });
            const active = document.getElementById('nav-' + pageId);
            if(active) { active.classList.remove('text-slate-600'); active.classList.add('bg-brand-50', 'text-brand-600'); }
            
            const titles = {'dashboard':'ภาพรวมระบบ', 'create-ticket':'แจ้งซ่อมใหม่', 'my-tickets':'รายการทั้งหมด', 'manage-tickets':'จัดการงานซ่อม'};
            document.getElementById('page-title').innerText = titles[pageId] || 'IT Service';
        }

        // 2. Submit Ticket
        async function submitTicket(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            
            if(confirm('ยืนยันการแจ้งซ่อม?')) {
                try {
                    const res = await fetch('api/save_ticket.php', {
                        method: 'POST', headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(data)
                    });
                    const result = await res.json();
                    if(result.success) { alert('บันทึกสำเร็จ!'); window.location.reload(); }
                    else { alert('Error: ' + result.message); }
                } catch(err) { alert('เชื่อมต่อ Server ไม่ได้'); }
            }
        }

        // 3. Update Status (Admin)
        async function updateStatus(id, newStatus) {
            try {
                const res = await fetch('api/update_ticket.php', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id, status: newStatus })
                });
                const result = await res.json();
                if(result.success) {
                    // Update UI badge without reload
                    /* Reloading is safer to see changes everywhere, but let's reload for simplicity */
                    window.location.reload();
                } else { alert('Update failed'); }
            } catch(err) { alert('Connection failed'); }
        }

        // 4. Init Chart
        document.addEventListener('DOMContentLoaded', function() {
            var options = {
                series: [{ name: 'งานซ่อมใหม่', data: <?php echo json_encode($chartDataNew); ?> }],
                chart: { height: 250, type: 'area', toolbar: {show:false}, fontFamily: 'Prompt, sans-serif' },
                colors: ['#0ea5e9'],
                xaxis: { categories: <?php echo json_encode($chartDates); ?> },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2 } }
            };
            new ApexCharts(document.querySelector("#activityChart"), options).render();
        });
    </script>
</body>
</html>
