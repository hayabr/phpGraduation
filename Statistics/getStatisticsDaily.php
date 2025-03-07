<?php

include "../connect.php";

try {
    // بدء معاملة قاعدة البيانات
    $con->beginTransaction();

    // استلام بيانات المستخدم
    $user_id = filterRequest('user_id');

    // تاريخ اليوم
    $today = date("Y-m-d");

    // جلب معاملات تاريخ اليوم
    $stmtTodayTransactions = $con->prepare("SELECT * FROM transactions WHERE user_id = ? AND DATE(transaction_date) = ?");
    $stmtTodayTransactions->execute([$user_id, $today]);
    $todayTransactions = $stmtTodayTransactions->fetchAll(PDO::FETCH_ASSOC);

    // إذا لم تكن هناك معاملات بتاريخ اليوم، جلب معاملات آخر تاريخ متاح
    if (empty($todayTransactions)) {
        $stmtLastDate = $con->prepare("SELECT MAX(DATE(transaction_date)) AS last_date FROM transactions WHERE user_id = ?");
        $stmtLastDate->execute([$user_id]);
        $lastDate = $stmtLastDate->fetch(PDO::FETCH_ASSOC)['last_date'];

        if ($lastDate) {
            $stmtLastDateTransactions = $con->prepare("SELECT * FROM transactions WHERE user_id = ? AND DATE(transaction_date) = ?");
            $stmtLastDateTransactions->execute([$user_id, $lastDate]);
            $todayTransactions = $stmtLastDateTransactions->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // إذا كانت هناك معاملات
    if (!empty($todayTransactions)) {
        $totalIncome = 0;
        $totalExpenses = 0;
        $incomeByCategory = [];
        $expensesByCategory = [];

        // حساب إجمالي الدخل والمصروفات لكل فئة
        foreach ($todayTransactions as $transaction) {
            $category_id = $transaction['category_id'];
            $amount = $transaction['amount'];

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

        // إعداد النتائج
        $result = [
            "status" => "success",
            "data" => [
                "total_income" => $totalIncome,
                "total_expenses" => $totalExpenses,
                "income_percentages" => $incomePercentages,
                "expense_percentages" => $expensePercentages
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