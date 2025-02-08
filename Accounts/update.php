<?php 

include "../connect.php";

$group = filterRequest('group'); // نوع الحساب
$name = filterRequest('name');
$amount = filterRequest('amount');
$description = filterRequest('description');
$accountid = filterRequest('id');

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

// تحديد التصنيف الجديد بناءً على نوع الحساب
$classification = $old_classification; // الاحتفاظ بالقيمة الأصلية افتراضيًا
if ($group == 'Cash' || $group == 'Card' || $group == 'Debit Card' || $group == 'Savings' || $group == 'Investments') {
    $classification = 'assets';
} elseif ($group == 'Overdrafts' || $group == 'Insurance' || $group == 'Loan') {
    $classification = 'liabilities';
}

// تحديث الحساب في جدول `accounts`
$stmt = $con->prepare("UPDATE `accounts` SET `group`=?, `name`=?, `amount`=?, `description`=?, `classification`=? WHERE `id`=?");
$success = $stmt->execute(array($group, $name, $amount, $description, $classification, $accountid));

if ($success) {
    // حساب الفرق بين المبلغ الجديد والقديم
    $difference = $amount - $old_amount;

    if ($classification == 'assets') {
        $stmtUpdate = $con->prepare("UPDATE `user_accounts` 
            SET `assets` = `assets` + ?, `total` = `assets` - `liabilities` 
            WHERE `user_id` = ?");
        $stmtUpdate->execute(array($difference, $userid));
    } elseif ($classification == 'liabilities') {
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