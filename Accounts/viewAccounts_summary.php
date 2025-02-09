<?php 

include "../connect.php";


$userid = filterRequest('user_id');


// التحقق مما إذا كان البريد الإلكتروني موجودًا مسبقًا
$stmt = $con->prepare("SELECT * FROM user_accounts WHERE user_id = ?  ");
$stmt->execute(array($userid));
$data = $stmt -> fetchAll(PDO::FETCH_ASSOC);
$count =$stmt->rowCount();

if ($count>0){
    echo json_encode(array("status" => "success","data"=>$data));
}else{
    echo json_encode(array("status" => "fail"));  
}

?>