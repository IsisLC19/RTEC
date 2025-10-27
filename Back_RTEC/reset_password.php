<?php
include "db_connect.php";
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
$email = $data["email"] ?? "";
$newPassword = $data["newPassword"] ?? "";

if (!$email || !$newPassword) {
    http_response_code(400);
    echo json_encode(["message" => "Dados insuficientes"]);
    exit;
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE usuarios SET senha=? WHERE email=?");
$stmt->bind_param("ss", $hash, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["message" => "Senha redefinida com sucesso"]);
} else {
    http_response_code(400);
    echo json_encode(["message" => "Usuário não encontrado"]);
}
?>
