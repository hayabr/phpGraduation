<?php

include "../connect.php";

// استلام معرف المستخدم
$user_id = filterRequest('user_id');

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

// استعلام لجلب جميع الميزانيات الخاصة بالمستخدم
$stmt = $con->prepare("SELECT budgets.id, budgets.amount, budgets.start_date, budgets.end_date, categories.name AS category_name FROM budgets INNER JOIN categories ON budgets.category_id = categories.id WHERE budgets.user_id = ?");
$stmt->execute(array($user_id));
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($budgets) {
    echo json_encode(["status" => "success", "budgets" => $budgets]);
} else {
    echo json_encode(["status" => "error", "message" => "No budgets found"]);
}

?>