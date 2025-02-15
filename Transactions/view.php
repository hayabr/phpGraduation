<?php 

include "../connect.php";

$userid = filterRequest('user_id');

// استعلام لجلب بيانات المعاملات مع اسم الفئة والأيقونة واسم الحساب
$stmt = $con->prepare("
    SELECT t.id, t.user_id, t.account_id, t.amount, t.type, t.note, t.transaction_date,
           c.name AS category_name, c.icon,
           a.name
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    JOIN accounts a ON t.account_id = a.id
    WHERE t.user_id = ?
");

$stmt->execute(array($userid));
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$count = $stmt->rowCount();

if ($count > 0) {
    echo json_encode(array("status" => "success", "data" => $data));
} else {
    echo json_encode(array("status" => "fail"));
}

?>