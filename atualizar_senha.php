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
    $senha_atual_digitada = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_nova_senha = $_POST['confirmar_senha'];

    // 5. VALIDAÇÃO: As novas senhas coincidem?
    if ($nova_senha !== $confirmar_nova_senha) {
        echo "<script>alert('As novas senhas não coincidem!'); window.history.back();</script>";
        exit();
    }

    // 6. VALIDAÇÃO: A nova senha é forte o suficiente? (Opcional, mas recomendado)
    if (strlen($nova_senha) < 6) {
        echo "<script>alert('A nova senha deve ter pelo menos 6 caracteres!'); window.history.back();</script>";
        exit();
    }

    // 7. BUSCA A SENHA ATUAL (HASH) NO BANCO DE DADOS
    $stmt_busca = $conn->prepare("SELECT senha_usuario FROM tbl_usuario WHERE id_usuario = ?");
    $stmt_busca->bind_param("i", $id_usuario);
    $stmt_busca->execute();
    $result = $stmt_busca->get_result();
    
    if ($result->num_rows !== 1) {
        // Isso não deve acontecer se o usuário está logado, mas é uma segurança
        echo "<script>alert('Erro: Usuário não encontrado.'); window.location.href='entrar.html';</script>";
        exit();
    }
    
    $user = $result->fetch_assoc();
    $hash_senha_do_banco = $user['senha_usuario'];
    $stmt_busca->close();

    // 8. VERIFICA SE A SENHA ATUAL DIGITADA BATE COM A DO BANCO
    // Esta é a verificação de segurança principal
    if (password_verify($senha_atual_digitada, $hash_senha_do_banco)) {
        
        // 9. SENHA ATUAL CORRETA! Criptografa a nova senha
        $novo_hash_senha = password_hash($nova_senha, PASSWORD_BCRYPT);
        
        // 10. ATUALIZA A SENHA NO BANCO
        $stmt_update = $conn->prepare("UPDATE tbl_usuario SET senha_usuario = ? WHERE id_usuario = ?");
        $stmt_update->bind_param("si", $novo_hash_senha, $id_usuario);
        
        if ($stmt_update->execute()) {
            echo "<script>
                    alert('Senha atualizada com sucesso!'); 
                    window.location.href='configuracoes.php';
                  </script>";
        } else {
            echo "<script>
                    alert('Erro ao atualizar a senha. Tente novamente.'); 
                    window.history.back();
                  </script>";
        }
        $stmt_update->close();
        
    } else {
        // 8b. SENHA ATUAL INCORRETA
        echo "<script>alert('A senha atual está incorreta!'); window.history.back();</script>";
    }
    
} else {
    // Se alguém tentar acessar o arquivo diretamente
    header("Location: configuracoes.php");
    exit();
}

$conn->close();
?>