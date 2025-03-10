<?php

include "../connect.php";

try {
    // بدء معاملة قاعدة البيانات
    $con->beginTransaction();

    // استلام بيانات المستخدم
    $user_id = filterRequest('user_id');

    // تاريخ بداية الشهر ونهاية الشهر
    $startOfMonth = date("Y-m-01"); // أول يوم في الشهر الحالي
    $endOfMonth = date("Y-m-t");   // آخر يوم في الشهر الحالي

    // جلب معاملات الشهر الحالي
    $stmtMonthlyTransactions = $con->prepare("SELECT * FROM transactions WHERE user_id = ? AND DATE(transaction_date) BETWEEN ? AND ?");
    $stmtMonthlyTransactions->execute([$user_id, $startOfMonth, $endOfMonth]);
    $monthlyTransactions = $stmtMonthlyTransactions->fetchAll(PDO::FETCH_ASSOC);

    // إذا لم تكن هناك معاملات للشهر الحالي، جلب آخر شهر متاح
    if (empty($monthlyTransactions)) {
        $stmtLastMonth = $con->prepare("SELECT MAX(DATE(transaction_date)) AS last_month FROM transactions WHERE user_id = ?");
        $stmtLastMonth->execute([$user_id]);
        $lastMonthDate = $stmtLastMonth->fetch(PDO::FETCH_ASSOC)['last_month'];

        if ($lastMonthDate) {
            $startOfLastMonth = date("Y-m-01", strtotime($lastMonthDate));
            $endOfLastMonth = date("Y-m-t", strtotime($lastMonthDate));

            $stmtLastMonthTransactions = $con->prepare("SELECT * FROM transactions WHERE user_id = ? AND DATE(transaction_date) BETWEEN ? AND ?");
            $stmtLastMonthTransactions->execute([$user_id, $startOfLastMonth, $endOfLastMonth]);
            $monthlyTransactions = $stmtLastMonthTransactions->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // إذا كانت هناك معاملات
    if (!empty($monthlyTransactions)) {
        $totalIncome = 0;
        $totalExpenses = 0;
        $incomeByCategory = [];
        $expensesByCategory = [];

        // حساب إجمالي الدخل والمصروفات لكل فئة
        foreach ($monthlyTransactions as $transaction) {
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

        foreach ($monthlyTransactions as $transaction) {
            $category_id = $transaction['category_id'];
            $transaction_id = $transaction['id']; // معرف العملية
            $incomePercentage = isset($incomePercentages[$category_id]) ? $incomePercentages[$category_id] : 0;
            $expensePercentage = isset($expensePercentages[$category_id]) ? $expensePercentages[$category_id] : 0;

            // التحقق مما إذا كان هذا transaction_id موجودًا بالفعل لتجنب التكرار
            $stmtCheckExisting = $con->prepare("SELECT COUNT(*) FROM statistics WHERE transaction_id = ? AND period_type = 'monthly'");
            $stmtCheckExisting->execute([$transaction_id]);
            $exists = $stmtCheckExisting->fetchColumn();

            if ($exists == 0) { // إدراج البيانات فقط إذا لم تكن موجودة
                $stmtInsertStatistics->execute([$user_id, $category_id, $expensePercentage, $incomePercentage, $transaction_id, 'monthly']);
            }
        }

        // جلب الإحصائيات الأخيرة بناءً على user_id و period_type = 'monthly'
        $stmtLatestStatistics = $con->prepare("SELECT * FROM statistics WHERE user_id = ? AND period_type = 'monthly' ORDER BY created_at DESC LIMIT 1");
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