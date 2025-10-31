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
    $nome = trim($_POST['nome']); // 'nome' é o name do input de texto
    $tipo_lista = $_POST['tipo_lista']; // 'residuo', 'material', 'problema', ou 'destino'

    // 5. VALIDAÇÃO SIMPLES
    if (empty($nome)) {
        echo "<script>alert('O nome não pode estar vazio!'); window.history.back();</script>";
        exit();
    }

    // 6. LÓGICA PARA DECIDIR ONDE SALVAR
    $tabela = "";
    $coluna = "";
    $tab_anchor = ""; // Para onde redirecionar de volta

    switch ($tipo_lista) {
        case 'residuo':
            // Segurança: Apenas 'funcionario' pode editar isso
            if ($_SESSION['tipo_usuario'] !== 'funcionario') break;
            $tabela = "tbl_tiporesiduo";
            $coluna = "nome_residuo";
            $tab_anchor = "#sistema"; // Para voltar à aba Sistema
            break;
        case 'material':
            // Segurança: Apenas 'monitor' pode editar isso
            if ($_SESSION['tipo_usuario'] !== 'monitor') break;
            $tabela = "tbl_tipomaterial";
            $coluna = "nome_material";
            $tab_anchor = "#monitoria"; // Para voltar à aba Monitoria
            break;
        case 'problema':
            if ($_SESSION['tipo_usuario'] !== 'monitor') break;
            $tabela = "tbl_tipoproblema";
            $coluna = "nome_problema";
            $tab_anchor = "#monitoria";
            break;
        case 'destino':
            if ($_SESSION['tipo_usuario'] !== 'monitor') break;
            $tabela = "tbl_tipodestino";
            $coluna = "nome_destino";
            $tab_anchor = "#monitoria";
            break;
        default:
            echo "<script>alert('Tipo de lista inválido ou permissão negada!'); window.history.back();</script>";
            exit();
    }
    
    if (empty($tabela)) {
         echo "<script>alert('Permissão negada para esta ação!'); window.history.back();</script>";
         exit();
    }

    // 7. PREPARA E EXECUTA A CONSULTA
    // Como $tabela e $coluna são definidos por nós (e não pelo usuário),
    // é seguro colocar na string SQL. O '?' protege o $nome.
    $sql = "INSERT INTO $tabela ($coluna) VALUES (?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Erro ao preparar a consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $nome);

    // 8. EXECUTA E VERIFICA O RESULTADO
    if ($stmt->execute()) {
        // Redireciona de volta para a página de configurações, abrindo a aba correta
        echo "<script>
                alert('Item adicionado com sucesso!'); 
                window.location.href='configuracoes.php" . $tab_anchor . "';
              </script>";
    } else {
        // Erro 1062 = Entrada Duplicada (o item já existe)
        if ($conn->errno === 1062) {
            echo "<script>
                    alert('Erro: Esse item já existe no cadastro!'); 
                    window.history.back();
                  </script>";
        } else {
            echo "<script>
                    alert('Erro ao adicionar o item. Tente novamente.'); 
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