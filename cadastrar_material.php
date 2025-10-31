<?php
// 1. INICIA A SESSÃO E CONEXÃO
session_start();
header('Content-Type: application/json');
require_once 'conexao.php'; // $conn

// 2. VERIFICA SE O MONITOR ESTÁ LOGADO
// Apenas monitores logados podem cadastrar material
if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'monitor' || !isset($_SESSION['id_usuario'])) {
    http_response_code(403); // Proibido (Forbidden)
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado. Faça login como monitor.']);
    exit();
}

// 3. PEGA OS DADOS (JSON) E O ID DO MONITOR
$data = json_decode(file_get_contents('php://input'));
$id_monitor = $_SESSION['id_usuario']; // ID de quem está cadastrando

// 4. VALIDAÇÃO BÁSICA
if (empty($data->id_tipo_material) || empty($data->category) || empty($data->id_tipo_destino)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Tipo, Categoria e Destino são obrigatórios.']);
    exit();
}

// 5. INICIA A TRANSAÇÃO
$conn->begin_transaction();

try {
    // 6. INSERE NA TABELA PRINCIPAL (tbl_material_cadastrado)
    $sql_material = "INSERT INTO tbl_material_cadastrado 
                        (id_tipo_material, id_monitor, id_tipo_destino, categoria, status_processamento)
                     VALUES (?, ?, ?, ?, 'Em Espera')";
                     
    $stmt_material = $conn->prepare($sql_material);
    $stmt_material->bind_param("iiss", 
        $data->id_tipo_material, 
        $id_monitor, 
        $data->id_tipo_destino, 
        $data->category
    );
    $stmt_material->execute();
    
    // 7. PEGA O ID DO MATERIAL QUE ACABOU DE SER CRIADO
    $id_material_cadastrado = $conn->insert_id;
    
    // 8. INSERE OS PROBLEMAS (se houver)
    if (!empty($data->problems) && is_array($data->problems)) {
        
        $sql_problema = "INSERT INTO tbl_material_problema_junction 
                            (id_material_cadastrado, id_tipo_problema) 
                         VALUES (?, ?)";
        $stmt_problema = $conn->prepare($sql_problema);
        
        foreach ($data->problems as $id_problema) {
            $id_problema_int = (int)$id_problema; // Garante que é um número
            $stmt_problema->bind_param("ii", $id_material_cadastrado, $id_problema_int);
            $stmt_problema->execute();
        }
        $stmt_problema->close();
    }
    
    // 9. CONFIRMA A TRANSAÇÃO (Salva tudo no banco)
    $conn->commit();
    
    // 10. BUSCA OS NOMES (para devolver ao JS e atualizar a lista ao vivo)
    $stmt_nomes = $conn->prepare("
        SELECT 
            (SELECT nome_material FROM tbl_tipomaterial WHERE id_tipo_material = ?) AS material_nome,
            (SELECT nome_destino FROM tbl_tipodestino WHERE id_tipo_destino = ?) AS destino_nome
    ");
    $stmt_nomes->bind_param("ii", $data->id_tipo_material, $data->id_tipo_destino);
    $stmt_nomes->execute();
    $nomes = $stmt_nomes->get_result()->fetch_assoc();
    
    // 'slug' é só um nome simples ('coleta' ou 'aula') para o JS entender
    $destino_slug = ($nomes['destino_nome'] == 'Para Coleta') ? 'coleta' : 'aula';
    
    // 11. ENVIA RESPOSTA DE SUCESSO
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Material cadastrado!',
        'id_material' => $id_material_cadastrado,
        'material_nome' => $nomes['material_nome'],
        'destino_slug' => $destino_slug // envia 'coleta' ou 'aula' para o JS
    ]);
    
    $stmt_material->close();
    $stmt_nomes->close();

} catch (mysqli_sql_exception $e) {
    // 12. ERRO! DESFAZ TUDO
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar no banco.', 'details' => $e->getMessage()]);
}

$conn->close();
?>