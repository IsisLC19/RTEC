<?php
// 1. INICIA A SESSÃO E CONEXÃO
session_start();
require_once 'conexao.php'; // $conn

// 2. BLOCO DE SEGURANÇA: Apenas 'funcionarios' logados podem remover usuários
if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'funcionario') {
    session_destroy();
    echo "<script>alert('Acesso negado!'); window.location.href='entrar.html';</script>";
    exit();
}

// 3. VERIFICA SE OS DADOS VIERAM PELA URL (GET)
if (isset($_GET['id'])) {
    
    // 4. CAPTURA OS DADOS DA URL
    $id_para_remover = (int)$_GET['id']; // ID do usuário a ser removido
    $id_logado = (int)$_SESSION['id_usuario']; // ID do funcionário que está logado

    // 5. VERIFICAÇÃO DE SEGURANÇA: NÃO SE PODE AUTO-DELETAR
    if ($id_para_remover === $id_logado) {
        echo "<script>
                alert('Erro: Você não pode remover a si mesmo!'); 
                window.location.href='configuracoes.php#sistema';
              </script>";
        exit();
    }

    // 6. PREPARA E EXECUTA A CONSULTA DELETE
    $sql = "DELETE FROM tbl_usuario WHERE id_usuario = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Erro ao preparar a consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id_para_remover);

    // 7. EXECUTA E VERIFICA O RESULTADO
    if ($stmt->execute()) {
        echo "<script>
                alert('Usuário removido com sucesso!'); 
                window.location.href='configuracoes.php#sistema';
              </script>";
    } else {
        // Erro 1451 = Violação de Chave Estrangeira
        // Isso acontece se o usuário que você está tentando apagar
        // ainda possui registros ligados a ele (ex: materiais cadastrados, feedbacks)
        if ($conn->errno === 1451) {
             echo "<script>
                     alert('Erro: Este usuário não pode ser removido pois possui registros (materiais, feedbacks, etc.) associados a ele.'); 
                     window.history.back();
                   </script>";
        } else {
            echo "<script>
                    alert('Erro ao remover o usuário. Tente novamente.'); 
                    window.history.back();
                  </script>";
        }
    }

    $stmt->close();
    
} else {
    // Se alguém tentar acessar o arquivo diretamente sem um ID
    header("Location: configuracoes.php");
    exit();
}

$conn->close();
?>