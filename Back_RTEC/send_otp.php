<?php
include "db_connect.php";
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
$email = $data["email"] ?? "";

if (!$email) {
    echo json_encode(["message" => "E-mail obrigatório"]); exit;
}

// Gerar código
$codigo = rand(100000, 999999);
$expira = date("Y-m-d H:i:s", time() + 600);

// Salva no banco
$stmt = $conn->prepare("INSERT INTO otp_codes (email, codigo, expira_em) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $email, $codigo, $expira);
$stmt->execute();

// (opcional) envia e-mail real com PHPMailer
// ou só pra dev:
error_log("Código OTP para $email: $codigo");

echo json_encode(["message" => "Código enviado"]);
?>
