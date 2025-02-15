<?php

include "../connect.php";

try {
    // بدء معاملة قاعدة البيانات
    $con->beginTransaction();

    // استلام البيانات (يجب أن يتم إرسال transaction_id مع البيانات)
    $transaction_id = filterRequest('transaction_id');
    $user_id = filterRequest('user_id');

    // 1. استرجاع المعاملة القديمة قبل حذفها
    $stmtOld = $con->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
    $stmtOld->execute([$transaction_id, $user_id]);
    $oldTrans = $stmtOld->fetch(PDO::FETCH_ASSOC);
    if (!$oldTrans) {
        throw new Exception("المعاملة غير موجودة للمستخدم.");
    }

    // تخزين بيانات المعاملة القديمة
    $old_account_id = $oldTrans['account_id'];
    $old_amount = $oldTrans['amount'];
    $old_type = $oldTrans['type']; // قد تكون "income" أو "expenses"

    // 2. عكس تأثير المعاملة القديمة على user_transactions_summary
    if ($old_type === 'income') {
        $stmtUpdateSummary = $con->prepare("UPDATE user_transactions_summary SET income = income - ? WHERE user_id = ?");
        $stmtUpdateSummary->execute([$old_amount, $user_id]);
    } elseif ($old_type === 'expenses') {
        $stmtUpdateSummary = $con->prepare("UPDATE user_transactions_summary SET expenses = expenses - ? WHERE user_id = ?");
        $stmtUpdateSummary->execute([$old_amount, $user_id]);
    }
    // إعادة حساب total
    $stmtUpdateSummaryTotal = $con->prepare("UPDATE user_transactions_summary SET total = income - expenses WHERE user_id = ?");
    $stmtUpdateSummaryTotal->execute([$user_id]);

    // 3. عكس تأثير المعاملة القديمة على جدول accounts
    $stmtGetOldAccount = $con->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmtGetOldAccount->execute([$old_account_id, $user_id]);
    $oldAccount = $stmtGetOldAccount->fetch(PDO::FETCH_ASSOC);
    if (!$oldAccount) {
        throw new Exception("الحساب غير موجود للمستخدم.");
    }
    if ($old_type === 'income') {
        $stmtUpdateOldAccount = $con->prepare("UPDATE accounts SET amount = amount - ? WHERE id = ?");
        $stmtUpdateOldAccount->execute([$old_amount, $old_account_id]);
    } elseif ($old_type === 'expenses') {
        $stmtUpdateOldAccount = $con->prepare("UPDATE accounts SET amount = amount + ? WHERE id = ?");
        $stmtUpdateOldAccount->execute([$old_amount, $old_account_id]);
    }

    // 4. عكس تأثير المعاملة القديمة على user_accounts
    if ($old_type === 'income') {
        $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET assets = assets - ? WHERE user_id = ?");
        $stmtUpdateUA->execute([$old_amount, $user_id]);
    } elseif ($old_type === 'expenses') {
        if ($oldAccount['classification'] === 'Assets') {
            $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET assets = assets + ? WHERE user_id = ?");
            $stmtUpdateUA->execute([$old_amount, $user_id]);
        } elseif ($oldAccount['classification'] === 'Liabilities') {
            $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET liabilities = liabilities + ? WHERE user_id = ?");
            $stmtUpdateUA->execute([$old_amount, $user_id]);
        } else {
            throw new Exception("تصنيف الحساب غير معروف: " . $oldAccount['classification']);
        }
    }
    // إعادة حساب total في user_accounts
    $stmtUpdateUATotal = $con->prepare("UPDATE user_accounts SET total = assets - liabilities WHERE user_id = ?");
    $stmtUpdateUATotal->execute([$user_id]);

    // 5. حذف المعاملة من جدول transactions
    $stmtDeleteTrans = $con->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmtDeleteTrans->execute([$transaction_id, $user_id]);

    // تأكيد كافة العمليات
    $con->commit();
    echo json_encode(["status" => "success"]);

} catch (Exception $e) {
    $con->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

?>