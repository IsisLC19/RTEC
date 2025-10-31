<?php
// 1. INCLUI A CONEXÃO E DEFINE A RESPOSTA COMO JSON
require_once 'conexao.php'; // $conn
header('Content-Type: application/json');

// 2. OBTER OS DADOS DA REQUISIÇÃO (JSON)
// O JavaScript (fetch) envia dados em formato JSON
$json = file_get_contents('php://input');
$data = json_decode($json);

// 3. VALIDAR DADOS (simples)
if (empty($data->email_usuario_agendamento) || empty($data->id_tipo_residuo) || empty($data->data_agendada) || empty($data->hora_agendada)) {
    // Envia uma resposta de erro
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Todos os campos obrigatórios devem ser preenchidos.']);
    exit();
}

// 4. GERAR UM PROTOCOLO ÚNICO NO SERVIDOR
$protocolo = "RTEC" . mt_rand(100000, 999999);
// (Num sistema real, teríamos que verificar se esse protocolo já existe,
// mas para este projeto, a chance de colisão é mínima)

// 5. PREPARAR E SALVAR NO BANCO DE DADOS
try {
    // Usamos a tabela tbl_agendamento
    $sql = "INSERT INTO tbl_agendamento 
                (email_usuario_agendamento, tipo_pessoa, id_tipo_residuo, data_agendada, hora_agendada, protocolo_agendamento, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Agendado')";
    
    $stmt = $conn->prepare($sql);
    
    // Converte o ID do resíduo para Inteiro
    $id_residuo_int = (int)$data->id_tipo_residuo;

    $stmt->bind_param("ssisss", 
        $data->email_usuario_agendamento,
        $data->tipo_pessoa,
        $id_residuo_int,
        $data->data_agendada,
        $data->hora_agendada,
        $protocolo
    );
    
    $stmt->execute();
    
    // 6. ENVIAR RESPOSTA DE SUCESSO DE VOLTA PARA O JAVASCRIPT
    // Devolvemos todos os dados + o protocolo gerado
    http_response_code(200); // OK
    echo json_encode([
        'status' => 'success',
        'message' => 'Agendamento salvo com sucesso!',
        'protocoloGerado' => $protocolo,
        'email_usuario' => $data->email_usuario_agendamento,
        'tipo_pessoa' => $data->tipo_pessoa,
        // Formata a data para o modal (ex: 30/10/2025)
        'data_agendada_formatada' => date('d/m/Y', strtotime($data->data_agendada)), 
        'hora_agendada' => $data->hora_agendada,
        'id_tipo_residuo' => $id_residuo_int
    ]);

    $stmt->close();

} catch (mysqli_sql_exception $e) {
    // 7. TRATAR ERROS
    http_response_code(500); // Internal Server Error
    $message = 'Erro ao salvar no banco de dados. Tente novamente.';
    
    // Se for um erro de duplicata (protocolo - muito raro)
    if ($e->getCode() == 1062) { 
        $message = 'Erro ao gerar protocolo. Por favor, tente enviar novamente.';
    }
    
    echo json_encode(['status' => 'error', 'message' => $message, 'details' => $e->getMessage()]);
}

$conn->close();
?>