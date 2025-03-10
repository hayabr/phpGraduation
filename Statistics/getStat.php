<?php 

include "../connect.php";

$userid = filterRequest('user_id');
$periodType = filterRequest('period_type'); // daily, weekly, monthly, yearly

$stmt = $con->prepare("SELECT * FROM statistics WHERE user_id = ? AND period_type = ?");
$stmt->execute(array($userid, $periodType));
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$count = $stmt->rowCount();

if ($count > 0) {
    echo json_encode(array("status" => "success", "data" => $data));
} else {
    echo json_encode(array("status" => "fail"));  
}

?>