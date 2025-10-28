<?php
// CONFIGURAÇÕES DO BANCO DE DADOS
$host = "localhost";
$usuario = "root"; // seu usuário do MySQL
$senha = "";       // sua senha do MySQL
$banco = "db_rtec"; // nome do banco

// CONEXÃO COM O BANCO
$conn = new mysqli($host, $usuario, $senha, $banco);

// VERIFICAR CONEXÃO
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// CAPTURA DOS DADOS DO FORMULÁRIO
$nome = $_POST['nome_usuario'];
$sobrenome = $_POST['sobrenome_usuario'];
$email = $_POST['email_usuario'];
$telefone = $_POST['telefone_usuario'];
$senha = $_POST['senha_usuario'];
$confirmar = $_POST['confirmar_senha'];
$consentimento = isset($_POST['consentimento_dados']) ? 1 : 0;

// VERIFICA SE AS SENHAS COINCIDEM
if ($senha !== $confirmar) {
    die("<script>alert('As senhas não coincidem!'); window.history.back();</script>");
}

// CRIPTOGRAFA A SENHA
$senha_hash = password_hash($senha, PASSWORD_BCRYPT);

// PREPARA A INSERÇÃO
$stmt = $conn->prepare("
    INSERT INTO Usuario 
    (nome_usuario, sobrenome_usuario, email_usuario, telefone_usuario, senha_usuario, consentimento_dados)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("sssssi", $nome, $sobrenome, $email, $telefone, $senha_hash, $consentimento);

// EXECUTA E VERIFICA RESULTADO
if ($stmt->execute()) {
    echo "<script>alert('Cadastro realizado com sucesso!'); window.location.href='entrar.html';</script>";
} else {
    if ($conn->errno === 1062) { // erro de email duplicado
        echo "<script>alert('E-mail já cadastrado!'); window.history.back();</script>";
    } else {
        echo "Erro ao cadastrar: " . $conn->error;
    }
}

$stmt->close();
$conn->close();
?>
