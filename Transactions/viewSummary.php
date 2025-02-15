<?php 

include "../connect.php";

$userid = filterRequest('user_id');

// استعلام لجلب بيانات ملخص المعاملات للمستخدم
$stmt = $con->prepare("
    SELECT user_id, income, expenses, total 
    FROM user_transactions_summary
    WHERE user_id = ?
");

$stmt->execute(array($userid));
$data = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $stmt->rowCount();

if ($count > 0) {
    echo json_encode(array("status" => "success", "data" => $data));
} else {
    echo json_encode(array("status" => "fail"));
}

?>