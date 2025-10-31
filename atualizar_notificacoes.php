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
    
    // 4. CAPTURA OS DADOS DO FORMULÁRIO E O TIPO DE USUÁRIO
    $id_usuario = $_SESSION['id_usuario'];
    $tipo_usuario = $_SESSION['tipo_usuario'];

    // 5. LÓGICA DOS CHECKBOXES
    // Se um checkbox estiver marcado, $_POST['nome_do_campo'] será '1'.
    // Se estiver DESMARCADO, ele não existirá (isset() será false).
    
    // Inicializa as variáveis
    $sql = "";
    $params_types = ""; // os "s" ou "i"
    $params_values = []; // os valores

    if ($tipo_usuario == 'funcionario') {
        // Funcionário só atualiza 'agendamento' e 'feedback'
        
        $notif_agendamento = isset($_POST['notif_agendamento']) ? 1 : 0;
        $notif_feedback = isset($_POST['notif_feedback']) ? 1 : 0;

        $sql = "UPDATE tbl_usuario 
                SET notif_agendamento = ?, 
                    notif_feedback = ?
                WHERE id_usuario = ?";
        
        $params_types = "iii"; // 3 inteiros (1, 0, id)
        $params_values = [$notif_agendamento, $notif_feedback, $id_usuario];

    } elseif ($tipo_usuario == 'monitor') {
        // Monitor só atualiza 'material'
        
        $notif_material = isset($_POST['notif_material']) ? 1 : 0;

        $sql = "UPDATE tbl_usuario 
                SET notif_material = ?
                WHERE id_usuario = ?";
        
        $params_types = "ii"; // 2 inteiros (1, id)
        $params_values = [$notif_material, $id_usuario];
        
    } else {
        // Tipo de usuário desconhecido
        echo "<script>alert('Erro: Tipo de usuário inválido.'); window.history.back();</script>";
        exit();
    }

    // 6. PREPARA E EXECUTA A CONSULTA
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Erro ao preparar a consulta: " . $conn->error);
    }
    
    // bind_param precisa de referências, não de valores diretos
    $stmt->bind_param($params_types, ...$params_values);

    // 7. VERIFICA O RESULTADO
    if ($stmt->execute()) {
        // Deu certo, avisa e volta para a aba "Notificações"
        echo "<script>
                alert('Preferências de notificação salvas com sucesso!'); 
                window.location.href='configuracoes.php#notificacoes';
              </script>";
    } else {
        // Erro
        echo "<script>
                alert('Erro ao salvar as preferências. Tente novamente.'); 
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