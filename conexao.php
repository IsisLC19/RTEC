<?php
// CONFIGURAÇÕES DO BANCO DE DADOS
$host = "localhost";
$usuario = "root"; // seu usuário do MySQL
$senha = "";       // sua senha do MySQL (provavelmente em branco no XAMPP padrão)
$banco = "db_rtec"; // nome do banco

// CONEXÃO COM O BANCO
$conn = new mysqli($host, $usuario, $senha, $banco);

// VERIFICAR CONEXÃO
if ($conn->connect_error) {
    // Para o script e mostra o erro
    die("Falha na conexão: " . $conn->connect_error);
}

// Garante que os dados (como acentos) sejam lidos e escritos corretamente
$conn->set_charset("utf8mb4");

?>