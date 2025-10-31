<?php
// 1. INICIA A SESSÃO E CONEXÃO
// A sessão é vital para os próximos passos
session_start();
header('Content-Type: application/json');
require_once 'conexao.php'; // $conn

// 2. PEGA OS DADOS (JSON) ENVIADOS PELO JAVASCRIPT
$data = json_decode(file_get_contents('php://input'));

if (empty($data->email)) {
    echo json_encode(['status' => 'error', 'message' => 'E-mail não fornecido.']);
    exit();
}

$email = $data->email;

// --- PASSO 3: VERIFICAR SE O E-MAIL EXISTE NO BANCO ---
$stmt_check = $conn->prepare("SELECT id_usuario FROM tbl_usuario WHERE email_usuario = ?");
$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows === 0) {
    // E-mail não encontrado.
    // **IMPORTANTE (SEGURANÇA):** Nós NÃO dizemos ao usuário que o e-mail não foi encontrado.
    // Apenas fingimos que o e-mail foi enviado para evitar que hackers "adivinhem" e-mails.
    echo json_encode(['status' => 'success', 'message' => 'Se o e-mail existir, um código será enviado.']);
    exit();
}
$stmt_check->close();

// --- PASSO 4: GERAR CÓDIGO E EXPIRAÇÃO ---
// O usuário existe, então vamos gerar o código.
$codigo_otp = (string)mt_rand(100000, 999999); // Gera um código de 6 dígitos
$minutos_para_expirar = 10;
// Calcula o timestamp de expiração (ex: 18:30:00)
$data_expiracao = date('Y-m-d H:i:s', strtotime("+$minutos_para_expirar minutes"));

// --- PASSO 5: SALVAR O CÓDIGO NO BANCO (tbl_recuperacao_senha) ---
try {
    // Invalida códigos antigos para este e-mail (opcional, mas boa prática)
    $conn->query("UPDATE tbl_recuperacao_senha SET utilizado = 1 WHERE email_usuario = '$email'");

    // Salva o novo código
    $sql_save_otp = "INSERT INTO tbl_recuperacao_senha (email_usuario, codigo_otp, data_expiracao) 
                     VALUES (?, ?, ?)";
    $stmt_save = $conn->prepare($sql_save_otp);
    $stmt_save->bind_param("sss", $email, $codigo_otp, $data_expiracao);
    $stmt_save->execute();
    
    // --- PASSO 6: ENVIAR O E-MAIL (Simulação) ---
    // Enviar um e-mail de verdade requer bibliotecas (como PHPMailer) e um servidor SMTP.
    // Por enquanto, vamos retornar o código no JSON para você poder testar.
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Código enviado com sucesso.',
        '_teste_codigo' => $codigo_otp // Apenas para testes! Remova isso em produção.
    ]);
    
    $stmt_save->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar o código no banco.', 'details' => $e->getMessage()]);
}

$conn->close();
?>