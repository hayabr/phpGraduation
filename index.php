<?php
 
include "connect.php";//like import 

$stmt = $con->prepare("DELETE FROM `users` WHERE id=?");
$stmt ->execute(array(6));

$count = $stmt -> rowCount();

if($count>0){
    echo "sucess";
}
else{
    echo "fail"; 
}


?>