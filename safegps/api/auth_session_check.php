<?php
include 'db_config.php';
if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
    echo json_encode([
        "status" => "success",
        "loggedIn" => true,
        "user" => [
            "id" => $_SESSION['user_id'],
            "email" => $_SESSION['user_email']
        ]
    ]);
} else {
    echo json_encode(["status" => "success", "loggedIn" => false]);
}
?>
