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
