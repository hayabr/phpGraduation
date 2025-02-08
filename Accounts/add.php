<?php

include "../connect.php";

// استلام البيانات
$group = filterRequest('group'); // نوع الحساب
$name = filterRequest('name');
$amount = filterRequest('amount');
$description = filterRequest('description');
$classification = filterRequest('classification'); // التصنيف (assets أو liabilities)
$userid = filterRequest('user_id');

// تحديد التصنيف تلقائيًا إذا لم يكن "Others"
if ($group == 'Cash' || $group == 'Card' || $group == 'Debit Card' || $group == 'Savings' || $group == 'Investments') {
    $classification = 'assets';
} elseif ($group == 'Overdrafts' || $group == 'Insurance' || $group == 'Loan') {
    $classification = 'liabilities';
} elseif ($group == 'Others') {
    if (empty($classification)) {
        echo json_encode(["status" => "error", "message" => "Please specify classification for 'Others'"]);
        exit;
    }
}

// إدخال الحساب في جدول accounts
$stmt = $con->prepare("INSERT INTO `accounts` (`user_id`, `group`, `name`, `amount`, `description`, `classification`) 
VALUES (?, ?, ?, ?, ?, ?)");
$success = $stmt->execute(array($userid, $group, $name, $amount, $description, $classification));

if ($success) {
    // التحقق مما إذا كان للمستخدم صف في user_accounts
    $stmtCheck = $con->prepare("SELECT * FROM `user_accounts` WHERE `user_id` = ?");
    $stmtCheck->execute(array($userid));
    $userAccountExists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$userAccountExists) {
        // إذا لم يكن لديه سجل، قم بإنشائه
        $stmtInsert = $con->prepare("INSERT INTO `user_accounts` (`user_id`, `assets`, `liabilities`, `total`) VALUES (?, 0, 0, 0)");
        $stmtInsert->execute(array($userid));
    }

    // تحديث القيم في user_accounts
    if ($classification == 'assets') {
        $stmtUpdate = $con->prepare("UPDATE `user_accounts` SET `assets` = `assets` + ?, `total` = `assets` - `liabilities` WHERE `user_id` = ?");
        $stmtUpdate->execute(array($amount, $userid));
    } elseif ($classification == 'liabilities') {
        $stmtUpdate = $con->prepare("UPDATE `user_accounts` SET `liabilities` = `liabilities` + ?, `total` = `assets` - `liabilities` WHERE `user_id` = ?");
        $stmtUpdate->execute(array($amount, $userid));
    }

    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}

?>