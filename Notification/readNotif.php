<?php
include "../connect.php";

try {
    // استقبال معرف المستخدم
    $user_id = filterRequest('user_id');

    if (empty($user_id)) {
        throw new Exception("User ID is required.");
    }

    // جلب الإشعارات الخاصة بالمستخدم
    $stmt = $con->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "notifications" => $notifications]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>