<?php

include "../connect.php";

// استلام معرف الميزانية المراد حذفها
$budget_id = filterRequest('budget_id'); // معرف الميزانية المراد حذفها
$user_id = filterRequest('user_id'); // معرف المستخدم للتحقق من الصلاحية

// التحقق من وجود الميزانية
$stmtCheck = $con->prepare("SELECT * FROM `budgets` WHERE `id` = ? AND `user_id` = ?");
$stmtCheck->execute(array($budget_id, $user_id));
$budgetExists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$budgetExists) {
    echo json_encode(["status" => "error", "message" => "Budget not found or you do not have permission to delete it"]);
    exit;
}

// حذف الميزانية من جدول budgets
$stmt = $con->prepare("DELETE FROM `budgets` WHERE `id` = ? AND `user_id` = ?");
$success = $stmt->execute(array($budget_id, $user_id));

if ($success) {
    echo json_encode(["status" => "success", "message" => "Budget deleted successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error while deleting budget"]);
}

?>