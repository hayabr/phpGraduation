<?php 

include "../connect.php";

$group = filterRequest('group'); // نوع الحساب
$name = filterRequest('name');
$amount = filterRequest('amount');
$description = filterRequest('description');
$accountid = filterRequest('id');
$classification = filterRequest('classification'); // استقبال التصنيف مباشرة من الطلب

// جلب معلومات الحساب قبل التعديل
$stmtOld = $con->prepare("SELECT `amount`, `classification`, `user_id` FROM `accounts` WHERE `id` = ?");
$stmtOld->execute(array($accountid));
$oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

if (!$oldData) {
    echo json_encode(["status" => "error", "message" => "Account not found"]);
    exit;
}

$old_amount = $oldData['amount'];
$old_classification = $oldData['classification'];
$userid = $oldData['user_id'];

// تحديث الحساب في جدول `accounts`
$stmt = $con->prepare("UPDATE `accounts` SET `group`=?, `name`=?, `amount`=?, `description`=?, `classification`=? WHERE `id`=?");
$success = $stmt->execute(array($group, $name, $amount, $description, $classification, $accountid));

if ($success) {
    // حساب الفرق بين المبلغ الجديد والقديم
    $difference = $amount - $old_amount;

    if ($old_classification == 'Assets' && $classification == 'Liabilities') {
        $stmtUpdate = $con->prepare("UPDATE `user_accounts` 
            SET `assets` = `assets` - ?, `liabilities` = `liabilities` + ?, `total` = `assets` - `liabilities` 
            WHERE `user_id` = ?");
        $stmtUpdate->execute(array($old_amount, $amount, $userid));
    } elseif ($old_classification == 'Liabilities' && $classification == 'Assets') {
        $stmtUpdate = $con->prepare("UPDATE `user_accounts` 
            SET `liabilities` = `liabilities` - ?, `assets` = `assets` + ?, `total` = `assets` - `liabilities` 
            WHERE `user_id` = ?");
        $stmtUpdate->execute(array($old_amount, $amount, $userid));
    } elseif ($classification == 'Assets') {
        $stmtUpdate = $con->prepare("UPDATE `user_accounts` 
            SET `assets` = `assets` + ?, `total` = `assets` - `liabilities` 
            WHERE `user_id` = ?");
        $stmtUpdate->execute(array($difference, $userid));
    } elseif ($classification == 'Liabilities') {
        $stmtUpdate = $con->prepare("UPDATE `user_accounts` 
            SET `liabilities` = `liabilities` + ?, `total` = `assets` - `liabilities` 
            WHERE `user_id` = ?");
        $stmtUpdate->execute(array($difference, $userid));
    }

    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}

?>