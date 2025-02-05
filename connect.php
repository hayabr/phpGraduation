<?php
include "functions.php";
$dsn="mysql:host=localhost;dbname=moneytracker";
$user="root";
$pass="";
$con= new PDO($dsn,$user,$pass); //pdo class in php to connect with database



try{
    $con= new PDO($dsn,$user,$pass);
    $con->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION) ;
}
catch(PDOException $e){
    echo $e->getMessage();
}

?>