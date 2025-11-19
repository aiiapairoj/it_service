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