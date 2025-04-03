<?php

include "../connect.php";

try {
    // بدء معاملة قاعدة البيانات
    $con->beginTransaction();

    // استلام البيانات (يجب أن يتم إرسال transaction_id مع البيانات)
    $transaction_id   = filterRequest('transaction_id');
    $user_id          = filterRequest('user_id');
    $new_account_id   = filterRequest('account_id');
    $new_category_id  = filterRequest('category_id');
    $new_amount       = filterRequest('amount');
    $new_type         = filterRequest('type'); // متوقع: "income" أو "expenses"
    $new_note         = filterRequest('note');
    $new_date         = filterRequest('transaction_date');

    if (empty($new_date)) {
        $new_date = date("Y-m-d H:i:s");
    }
    
    // تصحيح محتمل لنوع المعاملة إذا أُرسلت "expensess"
    if (strtolower($new_type) === 'expensess') {
        $new_type = 'expenses';
    }
    
    // 1. استرجاع المعاملة القديمة للتعديل
    $stmtOld = $con->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
    $stmtOld->execute([$transaction_id, $user_id]);
    $oldTrans = $stmtOld->fetch(PDO::FETCH_ASSOC);
    if (!$oldTrans) {
        throw new Exception("المعاملة القديمة غير موجودة للمستخدم.");
    }
    
    // تخزين بيانات المعاملة القديمة
    $old_account_id = $oldTrans['account_id'];
    $old_amount     = $oldTrans['amount'];
    $old_type       = $oldTrans['type']; // قد تكون "income" أو "expenses"
    
    // 2. عكس تأثير المعاملة القديمة في جدول user_transactions_summary
    $stmtCheckSummary = $con->prepare("SELECT * FROM user_transactions_summary WHERE user_id = ?");
    $stmtCheckSummary->execute([$user_id]);
    $summary = $stmtCheckSummary->fetch(PDO::FETCH_ASSOC);
    if (!$summary) {
        // إنشاء سجل إذا لم يكن موجوداً (عادةً يكون موجوداً)
        $stmtInsertSummary = $con->prepare("INSERT INTO user_transactions_summary (user_id, income, expenses, total) VALUES (?, 0, 0, 0)");
        $stmtInsertSummary->execute([$user_id]);
    }
    if ($old_type === 'income') {
        // عكس تأثير الدخل: نطرح المبلغ القديم
        $stmtUpdateSummary = $con->prepare("UPDATE user_transactions_summary SET income = income - ? WHERE user_id = ?");
        $stmtUpdateSummary->execute([$old_amount, $user_id]);
    } elseif ($old_type === 'expenses') {
        // عكس تأثير المصروفات: نطرح المبلغ القديم
        $stmtUpdateSummary = $con->prepare("UPDATE user_transactions_summary SET expenses = expenses - ? WHERE user_id = ?");
        $stmtUpdateSummary->execute([$old_amount, $user_id]);
    }
    // إعادة حساب total: total = income - expenses
    $stmtUpdateSummaryTotal = $con->prepare("UPDATE user_transactions_summary SET total = income - expenses WHERE user_id = ?");
    $stmtUpdateSummaryTotal->execute([$user_id]);
    
    // 3. عكس تأثير المعاملة القديمة على جدول accounts
    // استرجاع بيانات الحساب القديم
    $stmtGetOldAccount = $con->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmtGetOldAccount->execute([$old_account_id, $user_id]);
    $oldAccount = $stmtGetOldAccount->fetch(PDO::FETCH_ASSOC);
    if (!$oldAccount) {
        throw new Exception("الحساب القديم غير موجود للمستخدم.");
    }
    if ($old_type === 'income') {
        // كان يُضاف المبلغ، فنعاكس ذلك بطرحه
        $stmtUpdateOldAccount = $con->prepare("UPDATE accounts SET amount = amount - ? WHERE id = ?");
        $stmtUpdateOldAccount->execute([$old_amount, $old_account_id]);
    } elseif ($old_type === 'expenses') {
        // كان يُطرح المبلغ، فنعاكس ذلك بإضافته
        $stmtUpdateOldAccount = $con->prepare("UPDATE accounts SET amount = amount + ? WHERE id = ?");
        $stmtUpdateOldAccount->execute([$old_amount, $old_account_id]);
    }
    
    // 4. عكس تأثير المعاملة القديمة على جدول user_accounts
    $stmtCheckUA = $con->prepare("SELECT * FROM user_accounts WHERE user_id = ?");
    $stmtCheckUA->execute([$user_id]);
    $userAccount = $stmtCheckUA->fetch(PDO::FETCH_ASSOC);
    if (!$userAccount) {
        $stmtInsertUA = $con->prepare("INSERT INTO user_accounts (user_id, assets, liabilities, total) VALUES (?, 0, 0, 0)");
        $stmtInsertUA->execute([$user_id]);
        $stmtCheckUA->execute([$user_id]);
        $userAccount = $stmtCheckUA->fetch(PDO::FETCH_ASSOC);
    }
    if ($old_type === 'income') {
        // عكس تأثير الدخل على user_accounts: يُطرح المبلغ من assets
        $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET assets = assets - ? WHERE user_id = ?");
        $stmtUpdateUA->execute([$old_amount, $user_id]);
    } elseif ($old_type === 'expenses') {
        // نحتاج لتحديد تصنيف الحساب القديم
        if ($oldAccount['classification'] === 'Assets') {
            // عكس طرح المصروفات من assets: نضيف المبلغ
            $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET assets = assets + ? WHERE user_id = ?");
            $stmtUpdateUA->execute([$old_amount, $user_id]);
        } elseif ($oldAccount['classification'] === 'Liabilities') {
            // عكس طرح المصروفات من liabilities: نضيف المبلغ
            $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET liabilities = liabilities + ? WHERE user_id = ?");
            $stmtUpdateUA->execute([$old_amount, $user_id]);
        } else {
            throw new Exception("تصنيف الحساب القديم غير معروف: " . $oldAccount['classification']);
        }
    }
    // إعادة حساب total في user_accounts
    $stmtUpdateUATotal = $con->prepare("UPDATE user_accounts SET total = assets - liabilities WHERE user_id = ?");
    $stmtUpdateUATotal->execute([$user_id]);
    
    // 5. تطبيق تأثير المعاملة الجديدة:
    // تحديث سجل المعاملة في جدول transactions
    $stmtUpdateTrans = $con->prepare("UPDATE transactions SET account_id = ?, category_id = ?, amount = ?, type = ?, note = ?, transaction_date = ? WHERE id = ? AND user_id = ?");
    $stmtUpdateTrans->execute([$new_account_id, $new_category_id, $new_amount, $new_type, $new_note, $new_date, $transaction_id, $user_id]);
    
    // تحديث جدول user_transactions_summary للقيم الجديدة
    if ($new_type === 'income') {
        $stmtUpdateSummary = $con->prepare("UPDATE user_transactions_summary SET income = income + ? WHERE user_id = ?");
        $stmtUpdateSummary->execute([$new_amount, $user_id]);
    } elseif ($new_type === 'expenses') {
        $stmtUpdateSummary = $con->prepare("UPDATE user_transactions_summary SET expenses = expenses + ? WHERE user_id = ?");
        $stmtUpdateSummary->execute([$new_amount, $user_id]);
    }
    // إعادة حساب total في summary
    $stmtUpdateSummaryTotal = $con->prepare("UPDATE user_transactions_summary SET total = income - expenses WHERE user_id = ?");
    $stmtUpdateSummaryTotal->execute([$user_id]);
    
    // تحديث جدول accounts للقيم الجديدة
    // استرجاع بيانات الحساب الجديد (قد يكون هو نفسه القديم أو مختلف)
    $stmtGetNewAccount = $con->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmtGetNewAccount->execute([$new_account_id, $user_id]);
    $newAccount = $stmtGetNewAccount->fetch(PDO::FETCH_ASSOC);
    if (!$newAccount) {
        throw new Exception("الحساب الجديد غير موجود للمستخدم.");
    }
    if ($new_type === 'income') {
        // إذا كانت المعاملة دخل: يُضاف المبلغ للحساب
        $stmtUpdateNewAccount = $con->prepare("UPDATE accounts SET amount = amount + ? WHERE id = ?");
        $stmtUpdateNewAccount->execute([$new_amount, $new_account_id]);
    } elseif ($new_type === 'expenses') {
        // إذا كانت المعاملة مصروف: يُطرح المبلغ من الحساب
        $stmtUpdateNewAccount = $con->prepare("UPDATE accounts SET amount = amount - ? WHERE id = ?");
        $stmtUpdateNewAccount->execute([$new_amount, $new_account_id]);
    }
    
    // تحديث جدول user_accounts للقيم الجديدة
    if ($new_type === 'income') {
        // للدخل: إضافة المبلغ إلى assets
        $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET assets = assets + ? WHERE user_id = ?");
        $stmtUpdateUA->execute([$new_amount, $user_id]);
    } elseif ($new_type === 'expenses') {
        if ($newAccount['classification'] === 'Assets') {
            // إذا كان التصنيف Assets: طرح المبلغ من assets
            $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET assets = assets - ? WHERE user_id = ?");
            $stmtUpdateUA->execute([$new_amount, $user_id]);
        } elseif ($newAccount['classification'] === 'Liabilities') {
            // إذا كان التصنيف Liabilities: طرح المبلغ من liabilities
            $stmtUpdateUA = $con->prepare("UPDATE user_accounts SET liabilities = liabilities - ? WHERE user_id = ?");
            $stmtUpdateUA->execute([$new_amount, $user_id]);
        } else {
            throw new Exception("تصنيف الحساب الجديد غير معروف: " . $newAccount['classification']);
        }
    }
    // إعادة حساب total في user_accounts
    $stmtUpdateUATotal = $con->prepare("UPDATE user_accounts SET total = assets - liabilities WHERE user_id = ?");
    $stmtUpdateUATotal->execute([$user_id]);

    // ================== Budget Check Logic ==================
    $budgetExceeded = false;
    if ($new_type === 'expenses') {
        // جلب الميزانية المحددة لهذه الفئة
        $stmtBudget = $con->prepare("SELECT `amount` FROM `budgets` WHERE `category_id` = ? AND `user_id` = ?");
        $stmtBudget->execute([$new_category_id, $user_id]);
        $budgetData = $stmtBudget->fetch(PDO::FETCH_ASSOC);

        if ($budgetData) {
            // جلب إجمالي المصروفات الحالية في هذه الفئة
            $stmtTotalExpenses = $con->prepare("SELECT SUM(amount) as total_expenses FROM `transactions` WHERE `category_id` = ? AND `type` = 'expenses' AND `user_id` = ?");
            $stmtTotalExpenses->execute([$new_category_id, $user_id]);
            $totalExpenses = $stmtTotalExpenses->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;

            // التحقق من تجاوز الحد
            $budgetLimit = $budgetData['amount'];
            if ($totalExpenses > $budgetLimit) {
                $budgetExceeded = true;
            }
        }
    }
    // ================== End Budget Check Logic ==================

    // تأكيد كافة العمليات
    $con->commit();

    // إرجاع رسالة تحذير إذا تم تجاوز الميزانية
    if ($budgetExceeded) {
        echo json_encode(["status" => "success", "message" => "Transaction updated successfully, but you have exceeded your budget limit for this category!"]);
    } else {
        echo json_encode(["status" => "success"]);
    }
    
} catch (Exception $e) {
    $con->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

?>