<?php
// 1. INICIA A SESSÃO
// A sessão é o que "guarda" que o usuário está logado.
// DEVE ser a primeira linha do arquivo.
session_start();

// 2. INCLUI A CONEXÃO
require_once 'conexao.php'; // A variável $conn vem daqui

// 3. VERIFICA SE O FORMULÁRIO FOI ENVIADO (MÉTODO POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 4. CAPTURA OS DADOS DO FORMULÁRIO (com os 'name' que você adicionou)
    $email = $_POST['email'];
    $senha_digitada = $_POST['senha'];

    // 5. PREPARA A CONSULTA PARA BUSCAR O USUÁRIO PELO E-MAIL
    // Nós pegamos o ID, nome, o hash da senha e o tipo de usuário
    $sql = "SELECT id_usuario, nome_usuario, senha_usuario, tipo_usuario 
            FROM tbl_usuario 
            WHERE email_usuario = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Erro ao preparar a consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // 6. VERIFICA SE O USUÁRIO FOI ENCONTRADO
    if ($result->num_rows === 1) {
        // Usuário (e-mail) encontrado! Pega os dados dele.
        $user = $result->fetch_assoc();
        
        // 7. VERIFICA A SENHA
        // Compara a senha digitada ($senha_digitada) com o hash salvo no banco ($user['senha_usuario'])
        // Esta é a parte crucial da segurança!
        if (password_verify($senha_digitada, $user['senha_usuario'])) {
            
            // 8. SENHA CORRETA! LOGIN BEM-SUCEDIDO
            // Armazena os dados do usuário na sessão para usar em outras páginas
            $_SESSION['loggedin'] = true;
            $_SESSION['id_usuario'] = $user['id_usuario'];
            $_SESSION['nome_usuario'] = $user['nome_usuario'];
            $_SESSION['tipo_usuario'] = $user['tipo_usuario']; // 'monitor' ou 'funcionario'
            
            // 9. REDIRECIONA COM BASE NO TIPO DE USUÁRIO
            if ($user['tipo_usuario'] == 'funcionario') {
                header("Location: funcionario.php"); // Redireciona para a página do funcionário
                exit();
            } elseif ($user['tipo_usuario'] == 'monitor') {
                header("Location: monitoria.php"); // Redireciona para a página do monitor
                exit();
            } else {
                // Segurança: caso o tipo de usuário não seja nenhum dos dois
                echo "<script>alert('Tipo de usuário desconhecido!'); window.location.href='entrar.html';</script>";
            }

        } else {
            // 7b. Senha incorreta
            echo "<script>alert('E-mail ou senha incorretos!'); window.location.href='entrar.html';</script>";
        }
    } else {
        // 6b. E-mail não encontrado
        echo "<script>alert('E-mail ou senha incorretos!'); window.location.href='entrar.html';</script>";
    }
    
    $stmt->close();
    
} else {
    // Se alguém tentar acessar o arquivo validar_login.php diretamente pela URL
    header("Location: entrar.html");
    exit();
}

$conn->close();
?>