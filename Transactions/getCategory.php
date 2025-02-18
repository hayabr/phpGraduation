<?php
include "../connect.php";

$type = filterRequest('type'); // Get transaction type (income or expenses)

try {
    if ($type == 'income') {
        $stmt = $con->prepare("SELECT * FROM categories WHERE type = 'income'");
    } elseif ($type == 'expenses') {
        $stmt = $con->prepare("SELECT * FROM categories WHERE type = 'expenses'");
    } else {
        throw new Exception("Invalid transaction type.");
    }

    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $categories]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>