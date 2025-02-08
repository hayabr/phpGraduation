<?php 

include "../connect.php";


$accountid = filterRequest('id');


// التحقق مما إذا كان البريد الإلكتروني موجودًا مسبقًا
$stmt = $con->prepare("SELECT * FROM accounts WHERE id = ?  ");
$stmt->execute(array($accountid));
$data = $stmt -> fetch(PDO::FETCH_ASSOC);
$count =$stmt->rowCount();

if ($count>0){
    echo json_encode(array("status" => "success","data"=> $data));
}else{
    echo json_encode(array("status" => "fail"));  
}

?>