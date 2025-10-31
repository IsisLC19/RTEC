<?php
// 1. INICIA A SESSÃO
// É necessário iniciar a sessão para poder destruí-la.
session_start();

// 2. LIMPA TODAS AS VARIÁVEIS DA SESSÃO
// Define a sessão como um array vazio.
$_SESSION = array();

// 3. DESTRÓI A SESSÃO
// Remove o cookie de sessão e destrói os dados no servidor.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 4. REDIRECIONA PARA A PÁGINA DE LOGIN
// Envia o usuário de volta para o 'entrar.html'.
header("Location: entrar.html");
exit();
?>