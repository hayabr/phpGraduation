<?php

include "../connect.php";

try {
    // بدء معاملة قاعدة البيانات
    $con->beginTransaction();

    // استلام بيانات المستخدم
    $user_id = filterRequest('user_id');

    // تاريخ بداية الأسبوع (السبت) ونهاية الأسبوع (الجمعة)
    $startOfWeek = date("Y-m-d", strtotime('last Saturday'));
    $endOfWeek = date("Y-m-d", strtotime('next Friday'));

    // جلب معاملات الأسبوع الحالي
    $stmtWeeklyTransactions = $con->prepare("SELECT * FROM transactions WHERE user_id = ? AND DATE(transaction_date) BETWEEN ? AND ?");
    $stmtWeeklyTransactions->execute([$user_id, $startOfWeek, $endOfWeek]);
    $weeklyTransactions = $stmtWeeklyTransactions->fetchAll(PDO::FETCH_ASSOC);

    // إذا لم تكن هناك معاملات للأسبوع الحالي، جلب آخر أسبوع متاح
    if (empty($weeklyTransactions)) {
        $stmtLastWeek = $con->prepare("SELECT MAX(DATE(transaction_date)) AS last_week FROM transactions WHERE user_id = ?");
        $stmtLastWeek->execute([$user_id]);
        $lastWeekDate = $stmtLastWeek->fetch(PDO::FETCH_ASSOC)['last_week'];

        if ($lastWeekDate) {
            $startOfLastWeek = date("Y-m-d", strtotime('last Saturday', strtotime($lastWeekDate)));
            $endOfLastWeek = date("Y-m-d", strtotime('next Friday', strtotime($lastWeekDate)));

            $stmtLastWeekTransactions = $con->prepare("SELECT * FROM transactions WHERE user_id = ? AND DATE(transaction_date) BETWEEN ? AND ?");
            $stmtLastWeekTransactions->execute([$user_id, $startOfLastWeek, $endOfLastWeek]);
            $weeklyTransactions = $stmtLastWeekTransactions->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // إذا كانت هناك معاملات
    if (!empty($weeklyTransactions)) {
        $totalIncome = 0;
        $totalExpenses = 0;
        $incomeByCategory = [];
        $expensesByCategory = [];

        // حساب إجمالي الدخل والمصروفات لكل فئة
        foreach ($weeklyTransactions as $transaction) {
            $category_id = $transaction['category_id'];
            $amount = $transaction['amount'];
            $transaction_id = $transaction['id']; // معرف العملية

            if ($transaction['type'] === 'income') {
                $totalIncome += $amount;
                if (!isset($incomeByCategory[$category_id])) {
                    $incomeByCategory[$category_id] = 0;
                }
                $incomeByCategory[$category_id] += $amount;
            } elseif ($transaction['type'] === 'expenses') {
                $totalExpenses += $amount;
                if (!isset($expensesByCategory[$category_id])) {
                    $expensesByCategory[$category_id] = 0;
                }
                $expensesByCategory[$category_id] += $amount;
            }
        }

        // حساب النسب المئوية لكل فئة
        $incomePercentages = [];
        $expensePercentages = [];

        foreach ($incomeByCategory as $category_id => $amount) {
            $incomePercentages[$category_id] = ($totalIncome > 0) ? ($amount / $totalIncome) * 100 : 0;
        }

        foreach ($expensesByCategory as $category_id => $amount) {
            $expensePercentages[$category_id] = ($totalExpenses > 0) ? ($amount / $totalExpenses) * 100 : 0;
        }

        // إدراج البيانات في جدول statistics
        $stmtInsertStatistics = $con->prepare("INSERT INTO statistics (user_id, category_id, expense_percentage, income_percentage, created_at, transaction_id, period_type) 
            VALUES (?, ?, ?, ?, NOW(), ?, ?)");

        foreach ($weeklyTransactions as $transaction) {
            $category_id = $transaction['category_id'];
            $transaction_id = $transaction['id']; // معرف العملية
            $incomePercentage = isset($incomePercentages[$category_id]) ? $incomePercentages[$category_id] : 0;
            $expensePercentage = isset($expensePercentages[$category_id]) ? $expensePercentages[$category_id] : 0;

            // التحقق مما إذا كان هذا transaction_id موجودًا بالفعل لتجنب التكرار
            $stmtCheckExisting = $con->prepare("SELECT COUNT(*) FROM statistics WHERE transaction_id = ? AND period_type = 'weekly'");
            $stmtCheckExisting->execute([$transaction_id]);
            $exists = $stmtCheckExisting->fetchColumn();

            if ($exists == 0) { // إدراج البيانات فقط إذا لم تكن موجودة
                $stmtInsertStatistics->execute([$user_id, $category_id, $expensePercentage, $incomePercentage, $transaction_id, 'weekly']);
            }
        }

        // جلب الإحصائيات الأخيرة بناءً على user_id و period_type = 'weekly'
        $stmtLatestStatistics = $con->prepare("SELECT * FROM statistics WHERE user_id = ? AND period_type = 'weekly' ORDER BY created_at DESC LIMIT 1");
        $stmtLatestStatistics->execute([$user_id]);
        $latestStatistics = $stmtLatestStatistics->fetch(PDO::FETCH_ASSOC);

        // إعداد النتائج للإرجاع
        $result = [
            "status" => "success",
            "data" => [
                "total_income" => $totalIncome,
                "total_expenses" => $totalExpenses,
                "income_percentages" => $incomePercentages,
                "expense_percentages" => $expensePercentages,
              //  "latest_statistics" => $latestStatistics // إضافة الإحصائيات الأخيرة
            ]
        ];

        echo json_encode($result);
    } else {
        echo json_encode(["status" => "success", "message" => "لا توجد معاملات لهذا المستخدم."]);
    }

    // تأكيد كافة العمليات
    $con->commit();
} catch (Exception $e) {
    // التراجع عن كافة العمليات في حال حدوث خطأ
    $con->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

?>