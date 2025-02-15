<?php

include "../connect.php";

try {
    // بدء معاملة قاعدة البيانات
    $con->beginTransaction();

    // استلام البيانات
    $user_id          = filterRequest('user_id');
    $account_id       = filterRequest('account_id');
    $category_id      = filterRequest('category_id');
    $amount           = filterRequest('amount');
    $type             = filterRequest('type'); // من المتوقع أن تكون "income" أو "expenses"
    $note             = filterRequest('note');
    $transaction_date = filterRequest('transaction_date');

    if (empty($transaction_date)) {
        $transaction_date = date("Y-m-d H:i:s");
    }
    
    // تصحيح محتمل لنوع المعاملة إذا أُرسلت "expensess" بدلاً من "expenses"
    if (strtolower($type) === 'expensess') {
        $type = 'expenses';
    }
    
    // 1. إدخال المعاملة في جدول transactions
    $stmt = $con->prepare("INSERT INTO transactions (user_id, account_id, category_id, amount, type, note, transaction_date) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $account_id, $category_id, $amount, $type, $note, $transaction_date]);

    // 2. تحديث جدول user_transactions_summary
    $stmtCheckSummary = $con->prepare("SELECT * FROM user_transactions_summary WHERE user_id = ?");
    $stmtCheckSummary->execute([$user_id]);
    $summary = $stmtCheckSummary->fetch(PDO::FETCH_ASSOC);
    
    if (!$summary) {
        $stmtInsertSummary = $con->prepare("INSERT INTO user_transactions_summary (user_id, income, expenses, total) 
                                            VALUES (?, 0, 0, 0)");
        $stmtInsertSummary->execute([$user_id]);
    }
    
    if ($type === 'income') {
        $stmtUpdateSummary = $con->prepare("UPDATE user_transactions_summary SET income = income + ? WHERE user_id = ?");
        $stmtUpdateSummary->execute([$amount, $user_id]);
    } elseif ($type === 'expenses') {
        $stmtUpdateSummary = $con->prepare("UPDATE user_transactions_summary SET expenses = expenses + ? WHERE user_id = ?");
        $stmtUpdateSummary->execute([$amount, $user_id]);
    }
    
    // إعادة حساب total: total = income - expenses
    $stmtUpdateSummaryTotal = $con->prepare("UPDATE user_transactions_summary SET total = income - expenses WHERE user_id = ?");
    $stmtUpdateSummaryTotal->execute([$user_id]);

    // 3. تحديث جدول accounts
    // جلب بيانات الحساب لتحديد التصنيف (Assets أو Liabilities)
    $stmtGetAccount = $con->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmtGetAccount->execute([$account_id, $user_id]);
    $account = $stmtGetAccount->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        throw new Exception("الحساب غير موجود للمستخدم.");
    }
    
    if ($type === 'income') {
        $stmtUpdateAccount = $con->prepare("UPDATE accounts SET amount = amount + ? WHERE id = ?");
        $stmtUpdateAccount->execute([$amount, $account_id]);
    } elseif ($type === 'expenses') {
        $stmtUpdateAccount = $con->prepare("UPDATE accounts SET amount = amount - ? WHERE id = ?");
        $stmtUpdateAccount->execute([$amount, $account_id]);
    }

    // 4. تحديث جدول user_accounts
    // التأكد من وجود سجل للمستخدم في user_accounts
    $stmtCheckUA = $con->prepare("SELECT * FROM user_accounts WHERE user_id = ?");
    $stmtCheckUA->execute([$user_id]);
    $userAccount = $stmtCheckUA->fetch(PDO::FETCH_ASSOC);
    
    if (!$userAccount) {
        $stmtInsertUA = $con->prepare("INSERT INTO user_accounts (user_id, assets, liabilities, total) 
                                       VALUES (?, 0, 0, 0)");
        $stmtInsertUA->execute([$user_id]);
    }
    
    // تحديث user_accounts بناءً على نوع المعاملة
    if ($type === 'income') {
        // حالة الدخل: إضافة المبلغ إلى حقل assets
        $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET assets = assets + ? WHERE user_id = ?");
        $stmtUpdateUA->execute([$amount, $user_id]);
    } elseif ($type === 'expenses') {
        // حالة المصروفات: استخدام التصنيف من جدول accounts
        if ($account['classification'] === 'Assets') {
            $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET assets = assets - ? WHERE user_id = ?");
            $stmtUpdateUA->execute([$amount, $user_id]);
        } elseif ($account['classification'] === 'Liabilities') {
            $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET liabilities = liabilities - ? WHERE user_id = ?");
            $stmtUpdateUA->execute([$amount, $user_id]);
        } else {
            throw new Exception("تصنيف الحساب غير معروف: " . $account['classification']);
        }
    }
    
    // إعادة حساب total في user_accounts: total = assets - liabilities
    $stmtUpdateUATotal = $con->prepare("UPDATE user_accounts SET total = assets - liabilities WHERE user_id = ?");
    $stmtUpdateUATotal->execute([$user_id]);

    // تأكيد كافة العمليات
    $con->commit();

    echo json_encode(["status" => "success"]);
} catch (Exception $e) {
    // التراجع عن كافة العمليات في حال حدوث خطأ
    $con->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

?>