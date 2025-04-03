<?php

include "../connect.php";

// استلام بيانات الميزانية أو المعاملة بناءً على المعلمات المرسلة
$user_id = filterRequest('user_id');

if (isset($_POST['category_id']) && isset($_POST['amount'])) {
    // استلام بيانات الميزانية
    $category_id = filterRequest('category_id');
    $amount = filterRequest('amount');
    $start_date = filterRequest('start_date'); // يمكن أن يكون فارغًا
    $end_date = filterRequest('end_date');   // يمكن أن يكون فارغًا

    // إدخال الميزانية في جدول budgets
    $stmt = $con->prepare("INSERT INTO `budgets` (`user_id`, `category_id`, `amount`, `start_date`, `end_date`) 
    VALUES (?, ?, ?, ?, ?)");
    $success = $stmt->execute(array($user_id, $category_id, $amount, $start_date, $end_date));

    if ($success) {
        echo json_encode(["status" => "success", "message" => "Budget added successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error while adding budget"]);
    }

} elseif (isset($_POST['account_id']) && isset($_POST['category_id']) && isset($_POST['amount']) && isset($_POST['type'])) {
    // استلام بيانات المعاملة
    $account_id = filterRequest('account_id'); 
    $category_id = filterRequest('category_id'); 
    $amount = filterRequest('amount'); 
    $type = filterRequest('type'); // Income or Expenses
    $transaction_date = filterRequest('transaction_date');
    $note = filterRequest('note');

    // جلب الميزانية المحددة لهذه الفئة
    $stmtBudget = $con->prepare("SELECT `amount` FROM `budgets` WHERE `category_id` = ? AND `user_id` = ?");
    $stmtBudget->execute(array($category_id, $user_id));
    $budgetData = $stmtBudget->fetch(PDO::FETCH_ASSOC);

    // جلب إجمالي المصروفات الحالية في هذه الفئة
    $stmtTotalExpenses = $con->prepare("SELECT SUM(amount) as total_expenses FROM `transactions` WHERE `category_id` = ? AND `type` = 'Expenses' AND `user_id` = ?");
    $stmtTotalExpenses->execute(array($category_id, $user_id));
    $totalExpenses = $stmtTotalExpenses->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;

    // التحقق من تجاوز الحد
    if ($type == 'Expenses' && $budgetData) {
        $budgetLimit = $budgetData['amount'];
        if (($totalExpenses + $amount) > $budgetLimit) {
            echo json_encode(["status" => "warning", "message" => "You have exceeded your budget limit for this category!"]);
            exit;
        }
    }

    // إدخال المعاملة في جدول transactions
    $stmt = $con->prepare("INSERT INTO `transactions` (`account_id`, `category_id`, `amount`, `type`, `transaction_date`, `note`, `user_id`) 
    VALUES (?, ?, ?, ?, ?, ?, ?)");
    $success = $stmt->execute(array($account_id, $category_id, $amount, $type, $transaction_date, $note, $user_id));

    if ($success) {
        echo json_encode(["status" => "success", "message" => "Transaction added successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error while adding transaction"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
}

?>