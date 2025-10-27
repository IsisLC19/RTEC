<?php
include "db_connect.php";
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
$email = $data["email"] ?? "";
$code = $data["code"] ?? "";

$sql = "SELECT * FROM otp_codes WHERE email=? AND codigo=? AND usado=0 AND expira_em > NOW()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $email, $code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["message" => "Código válido"]);
} else {
    http_response_code(400);
    echo json_encode(["message" => "Código inválido ou expirado"]);
}
?>
