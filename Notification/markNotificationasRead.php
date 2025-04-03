<?php
include "../connect.php";

try {
    $user_id = filterRequest('user_id');

    if (empty($user_id)) {
        throw new Exception("User ID is required.");
    }

    $stmt = $con->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);

    echo json_encode(["status" => "success", "message" => "Notifications marked as read."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>