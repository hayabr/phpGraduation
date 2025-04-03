<?php 

include "../connect.php";

$category_id = filterRequest('category_id');

// استعلام لجلب بيانات الفئة بناءً على category_id
$stmt = $con->prepare(" 
    SELECT id, name, type, icon 
    FROM categories 
    WHERE id = ?
");

$stmt->execute(array($category_id));
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($data) {
    echo json_encode(array("status" => "success", "data" => $data));
} else {
    echo json_encode(array("status" => "fail"));
}

?>