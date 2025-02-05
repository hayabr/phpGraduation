<?php 

include "../connect.php";

$username = filterRequest('username');
$email = filterRequest('email');
$password = filterRequest('password');

// التحقق مما إذا كان البريد الإلكتروني موجودًا مسبقًا
$stmt = $con->prepare("SELECT email FROM users WHERE email = ?");
$stmt->execute(array($email));

if ($stmt->rowCount() > 0) {
    echo json_encode(["status" => "error", "message" => "Email already exists"]);
    exit();
}

// إدخال البيانات الجديدة
$stmt = $con->prepare("INSERT INTO `users` (`username`, `email`, `password`) VALUES (?, ?, ?)");
$success = $stmt->execute(array($username, $email, $password));

if ($success) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}

?>