<?php
// 1. INICIA A SESSÃO E CONEXÃO
session_start();
header('Content-Type: application/json');
require_once 'conexao.php'; // $conn

// 2. PEGA OS DADOS (JSON) ENVIADOS PELO JAVASCRIPT
$data = json_decode(file_get_contents('php://input'));

if (empty($data->email) || empty($data->code)) {
    echo json_encode(['status' => 'error', 'message' => 'E-mail ou código não fornecido.']);
    exit();
}

$email = $data->email;
$codigo = $data->code;

try {
    // 3. PROCURA O CÓDIGO VÁLIDO NO BANCO
    // A consulta checa 4 coisas:
    // 1. O e-mail bate?
    // 2. O código bate?
    // 3. O código AINDA NÃO foi utilizado (utilizado = 0)?
    // 4. O código AINDA NÃO expirou (data_expiracao > HORA ATUAL)?
    $sql_check = "SELECT id_recuperacao FROM tbl_recuperacao_senha 
                  WHERE email_usuario = ? 
                    AND codigo_otp = ? 
                    AND utilizado = 0 
                    AND data_expiracao > NOW()";
                    
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ss", $email, $codigo);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 1) {
        // 4. CÓDIGO VÁLIDO!
        $row = $result->fetch_assoc();
        $id_recuperacao = $row['id_recuperacao'];
        
        // 5. MARCA O CÓDIGO COMO UTILIZADO
        // Isso impede que o mesmo código seja usado de novo
        $stmt_update = $conn->prepare("UPDATE tbl_recuperacao_senha SET utilizado = 1 WHERE id_recuperacao = ?");
        $stmt_update->bind_param("i", $id_recuperacao);
        $stmt_update->execute();
        $stmt_update->close();
        
        // 6. AUTORIZA A REDEFINIÇÃO DE SENHA NA SESSÃO
        // Esta é a parte mais importante: salvamos na sessão que este e-mail foi verificado.
        $_SESSION['otp_verified_email'] = $email;
        
        echo json_encode(['status' => 'success', 'message' => 'Código verificado.']);
        
    } else {
        // 4b. CÓDIGO INVÁLIDO
        // Se a consulta não retornar nada, o código está errado, expirou ou já foi usado.
        echo json_encode(['status' => 'error', 'message' => 'Código inválido ou expirado.']);
    }
    
    $stmt_check->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao validar o código.', 'details' => $e->getMessage()]);
}

$conn->close();
?>