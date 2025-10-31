<?php
// 1. INICIA A SESSÃO E CONEXÃO
session_start();
require_once 'conexao.php'; // $conn

// 2. BLOCO DE SEGURANÇA: Verifica se o usuário está logado
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['id_usuario'])) {
    // Se não estiver logado, não pode atualizar nada.
    session_destroy();
    header("Location: entrar.html");
    exit();
}

// 3. VERIFICA SE OS DADOS VIERAM DO FORMULÁRIO (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 4. CAPTURA OS DADOS DO FORMULÁRIO
    $id_usuario = $_SESSION['id_usuario']; // Pega o ID do usuário logado
    $nome = $_POST['nome_usuario'];
    $sobrenome = $_POST['sobrenome_usuario'];
    $email = $_POST['email_usuario'];
    $telefone = $_POST['telefone_usuario'];

    // 5. PREPARA O UPDATE NO BANCO (tbl_usuario)
    // Atualiza os dados do usuário com base no ID da sessão
    $sql = "UPDATE tbl_usuario 
            SET nome_usuario = ?, 
                sobrenome_usuario = ?, 
                email_usuario = ?, 
                telefone_usuario = ?
            WHERE id_usuario = ?";
            
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Erro ao preparar a consulta: " . $conn->error);
    }
    
    // "ssssi" = 4 strings (nome, sobrenome, email, tel) e 1 integer (id_usuario)
    $stmt->bind_param("ssssi", $nome, $sobrenome, $email, $telefone, $id_usuario);

    // 6. EXECUTA E VERIFICA O RESULTADO
    if ($stmt->execute()) {
        // 7. ATUALIZA A SESSÃO (para o "Olá, Nome!" mudar imediatamente)
        $_SESSION['nome_usuario'] = $nome; 
        
        // Deu certo, avisa e volta para as configurações
        echo "<script>
                alert('Perfil atualizado com sucesso!'); 
                window.location.href='configuracoes.php';
              </script>";
    } else {
        // Verifica se o erro é de e-mail duplicado
        if ($conn->errno === 1062) {
            echo "<script>
                    alert('Erro: Este e-mail já está sendo usado por outra conta!'); 
                    window.history.back();
                  </script>";
        } else {
            // Outro tipo de erro
            echo "<script>
                    alert('Erro ao atualizar o perfil. Tente novamente.'); 
                    window.history.back();
                  </script>";
        }
    }

    $stmt->close();
    
} else {
    // Se alguém tentar acessar o arquivo diretamente
    header("Location: configuracoes.php");
    exit();
}

$conn->close();
?>