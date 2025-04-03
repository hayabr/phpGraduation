<?php

include "../connect.php";

try {
    // بدء معاملة قاعدة البيانات
    $con->beginTransaction();

    // استلام البيانات
    $user_id  = filterRequest('user_id');
    $message  = filterRequest('message');
    $is_read  = filterRequest('is_read');

    // إذا كانت `is_read` فارغة، تعيينها إلى 0 تلقائيًا
    if (empty($is_read)) {
        $is_read = 0;
    }

    // التحقق من أن البيانات الأساسية ليست فارغة
    if (empty($user_id) || empty($message)) {
        throw new Exception("User ID and message are required.");
    }

    // إدخال الإشعار في جدول notifications
    $stmt = $con->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $message, $is_read]);

    // تأكيد العملية
    $con->commit();

    // استجابة JSON بنجاح الإضافة
    echo json_encode(["status" => "success", "message" => "Notification added successfully"]);

} catch (Exception $e) {
    // التراجع عن التغييرات في حال حدوث خطأ
    $con->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

?>