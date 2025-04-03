<?php

include "../connect.php";

// استلام بيانات الميزانية للتعديل
$budget_id = filterRequest('budget_id'); // معرف الميزانية المراد تعديلها
$user_id = filterRequest('user_id'); 
$category_id = filterRequest('category_id');
$amount = filterRequest('amount');
$start_date = filterRequest('start_date'); // يمكن أن يكون فارغًا
$end_date = filterRequest('end_date');   // يمكن أن يكون فارغًا

// التحقق من وجود الميزانية
$stmtCheck = $con->prepare("SELECT * FROM `budgets` WHERE `id` = ? AND `user_id` = ?");
$stmtCheck->execute(array($budget_id, $user_id));
$budgetExists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$budgetExists) {
    echo json_encode(["status" => "error", "message" => "Budget not found or you do not have permission to edit it"]);
    exit;
}

// تحديث الميزانية في جدول budgets
$stmt = $con->prepare("UPDATE `budgets` SET `category_id` = ?, `amount` = ?, `start_date` = ?, `end_date` = ? WHERE `id` = ? AND `user_id` = ?");
$success = $stmt->execute(array($category_id, $amount, $start_date, $end_date, $budget_id, $user_id));

if ($success) {
    echo json_encode(["status" => "success", "message" => "Budget updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error while updating budget"]);
}

?>