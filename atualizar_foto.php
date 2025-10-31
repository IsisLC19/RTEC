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

    $id_usuario = $_SESSION['id_usuario'];

    // 4. VERIFICA SE UM ARQUIVO FOI ENVIADO E SE NÃO HÁ ERROS
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {

        $file = $_FILES['foto_perfil'];
        $file_name = $file['name'];
        $file_tmp_name = $file['tmp_name']; // Caminho temporário
        $file_size = $file['size'];
        $file_error = $file['error'];

        // Pega a extensão do arquivo (ex: "jpg")
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // 5. VALIDAÇÃO DO ARQUIVO
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        $max_file_size = 2 * 1024 * 1024; // 2 Megabytes

        if (!in_array($file_ext, $allowed_extensions)) {
            echo "<script>alert('Erro: Apenas arquivos JPG, JPEG e PNG são permitidos!'); window.history.back();</script>";
            exit();
        }

        if ($file_size > $max_file_size) {
            echo "<script>alert('Erro: O arquivo é muito grande (máximo 2MB)!'); window.history.back();</script>";
            exit();
        }

        // 6. PREPARA O NOVO NOME E CAMINHO
        // Usamos a pasta 'img/' que você já tem.
        $upload_directory = 'img/';
        // Cria um nome único (ex: perfil_605c725a1b2d3.jpg)
        $new_file_name = 'perfil_' . uniqid() . '.' . $file_ext;
        $target_file_path = $upload_directory . $new_file_name;

        // 7. PEGA O CAMINHO DA FOTO ANTIGA (para apagar depois)
        $stmt_old = $conn->prepare("SELECT foto_perfil_url FROM tbl_usuario WHERE id_usuario = ?");
        $stmt_old->bind_param("i", $id_usuario);
        $stmt_old->execute();
        $old_file_path = $stmt_old->get_result()->fetch_assoc()['foto_perfil_url'];
        $stmt_old->close();

        // 8. TENTA MOVER O ARQUIVO
        if (move_uploaded_file($file_tmp_name, $target_file_path)) {
            
            // 9. ARQUIVO MOVIDO COM SUCESSO! ATUALIZA O BANCO
            $sql_update = "UPDATE tbl_usuario SET foto_perfil_url = ? WHERE id_usuario = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("si", $target_file_path, $id_usuario);
            
            if ($stmt_update->execute()) {
                
                // 10. APAGA A FOTO ANTIGA (se não for a padrão)
                if ($old_file_path != 'img/usuario.png' && file_exists($old_file_path)) {
                    unlink($old_file_path); // Apaga o arquivo antigo do servidor
                }
                
                echo "<script>alert('Foto de perfil atualizada com sucesso!'); window.location.href='configuracoes.php';</script>";
            } else {
                echo "<script>alert('Erro ao salvar o caminho da foto no banco.'); window.history.back();</script>";
            }
            $stmt_update->close();
            
        } else {
            echo "<script>alert('Erro ao mover o arquivo para o servidor.'); window.history.back();</script>";
        }

    } else {
        // Erro no upload (ex: arquivo muito grande, nenhum arquivo enviado)
        echo "<script>alert('Nenhum arquivo enviado ou erro no upload!'); window.history.back();</script>";
    }
    
} else {
    // Se alguém tentar acessar o arquivo diretamente
    header("Location: configuracoes.php");
    exit();
}

$conn->close();
?>