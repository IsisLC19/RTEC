<?php
// 1. INCLUI O ARQUIVO DE CONEXÃO
// Como ele está na mesma pasta, isso vai funcionar.
require_once 'conexao.php'; // $conn (conexão) já está disponível

// 2. CAPTURA OS DADOS VINDOS DO FORMULÁRIO (cadastro.html)
// Usamos $_POST para pegar os valores enviados pelo 'method="POST"'
$nome = $_POST['nome_usuario'];
$sobrenome = $_POST['sobrenome_usuario'];
$email = $_POST['email_usuario'];
$telefone = $_POST['telefone_usuario']; 
$senha = $_POST['senha_usuario'];
$confirmar = $_POST['confirmar_senha'];

// 3. VALIDAÇÃO SIMPLES (Se as senhas batem)
if ($senha !== $confirmar) {
    // Se não baterem, exibe um alerta e para o script.
    echo "<script>alert('As senhas não coincidem!'); window.history.back();</script>";
    exit(); // Para a execução do script
}

// 4. CRIPTOGRAFA A SENHA (Muito importante para segurança)
$senha_hash = password_hash($senha, PASSWORD_BCRYPT);

// 5. DEFINE O TIPO DE USUÁRIO PADRÃO
// O seu banco de dados (tbl_usuario) exige um 'tipo_usuario'
// Vamos definir 'monitor' como padrão para qualquer pessoa que se cadastrar.
$tipo_usuario = 'monitor'; 

// 6. PREPARA A CONSULTA SQL (INSERT)
// Usamos a tabela 'tbl_usuario' e as colunas corretas do seu banco.
$sql = "INSERT INTO tbl_usuario 
            (nome_usuario, sobrenome_usuario, email_usuario, telefone_usuario, senha_usuario, tipo_usuario) 
        VALUES 
            (?, ?, ?, ?, ?, ?)";

// O 'prepare' protege contra injeção de SQL
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Erro ao preparar a consulta: " . $conn->error);
}

// 7. VINCULA OS VALORES À CONSULTA
// "ssssss" significa que estamos enviando 6 variáveis do tipo String (s)
$stmt->bind_param("ssssss", 
    $nome, 
    $sobrenome, 
    $email, 
    $telefone, 
    $senha_hash, 
    $tipo_usuario
);

// 8. EXECUTA A CONSULTA E VERIFICA O RESULTADO
if ($stmt->execute()) {
    // Se deu certo, avisa e redireciona para o login
    echo "<script>alert('Cadastro realizado com sucesso!'); window.location.href='entrar.html';</script>";
} else {
    // Verifica se o erro é de e-mail duplicado (Código 1062)
    if ($conn->errno === 1062) {
        echo "<script>alert('Erro: Este e-mail já está cadastrado!'); window.history.back();</script>";
    } else {
        // Outro tipo de erro
        echo "<script>alert('Erro ao realizar o cadastro. Tente novamente.'); window.history.back();</script>";
        // Para depuração: echo "Erro: " . $stmt->error;
    }
}

// 9. FECHA A CONEXÃO
$stmt->close();
$conn->close();

?>