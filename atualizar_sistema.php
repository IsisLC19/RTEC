<?php
// 1. INICIA A SESSÃO E CONEXÃO
session_start();
require_once 'conexao.php'; // $conn

// 2. BLOCO DE SEGURANÇA: Verifica se o usuário é um 'funcionario'
if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'funcionario') {
    // Apenas funcionários podem mudar as configurações do sistema
    session_destroy();
    echo "<script>alert('Acesso negado!'); window.location.href='entrar.html';</script>";
    exit();
}

// 3. VERIFICA SE OS DADOS VIERAM DO FORMULÁRIO (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 4. CAPTURA OS DADOS DO FORMULÁRIO
    $endereco = $_POST['endereco_coleta'];
    $horario_inicio = $_POST['horario_inicio'];
    $horario_fim = $_POST['horario_fim'];

    // 5. PREPARA O UPDATE NO BANCO (tbl_configuracoes_sistema)
    // Nós sempre atualizamos a linha onde id_config = 1
    $sql = "UPDATE tbl_configuracoes_sistema 
            SET endereco_coleta = ?, 
                horario_inicio = ?, 
                horario_fim = ?
            WHERE id_config = 1";
            
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Erro ao preparar a consulta: " . $conn->error);
    }
    
    // "sss" = 3 strings (endereco, inicio, fim)
    $stmt->bind_param("sss", $endereco, $horario_inicio, $horario_fim);

    // 6. EXECUTA E VERIFICA O RESULTADO
    if ($stmt->execute()) {
        // Deu certo, avisa e volta para a aba "Sistema"
        echo "<script>
                alert('Configurações do sistema atualizadas com sucesso!'); 
                window.location.href='configuracoes.php#sistema';
              </script>";
    } else {
        // Erro
        echo "<script>
                alert('Erro ao atualizar as configurações. Tente novamente.'); 
                window.history.back();
              </script>";
    }

    $stmt->close();
    
} else {
    // Se alguém tentar acessar o arquivo diretamente
    header("Location: configuracoes.php");
    exit();
}

$conn->close();
?>