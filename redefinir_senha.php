<?php
// 1. INICIA A SESSÃO E CONEXÃO
session_start();
header('Content-Type: application/json');
require_once 'conexao.php'; // $conn

// 2. PEGA OS DADOS (JSON) ENVIADOS PELO JAVASCRIPT
$data = json_decode(file_get_contents('php://input'));

if (empty($data->email) || empty($data->newPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'E-mail ou nova senha não fornecidos.']);
    exit();
}

$email = $data->email;
$nova_senha = $data->newPassword;

// 3. VERIFICAÇÃO DE SEGURANÇA CRUCIAL
// O script só continua se:
// 1. A sessão de verificação EXISTE (o usuário passou pelo validar_otp.php)
// 2. O e-mail na sessão é O MESMO e-mail que o formulário está tentando redefinir.
if (!isset($_SESSION['otp_verified_email']) || $_SESSION['otp_verified_email'] !== $email) {
    http_response_code(403); // Proibido (Forbidden)
    echo json_encode(['status' => 'error', 'message' => 'Autorização expirada. Por favor, reinicie o processo de recuperação.']);
    exit();
}

try {
    // 4. CRIPTOGRAFAR A NOVA SENHA
    $senha_hash = password_hash($nova_senha, PASSWORD_BCRYPT);
    
    // 5. ATUALIZAR A SENHA NO BANCO tbl_usuario
    $sql_update = "UPDATE tbl_usuario SET senha_usuario = ? WHERE email_usuario = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ss", $senha_hash, $email);
    $stmt_update->execute();
    
    // 6. VERIFICA SE A SENHA FOI REALMENTE ATUALIZADA
    if ($stmt_update->affected_rows === 1) {
        
        // 7. SUCESSO! Limpa a autorização da sessão
        // Isso impede que o usuário possa usar o mesmo link/sessão para redefinir a senha de novo.
        unset($_SESSION['otp_verified_email']);
        
        echo json_encode(['status' => 'success', 'message' => 'Senha redefinida com sucesso.']);
    } else {
        // Isso pode acontecer se o e-mail não foi encontrado,
        // embora a verificação no 'enviar_otp' deva ter pego isso.
        throw new Exception("Nenhum usuário foi atualizado.");
    }
    
    $stmt_update->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao redefinir a senha.', 'details' => $e->getMessage()]);
}

$conn->close();
?>