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

// 3. VERIFICA SE OS DADOS VIERAM PELA URL (GET)
if (isset($_GET['tipo']) && isset($_GET['id'])) {
    
    // 4. CAPTURA OS DADOS DA URL
    $tipo_lista = $_GET['tipo'];
    $id = (int)$_GET['id']; // Converte o ID para um número inteiro por segurança

    // 5. LÓGICA PARA DECIDIR DE ONDE EXCLUIR
    $tabela = "";
    $coluna_id = "";
    $tab_anchor = ""; // Para onde redirecionar de volta

    switch ($tipo_lista) {
        case 'residuo':
            // Segurança: Apenas 'funcionarios' podem mexer em resíduos
            if ($_SESSION['tipo_usuario'] !== 'funcionario') break;
            $tabela = "tbl_tiporesiduo";
            $coluna_id = "id_tipo_residuo";
            $tab_anchor = "#sistema";
            break;
        case 'material':
            // Segurança: Apenas 'monitores' podem mexer em materiais
            if ($_SESSION['tipo_usuario'] !== 'monitor') break;
            $tabela = "tbl_tipomaterial";
            $coluna_id = "id_tipo_material";
            $tab_anchor = "#monitoria";
            break;
        case 'problema':
            if ($_SESSION['tipo_usuario'] !== 'monitor') break;
            $tabela = "tbl_tipoproblema";
            $coluna_id = "id_tipo_problema";
            $tab_anchor = "#monitoria";
            break;
        case 'destino':
            if ($_SESSION['tipo_usuario'] !== 'monitor') break;
            $tabela = "tbl_tipodestino";
            $coluna_id = "id_tipo_destino";
            $tab_anchor = "#monitoria";
            break;
        default:
            echo "<script>alert('Tipo de lista inválido!'); window.history.back();</script>";
            exit();
    }
    
    // Se a tabela não foi definida (ex: um monitor tentou excluir um resíduo)
    if (empty($tabela)) {
         echo "<script>alert('Você não tem permissão para esta ação!'); window.history.back();</script>";
         exit();
    }

    // 6. PREPARA E EXECUTA A CONSULTA DELETE
    $sql = "DELETE FROM $tabela WHERE $coluna_id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Erro ao preparar a consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);

    // 7. EXECUTA E VERIFICA O RESULTADO
    if ($stmt->execute()) {
        echo "<script>
                alert('Item removido com sucesso!'); 
                window.location.href='configuracoes.php" . $tab_anchor . "';
              </script>";
    } else {
        // Erro 1451 = Violação de Chave Estrangeira (O item está em uso)
        if ($conn->errno === 1451) {
             echo "<script>
                    alert('Erro: Este item não pode ser removido pois já está em uso (ex: em um agendamento ou material cadastrado).'); 
                    window.history.back();
                  </script>";
        } else {
            echo "<script>
                    alert('Erro ao remover o item. Tente novamente.'); 
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