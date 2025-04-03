<?php 

include "../connect.php";

// استلام ID الحساب
$accountid = filterRequest('id');

// التحقق مما إذا كان الحساب موجودًا قبل الحذف
$stmtOld = $con->prepare("SELECT `amount`, `classification`, `user_id` FROM `accounts` WHERE `id` = ?");
$stmtOld->execute(array($accountid));
$oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

if (!$oldData) {
    echo json_encode(["status" => "error", "message" => "Account not found"]);
    exit;
}

$old_amount = $oldData['amount'];
$classification = strtolower($oldData['classification']); // التأكد من أن classification بحروف صغيرة
$userid = $oldData['user_id'];

// التحقق مما إذا كان الحساب مرتبطًا بمعاملة
$stmtCheckTransaction = $con->prepare("SELECT COUNT(*) as transaction_count FROM `transactions` WHERE `account_id` = ?");
$stmtCheckTransaction->execute(array($accountid));
$transactionData = $stmtCheckTransaction->fetch(PDO::FETCH_ASSOC);

if ($transactionData['transaction_count'] > 0) {
    echo json_encode(["status" => "error", "message" => "الحساب مرتبط بمعاملة ولا يمكن حذفه إلا بعد حذف المعاملة المرتبطة به"]);
    exit;
}

// **حذف الحساب من جدول `accounts`**
$stmtDelete = $con->prepare("DELETE FROM `accounts` WHERE id = ?");
$successDelete = $stmtDelete->execute(array($accountid));

if ($successDelete) {
    $successUpdate = false; // تعريفه افتراضيًا

    // تحديث user_accounts بناءً على التصنيف
    if ($classification == 'assets') {
        // إذا كان التصنيف "أصول"
        $stmtUpdate = $con->prepare("UPDATE `user_accounts` 
            SET `assets` = GREATEST(0, `assets` - ?), `total` = `assets` - `liabilities` 
            WHERE `user_id` = ?");
        $successUpdate = $stmtUpdate->execute(array($old_amount, $userid));
    } elseif ($classification == 'liabilities') {
        // إذا كان التصنيف "التزامات"
        $stmtUpdate = $con->prepare("UPDATE `user_accounts` 
            SET `liabilities` = GREATEST(0, `liabilities` - ?), `total` = `assets` - `liabilities` 
            WHERE `user_id` = ?");
        $successUpdate = $stmtUpdate->execute(array($old_amount, $userid));
    }

    // التحقق مما إذا تم التحديث بنجاح
    if ($successUpdate) {
        echo json_encode(["status" => "success", "message" => "Account deleted and user_accounts updated"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update user_accounts"]);
    }

} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete account"]);
}

?>