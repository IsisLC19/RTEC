<?php
// 1. INICIA A SESSÃO E CONEXÃO
session_start();
require_once 'conexao.php'; // $conn

// 2. BLOCO DE SEGURANÇA: Verifica se o usuário está logado
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['id_usuario'])) {
    session_destroy();
    header("Location: entrar.html");
    exit();
}

// 3. VERIFICA SE OS DADOS VIERAM DO FORMULÁRIO (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 4. CAPTURA OS DADOS DO FORMULÁRIO
    $id_usuario = $_SESSION['id_usuario'];
    
    // 5. LÓGICA DO CHECKBOX
    // Se o switch "Modo Escuro" estiver MARCADO, $_POST['preferencia_tema'] será 'dark'.
    // Se estiver DESMARCADO, ele não será enviado (isset() será false).
    if (isset($_POST['preferencia_tema']) && $_POST['preferencia_tema'] == 'dark') {
        $tema = 'escuro';
    } else {
        $tema = 'claro';
    }

    // 6. PREPARA O UPDATE NO BANCO (tbl_usuario)
    $sql = "UPDATE tbl_usuario 
            SET preferencia_tema = ?
            WHERE id_usuario = ?";
            
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Erro ao preparar a consulta: " . $conn->error);
    }
    
    // "si" = 1 string (tema) e 1 integer (id_usuario)
    $stmt->bind_param("si", $tema, $id_usuario);

    // 7. EXECUTA E REDIRECIONA
    if ($stmt->execute()) {
        // Deu certo, volta para as configurações
        // O script na própria página 'configuracoes.php' vai ler o novo valor
        // e aplicar o tema (dark/light) no próximo carregamento.
        header("Location: configuracoes.php");
        exit();
    } else {
        // Erro
        echo "<script>
                alert('Erro ao salvar a preferência de tema. Tente novamente.'); 
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