<?php
// 1. INICIA A SESSÃO
session_start();

// 2. INCLUI A CONEXÃO
require_once 'conexao.php'; // $conn

// 3. BLOCO DE SEGURANÇA (Qualquer usuário logado pode ver esta página)
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['id_usuario'])) {
    session_destroy();
    header("Location: entrar.html");
    exit();
}

// 4. PEGA INFORMAÇÕES BÁSICAS DA SESSÃO
$id_usuario = $_SESSION['id_usuario'];
$tipo_usuario = $_SESSION['tipo_usuario']; // 'monitor' or 'funcionario'

// 5. BUSCA DE DADOS (QUERIES)

// --- DADOS DO PERFIL DO USUÁRIO (Para todos) ---
$stmt_user = $conn->prepare("
    SELECT nome_usuario, sobrenome_usuario, email_usuario, telefone_usuario, foto_perfil_url, 
           preferencia_tema, notif_agendamento, notif_feedback, notif_material 
    FROM tbl_usuario 
    WHERE id_usuario = ?
");
$stmt_user->bind_param("i", $id_usuario);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

// Prepara variáveis para o HTML
$nome_usuario = $user_data['nome_usuario'];
$sobrenome_usuario = $user_data['sobrenome_usuario'];
$email_usuario = $user_data['email_usuario'];
$foto_perfil = $user_data['foto_perfil_url'];

// --- DADOS PARA ABAS (Depende do tipo de usuário) ---
$outros_usuarios = null;
$config_sistema = null;
$tipos_residuo = null;
$tipos_material = null;
$tipos_problema = null;
$tipos_destino = null;

if ($tipo_usuario == 'funcionario') {
    // --- DADOS PARA ABA SISTEMA (Funcionário) ---
    $outros_usuarios = $conn->query("SELECT id_usuario, email_usuario, tipo_usuario FROM tbl_usuario WHERE id_usuario != $id_usuario");
    
    $config_sistema = $conn->query("SELECT endereco_coleta, TIME_FORMAT(horario_inicio, '%H:%i') AS h_inicio, TIME_FORMAT(horario_fim, '%H:%i') AS h_fim FROM tbl_configuracoes_sistema WHERE id_config = 1")->fetch_assoc();
    
    $tipos_residuo = $conn->query("SELECT id_tipo_residuo, nome_residuo FROM tbl_tiporesiduo ORDER BY nome_residuo ASC");

} elseif ($tipo_usuario == 'monitor') {
    // --- DADOS PARA ABA MONITORIA (Monitor) ---
    $tipos_material = $conn->query("SELECT id_tipo_material, nome_material FROM tbl_tipomaterial ORDER BY nome_material ASC");
    $tipos_problema = $conn->query("SELECT id_tipo_problema, nome_problema FROM tbl_tipoproblema ORDER BY nome_problema ASC");
    $tipos_destino = $conn->query("SELECT id_tipo_destino, nome_destino FROM tbl_tipodestino ORDER BY nome_destino ASC");
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configurações - RTEC</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    darkMode: 'class', // <-- Esta é a linha mais importante
    theme: {
      extend: {
        // (Você pode adicionar cores, etc. aqui no futuro)
      }
    }
  }
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="icon" href="img/logo.png" type="image/x-icon">

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    body {
        font-family: 'Inter', sans-serif;
        box-sizing: border-box;
    }

    .nav-link:hover {
        color: #10b981;
        transform: translateY(-1px);
    }

    .filter-active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white !important;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    .filter-inactive:hover {
        background-color: #f3f4f6; /* gray-100 */
    }
    .dark .filter-inactive:hover {
        background-color: #374151; /* dark:bg-gray-700 */
    }

    #mobile-menu { position: fixed; top: 0; right: 0; width: 80%; max-width: 300px; height: 100vh; background-color: rgba(255, 255, 255, 0.98); backdrop-filter: blur(8px); box-shadow: -8px 0 20px rgba(0,0,0,0.2); transform: translateX(100%); transition: transform 0.4s ease-out; z-index: 100; display: flex; flex-direction: column; }
    .dark #mobile-menu {
        background-color: rgba(31, 41, 55, 0.98); /* dark:bg-gray-800/98 */
        border-left: 1px solid #374151; /* dark:border-gray-700 */
    }
    #mobile-menu.active { transform: translateX(0); }
    #mobile-menu-header { padding: 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
    .dark #mobile-menu-header {
        border-bottom-color: #374151; /* dark:border-gray-700 */
    }
    #mobile-menu .nav-list-mobile { padding: 1.5rem 1rem; flex-grow: 1; }
    #mobile-menu .nav-list-mobile li a { display: block; padding: 0.75rem 1rem; margin-bottom: 0.5rem; font-size: 1.1rem; font-weight: 500; color: #374151; border-radius: 0.5rem; transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease; position: relative; }
    .dark #mobile-menu .nav-list-mobile li a {
        color: #d1d5db; /* dark:text-gray-300 */
    }
    #mobile-menu .nav-list-mobile li a:hover { background-color: #ecfdf5; color: #047857; transform: translateX(5px); }
    .dark #mobile-menu .nav-list-mobile li a:hover {
        background-color: #374151; /* dark:bg-gray-700 */
        color: #34d399; /* dark:text-green-400 */
    }
    #mobile-menu .nav-list-mobile li .active-link-mobile { background-color: #d1fae5; color: #047857; font-weight: 700; border-left: 4px solid #10b981; padding-left: 1rem; }
    .dark #mobile-menu .nav-list-mobile li .active-link-mobile {
        background-color: #065f46; /* dark:bg-green-800 */
        color: #6ee7b7; /* dark:text-green-300 */
        border-left-color: #10b981;
    }
    .mobile-menu-footer { padding: 1rem; margin-top: auto; border-top: 1px solid #e5e7eb; background-color: #f9fafb; }
    .dark .mobile-menu-footer {
        border-top-color: #374151; /* dark:border-gray-700 */
        background-color: #1f2937; /* dark:bg-gray-800 */
    }
    .mobile-menu-footer .btn-mobile-login { display: block; width: 100%; text-align: center; padding: 0.75rem 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.2s ease; }
    .btn-mobile-logout { background-color: #fee2e2; color: #b91c1c; margin-top: 0.5rem; }
    .btn-mobile-logout:hover { background-color: #fecaca; color: #991b1b; }
    .dark .btn-mobile-logout {
        background-color: #450a0a; /* dark:bg-red-900 */
        color: #fca5a5; /* dark:text-red-300 */
    }
     .dark .btn-mobile-logout:hover {
        background-color: #7f1d1d; /* dark:hover:bg-red-800 */
        color: #fecaca; /* dark:hover:text-red-200 */
    }
    .managed-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        background-color: #f9fafb; /* gray-50 */
        border: 1px solid #e5e7eb; /* border-gray-200 */
        border-radius: 0.5rem;
    }
    .dark .managed-list li {
        background-color: #374151; /* dark:bg-gray-700 */
        border-color: #4b5563; /* dark:border-gray-600 */
        color: #d1d5db; /* dark:text-gray-300 */
    }
    .remove-btn {
        background: none;
        border: none;
        color: #ef4444; /* red-500 */
        cursor: pointer;
        transition: color 0.2s;
    }
    .remove-btn:hover { color: #b91c1c; /* red-700 */}
    .dark .remove-btn {
        color: #fca5a5; /* dark:text-red-300 */
    }
    .dark .remove-btn:hover {
        color: #fda4af; /* dark:hover:text-red-400 */
    }
    .toggle-bg:after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        background: white;
        width: 1.25rem; /* h-5 */
        height: 1.25rem; /* w-5 */
        border-radius: 50%;
        transition: all 0.3s;
    }
    input:checked + .toggle-bg:after {
        transform: translateX(100%);
    }
    input:checked + .toggle-bg {
        background-color: #10b981; /* green-500 */
    }
    .dark .toggle-bg {
        background-color: #4b5563; /* dark:bg-gray-600 */
    }
    .dark input:checked + .toggle-bg {
        background-color: #10b981; /* dark:checked:bg-green-500 */
    }
    .font-controls {
      position: fixed;
      bottom: 460px; 
      right: 10px; 
      z-index: 999;
      background: none;
      padding: 0;
    }
    @media (max-width: 768px) {
      .font-controls {
        bottom: 90px; 
        right: 15px; 
      }
    }
    #toggleFontButtons {
        width: 40px; 
        height: 40px;
        line-height: 40px; 
        padding: 0; 
        font-size: 18px !important; 
        text-align: center; 
    }
    .font-controls button {
        padding: 6px 10px;
        font-size: 14px;
        cursor: pointer;
        border: none;
        border-radius: 4px;
        background-color: #007bff; 
        color: white; 
    }
    #fontButtons button {
        background-color: #3f90ff; 
    }
    #fontButtons.hidden {
        display: none;
    }
    #fontButtons {
        position: absolute;
        bottom: 100%; 
        right: 0; 
        display: flex;
        flex-direction: column-reverse; 
        gap: 3px;
        margin-bottom: 5px; 
        margin-top: 0; 
    }
    @media (max-width: 400px) {
      .font-controls {
        top: 350px;
        right: 5px;
        padding: 3;
      }
      .font-controls button {
        padding: 4px 8px;
        font-size: 12px;
      }
      #fontButtons {
        gap: 2px;
        margin-bottom: 4px;
      }
    }
</style>
<script>
    // Este script aplica o tema (claro ou escuro) ANTES da página carregar
    // Ele lê a preferência salva no banco (que foi colocada no 'checked' do PHP)
    // ou o localStorage
    var savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark' || (!savedTheme && '<?php echo $user_data['preferencia_tema']; ?>' === 'dark')) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div vw="httpsAcess" class="enabled">
        <div vw-access-button class="active"></div>
        <div vw-plugin-wrapper>
            <div class="vw-plugin-top-wrapper"></div>
        </div>
    </div>
    <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
    <script>
        new window.VLibras.Widget('https://vlibras.gov.br/app');
    </script>

    <div class="font-controls">
    <button id="toggleFontButtons" aria-label="Controle de fonte">A</button>
    <div id="fontButtons" class="hidden">
        <button onclick="alterarFonte(1)">A+</button>
        <button onclick="alterarFonte(0)">A</button>
        <button onclick="alterarFonte(-1)">A-</button>
    </div>
    </div>

<header class="bg-white dark:bg-gray-800 shadow-lg relative z-50">
<nav class="bg-gray-100 dark:bg-gray-700 py-2">
    <div class="container mx-auto px-4 flex justify-end items-center space-x-4">
        <button id="theme-toggle-button" aria-label="Toggle light/dark theme" class="text-gray-600 dark:text-gray-300 hover:text-green-600 dark:hover:text-green-400 focus:outline-none transition-colors duration-200 text-lg">
            <i class="fas fa-sun" id="theme-toggle-icon"></i>
        </button>
        <div class="relative inline-block" id="profileDropdownContainer">
            <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Usuário" id="profileButton" class="h-10 w-10 rounded-full object-cover cursor-pointer border-2 border-green-600 hover:border-green-800 transition" onclick="toggleProfileDropdown()">
            <div id="profileDropdown" class="absolute right-0 mt-2 w-48 rounded-lg shadow-xl bg-white dark:bg-gray-700 ring-1 ring-black dark:ring-gray-600 ring-opacity-5 z-50 hidden">
                <div class="py-1">
                    <p class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 font-semibold border-b dark:border-gray-600">
                        Olá, <?php echo htmlspecialchars($nome_usuario); ?>
                    </p>
                    
                    <?php if ($tipo_usuario == 'funcionario'): ?>
                        <a href="coletores.html" id="dropdown-link-1" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600"><i class="fas fa-chart-line mr-2"></i>Dashboard</a>
                        <a href="funcionario.php" id="dropdown-link-2" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600"><i class="fas fa-file-alt mr-2"></i>Relatórios</a>
                    <?php else: // 'monitor' ?>
                        <a href="monitoria.php" id="dropdown-link-1" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600"><i class="fas fa-desktop mr-2"></i>Monitor</a>
                        <a href="monitoria.php#relatorios" id="dropdown-link-2" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600"><i class="fas fa-file-alt mr-2"></i>Relatórios</a>
                    <?php endif; ?>
                    
                    <a href="configuracoes.php" class="block px-4 py-2 text-sm text-green-600 dark:text-green-400 bg-gray-100 dark:bg-gray-600 font-medium"><i class="fas fa-cog mr-2"></i>Configurações</a>
                    <a href="logout.php" onclick="fazerLogout(event)" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900 hover:text-red-700 dark:hover:text-red-300 border-t dark:border-gray-600 mt-1"><i class="fas fa-sign-out-alt mr-2"></i>Sair</a>
                </div>
            </div>
        </div>
    </div>
</nav>
<nav class="py-4">
    <div class="container mx-auto px-4 flex items-center justify-between">
        <a href="sobre.html" class="block"><img src="img/logo.png" alt="Logo" class="h-12 w-auto"></a>
        
        <ul class="hidden md:flex space-x-8" id="main-nav-links">
            <?php if ($tipo_usuario == 'funcionario'): ?>
                <li><a href="coletores.html" class="nav-link text-gray-700 dark:text-gray-300 font-medium hover:text-green-600 dark:hover:text-green-400">DASHBOARD</a></li>
                <li><a href="funcionario.php" class="nav-link text-gray-700 dark:text-gray-300 font-medium hover:text-green-600 dark:hover:text-green-400">RELATÓRIOS</a></li>
            <?php else: // 'monitor' ?>
                <li><a href="monitoria.php" class="nav-link text-gray-700 dark:text-gray-300 font-medium hover:text-green-600 dark:hover:text-green-400">MONITOR</a></li>
                <li><a href="monitoria.php#relatorios" class="nav-link text-gray-700 dark:text-gray-300 font-medium hover:text-green-600 dark:hover:text-green-400">RELATÓRIOS</a></li>
            <?php endif; ?>
            <li><a href="configuracoes.php" class="nav-link text-green-600 dark:text-green-400 font-medium border-b-2 border-green-600">CONFIGURAÇÕES</a></li>
        </ul>
        
        <button id="menu-btn" class="md:hidden text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 focus:outline-none p-2 rounded-md transition-colors duration-200 hover:bg-green-50 dark:hover:bg-gray-700">
            <i class="fas fa-bars fa-xl"></i>
        </button>
    </div>
</nav>
</header>

<div id="mobile-menu" class="hidden">
<div id="mobile-menu-header">
    <img src="img/logo.png" alt="Logo RTEC" class="h-10 w-auto">
    <button id="close-menu-btn" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white focus:outline-none w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
        <i class="fas fa-times fa-lg"></i>
    </button>
</div>
<ul class="nav-list-mobile" id="mobile-nav-links">
    <?php if ($tipo_usuario == 'funcionario'): ?>
        <li><a href="coletores.html">DASHBOARD</a></li>
        <li><a href="funcionario.php">RELATÓRIOS</a></li>
    <?php else: // 'monitor' ?>
        <li><a href="monitoria.php">MONITOR</a></li>
        <li><a href="monitoria.php#relatorios">RELATÓRIOS</a></li>
    <?php endif; ?>
    <li><a href="configuracoes.php" class="active-link-mobile">CONFIGURAÇÕES</a></li>
</ul>
<div class="mobile-menu-footer">
    <a href="logout.php" onclick="fazerLogout(event); window.closeMobileMenu();" class="btn-mobile-login btn-mobile-logout"><i class="fas fa-sign-out-alt mr-2"></i>Sair</a>
</div>
</div>

<div id="menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-[99] hidden"></div>

    <main class="py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 dark:text-gray-100 mb-2">Configurações</h1>
                <p class="text-lg sm:text-xl text-gray-600 dark:text-gray-300">Gerencie seu perfil e as preferências do sistema</p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-2 justify-center items-center md:items-stretch mb-10 px-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-2 shadow-md w-full md:w-auto">
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                        <button onclick="showSection('perfil')" id="perfilTab" class="tab-button filter-inactive filter-active flex-1 px-4 py-2 rounded-lg font-semibold transition-all duration-300 flex items-center justify-center text-sm"><i class="fas fa-user-cog mr-2"></i>Perfil</button>
                        
                        <?php if ($tipo_usuario == 'funcionario'): ?>
                        <button onclick="showSection('sistema')" id="sistemaTab" class="tab-button filter-inactive flex-1 px-4 py-2 rounded-lg font-semibold text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 transition-all duration-300 flex items-center justify-center text-sm"><i class="fas fa-cogs mr-2"></i>Sistema</button>
                        <?php endif; ?>
                        
                        <?php if ($tipo_usuario == 'monitor'): ?>
                        <button onclick="showSection('monitoria')" id="monitoriaTab" class="tab-button filter-inactive flex-1 px-4 py-2 rounded-lg font-semibold text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 transition-all duration-300 flex items-center justify-center text-sm"><i class="fas fa-desktop mr-2"></i>Monitoria</button>
                        <?php endif; ?>
                        
                        <button onclick="showSection('notificacoes')" id="notificacoesTab" class="tab-button filter-inactive flex-1 px-4 py-2 rounded-lg font-semibold text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 transition-all duration-300 flex items-center justify-center text-sm"><i class="fas fa-bell mr-2"></i>Notificações</button>
                    </div>
                </div>
            </div>

            <div id="perfilSection" class="settings-section grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-user-edit mr-2 text-green-600 dark:text-green-400"></i>Alterar Dados Pessoais</h3>
                    <form action="atualizar_perfil.php" method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="nome_usuario" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                                <input type="text" id="nome_usuario" name="nome_usuario" value="<?php echo htmlspecialchars($nome_usuario); ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none">
                            </div>
                             <div>
                                <label for="sobrenome_usuario" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sobrenome</label>
                                <input type="text" id="sobrenome_usuario" name="sobrenome_usuario" value="<?php echo htmlspecialchars($sobrenome_usuario); ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none">
                            </div>
                        </div>
                        <div>
                            <label for="email_usuario" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">E-mail</label>
                            <input type="email" id="email_usuario" name="email_usuario" value="<?php echo htmlspecialchars($email_usuario); ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none">
                        </div>
                        <div>
                            <label for="telefone_usuario" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telefone</label>
                            <input type="tel" id="telefone_usuario" name="telefone_usuario" value="<?php echo htmlspecialchars($user_data['telefone_usuario']); ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none">
                        </div>
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-bold transition-all duration-300"><i class="fas fa-save mr-2"></i>Salvar Alterações</button>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-key mr-2 text-green-600 dark:text-green-400"></i>Alterar Senha</h3>
                    <form action="atualizar_senha.php" method="POST" class="space-y-4">
                        <div>
                            <label for="senha-atual" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Senha Atual</label>
                            <input type="password" id="senha-atual" name="senha_atual" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none" required>
                        </div>
                        <div>
                            <label for="nova-senha" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nova Senha</label>
                            <input type="password" id="nova-senha" name="nova_senha" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none" required>
                        </div>
                        <div>
                            <label for="confirmar-senha" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirmar Nova Senha</label>
                            <input type="password" id="confirmar-senha" name="confirmar_senha" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none" required>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-bold transition-all duration-300"><i class="fas fa-shield-alt mr-2"></i>Atualizar Senha</button>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8 lg:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-image mr-2 text-green-600 dark:text-green-400"></i>Alterar Foto de Perfil</h3>
                            <form action="atualizar_foto.php" method="POST" enctype="multipart/form-data" class="flex items-center space-x-4">
                                <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Usuário" id="profileImagePreview" class="h-20 w-20 rounded-full object-cover border-4 border-green-500">
                                <input type="file" id="profileImageInput" name="foto_perfil" accept="image/*" class="text-sm text-gray-600 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 dark:file:bg-gray-700 file:text-green-700 dark:file:text-green-300 hover:file:bg-green-100 dark:hover:file:bg-gray-600">
                                <button type="submit" class="bg-gray-200 dark:bg-gray-600 px-3 py-1 rounded-lg text-sm font-medium">Salvar</button>
                            </form>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-palette mr-2 text-green-600 dark:text-green-400"></i>Aparência</h3>
                            <form action="atualizar_tema.php" method="POST" id="themeForm">
                                <div class="flex items-center justify-between bg-gray-100 dark:bg-gray-700 p-4 rounded-lg">
                                    <span class="font-medium text-gray-700 dark:text-gray-200">Modo Escuro</span>
                                    <label for="themeToggle" class="cursor-pointer">
                                        <input type="checkbox" id="themeToggle" name="preferencia_tema" value="dark" class="hidden" <?php echo ($user_data['preferencia_tema'] == 'dark') ? 'checked' : ''; ?>>
                                        <div class="w-10 h-6 bg-gray-300 rounded-full flex items-center p-1 toggle-bg"></div>
                                    </label>
                                </div>
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-bold transition-all duration-300 mt-3 text-sm">Salvar Tema</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($tipo_usuario == 'funcionario'): ?>
            <div id="sistemaSection" class="settings-section hidden grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-users-cog mr-2 text-green-600 dark:text-green-400"></i>Gerenciamento de Usuários</h3>
                    <ul class="space-y-3 mb-4 managed-list">
                        <?php
                        if ($outros_usuarios->num_rows > 0) {
                            while($row = $outros_usuarios->fetch_assoc()) {
                                $tipo_label = ucfirst($row['tipo_usuario']);
                                echo '<li><span>' . htmlspecialchars($row['email_usuario']) . ' (' . $tipo_label . ')</span> <a href="remover_usuario.php?id=' . $row['id_usuario'] . '" class="remove-btn" onclick="return confirm(\'Tem certeza?\')"><i class="fas fa-trash-alt"></i></a></li>';
                            }
                        } else {
                            echo '<li class="italic text-gray-500">Nenhum outro usuário encontrado.</li>';
                        }
                        ?>
                    </ul>
                    </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-map-marker-alt mr-2 text-green-600 dark:text-green-400"></i>Gestão da Coleta</h3>
                    <form action="atualizar_sistema.php" method="POST" class="space-y-4">
                        <div>
                            <label for="endereco_coleta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Endereço de Coleta (para Agendamento.html)</label>
                            <input type="text" id="endereco_coleta" name="endereco_coleta" value="<?php echo htmlspecialchars($config_sistema['endereco_coleta']); ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="horario_inicio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Horário Início</label>
                                <input type="time" id="horario_inicio" name="horario_inicio" value="<?php echo htmlspecialchars($config_sistema['h_inicio']); ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none" style="color-scheme: dark;">
                            </div>
                             <div>
                                <label for="horario_fim" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Horário Fim</label>
                                <input type="time" id="horario_fim" name="horario_fim" value="<?php echo htmlspecialchars($config_sistema['h_fim']); ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none" style="color-scheme: dark;">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-bold transition-all duration-300"><i class="fas fa-save mr-2"></i>Salvar Endereço/Horários</button>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8 lg:col-span-2">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-edit mr-2 text-green-600 dark:text-green-400"></i>Tipos de Resíduos (Pág. Agendamento)</h3>
                    <ul id="listaResiduos" class="space-y-3 mb-4 managed-list">
                        <?php
                        $tipos_residuo->data_seek(0); // Rebobina o ponteiro do resultado
                        if ($tipos_residuo->num_rows > 0) {
                            while($row = $tipos_residuo->fetch_assoc()) {
                                echo '<li><span>' . htmlspecialchars($row['nome_residuo']) . '</span> <a href="remover_tipo.php?tipo=residuo&id=' . $row['id_tipo_residuo'] . '" class="remove-btn" onclick="return confirm(\'Tem certeza?\')"><i class="fas fa-trash-alt"></i></a></li>';
                            }
                        } else {
                            echo '<li class="italic text-gray-500">Nenhum tipo de resíduo cadastrado.</li>';
                        }
                        ?>
                    </ul>
                    <form class="flex gap-4" id="formResiduos" action="adicionar_tipo.php" method="POST">
                        <input type="hidden" name="tipo_lista" value="residuo">
                        <input type="text" id="inputResiduo" name="nome" placeholder="Novo tipo de resíduo" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none" required>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-bold transition-all duration-300"><i class="fas fa-plus"></i></button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($tipo_usuario == 'monitor'): ?>
            <div id="monitoriaSection" class="settings-section hidden grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-boxes mr-2 text-green-600 dark:text-green-400"></i>Tipos de Materiais</h3>
                    <ul id="listaMateriais" class="space-y-3 mb-4 managed-list max-h-60 overflow-y-auto pr-2">
                        <?php
                        $tipos_material->data_seek(0);
                        if ($tipos_material->num_rows > 0) {
                            while($row = $tipos_material->fetch_assoc()) {
                                echo '<li><span>' . htmlspecialchars($row['nome_material']) . '</span> <a href="remover_tipo.php?tipo=material&id=' . $row['id_tipo_material'] . '" class="remove-btn" onclick="return confirm(\'Tem certeza?\')"><i class="fas fa-trash-alt"></i></a></li>';
                            }
                        } else {
                             echo '<li class="italic text-gray-500">Nenhum tipo de material cadastrado.</li>';
                        }
                        ?>
                    </ul>
                    <form class="flex gap-4" id="formMateriais" action="adicionar_tipo.php" method="POST">
                        <input type="hidden" name="tipo_lista" value="material">
                        <input type="text" id="inputMaterial" name="nome" placeholder="Novo material" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none" required>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-bold transition-all duration-300"><i class="fas fa-plus"></i></button>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-exclamation-triangle mr-2 text-green-600 dark:text-green-400"></i>Possíveis Problemas</h3>
                    <ul id="listaProblemas" class="space-y-3 mb-4 managed-list max-h-60 overflow-y-auto pr-2">
                         <?php
                        $tipos_problema->data_seek(0);
                        if ($tipos_problema->num_rows > 0) {
                            while($row = $tipos_problema->fetch_assoc()) {
                                echo '<li><span>' . htmlspecialchars($row['nome_problema']) . '</span> <a href="remover_tipo.php?tipo=problema&id=' . $row['id_tipo_problema'] . '" class="remove-btn" onclick="return confirm(\'Tem certeza?\')"><i class="fas fa-trash-alt"></i></a></li>';
                            }
                        } else {
                             echo '<li class="italic text-gray-500">Nenhum problema cadastrado.</li>';
                        }
                        ?>
                    </ul>
                    <form class="flex gap-4" id="formProblemas" action="adicionar_tipo.php" method="POST">
                        <input type="hidden" name="tipo_lista" value="problema">
                        <input type="text" id="inputProblema" name="nome" placeholder="Novo problema" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none" required>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-bold transition-all duration-300"><i class="fas fa-plus"></i></button>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-map-signs mr-2 text-green-600 dark:text-green-400"></i>Destinos Finais</h3>
                    <ul id="listaDestinos" class="space-y-3 mb-4 managed-list max-h-60 overflow-y-auto pr-2">
                        <?php
                        $tipos_destino->data_seek(0);
                        if ($tipos_destino->num_rows > 0) {
                            while($row = $tipos_destino->fetch_assoc()) {
                                echo '<li><span>' . htmlspecialchars($row['nome_destino']) . '</span> <a href="remover_tipo.php?tipo=destino&id=' . $row['id_tipo_destino'] . '" class="remove-btn" onclick="return confirm(\'Tem certeza?\')"><i class="fas fa-trash-alt"></i></a></li>';
                            }
                        } else {
                             echo '<li class="italic text-gray-500">Nenhum destino cadastrado.</li>';
                        }
                        ?>
                    </ul>
                    <form class="flex gap-4" id="formDestinos" action="adicionar_tipo.php" method="POST">
                        <input type="hidden" name="tipo_lista" value="destino">
                        <input type="text" id="inputDestino" name="nome" placeholder="Novo destino" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none" required>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-bold transition-all duration-300"><i class="fas fa-plus"></i></button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div id="notificacoesSection" class="settings-section hidden max-w-2xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-4"><i class="fas fa-bell mr-2 text-green-600 dark:text-green-400"></i>Gerenciar Notificações</h3>
                    
                    <form action="atualizar_notificacoes.php" method="POST">
                        <div class="space-y-4">
                            <div id="notificacoesFuncionario" class="<?php echo ($tipo_usuario == 'funcionario') ? 'space-y-3' : 'hidden'; ?>">
                                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <label for="notif-agendamento" class="font-medium text-gray-700 dark:text-gray-200 cursor-pointer">Novo agendamento feito</label>
                                    <input type="checkbox" id="notif-agendamento" name="notif_agendamento" value="1" class="h-5 w-5 text-green-600 rounded border-gray-300 dark:border-gray-500 focus:ring-green-500 dark:bg-gray-600 dark:focus:ring-offset-gray-800" <?php echo ($user_data['notif_agendamento'] == 1) ? 'checked' : ''; ?>>
                                </div>
                                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <label for="notif-feedback" class="font-medium text-gray-700 dark:text-gray-200 cursor-pointer">Novo feedback recebido</label>
                                    <input type="checkbox" id="notif-feedback" name="notif_feedback" value="1" class="h-5 w-5 text-green-600 rounded border-gray-300 dark:border-gray-500 focus:ring-green-500 dark:bg-gray-600 dark:focus:ring-offset-gray-800" <?php echo ($user_data['notif_feedback'] == 1) ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <div id="notificacoesMonitor" class="<?php echo ($tipo_usuario == 'monitor') ? 'space-y-3' : 'hidden'; ?>">
                                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <label for="notif-material" class="font-medium text-gray-700 dark:text-gray-200 cursor-pointer">Novo material cadastrado</label>
                                    <input type="checkbox" id="notif-material" name="notif_material" value="1" class="h-5 w-5 text-green-600 rounded border-gray-300 dark:border-gray-500 focus:ring-green-500 dark:bg-gray-600 dark:focus:ring-offset-gray-800" <?php echo ($user_data['notif_material'] == 1) ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-bold transition-all duration-300 mt-6"><i class="fas fa-save mr-2"></i>Salvar Preferências</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <script>
        // === CONTROLE DE ABAS ===
        function showSection(sectionId) {
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.add('hidden');
            });
            document.querySelectorAll('.tab-button').forEach(tab => {
                tab.classList.remove('filter-active');
                tab.classList.add('filter-inactive', 'text-gray-600', 'bg-gray-100', 'dark:bg-gray-700', 'dark:text-gray-300');
            });
            document.getElementById(sectionId + 'Section').classList.remove('hidden');
            const activeTab = document.getElementById(sectionId + 'Tab');
            activeTab.classList.add('filter-active');
            activeTab.classList.remove('filter-inactive', 'text-gray-600', 'bg-gray-100', 'dark:bg-gray-700', 'dark:text-gray-300');
        }

        // === LÓGICA DE GERENCIAMENTO DE LISTAS (AJUSTADA) ===
        // Removemos o preventDefault() para deixar o formulário PHP funcionar
        function setupListManagement(formId, inputId, listId) {
            const form = document.getElementById(formId);
            const input = document.getElementById(inputId);
            const list = document.getElementById(listId);
            if (!form || !input || !list) {
                console.warn(`Aviso: Elementos de lista não encontrados para: ${formId}`);
                return;
            }
            // form.addEventListener('submit', function(e) {
            //     e.preventDefault(); // Esta linha IMPEDE o PHP de funcionar
            //     ...
            // });
        }

        // --- Funções do Dropdown de Perfil ---
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            if (dropdown) dropdown.classList.toggle('hidden');
        }
        
        // --- Logout (AJUSTADO) ---
        // A melhor prática é ter um arquivo 'logout.php'
        function fazerLogout(event) {
            event.preventDefault();
            // Limpa dados locais
            localStorage.clear();
            sessionStorage.clear();
            // Redireciona para um script de logout que destrói a sessão
            window.location.href = 'logout.php'; 
        }


        // === INICIALIZAÇÃO DA PÁGINA ===
        document.addEventListener('DOMContentLoaded', () => {

            // ============================================
            // CÓDIGO DO BOTÃO DE TEMA (AJUSTADO)
            // ============================================
            const themeToggleButton = document.getElementById('theme-toggle-button');
            const themeToggleIcon = document.getElementById('theme-toggle-icon');
            const themeSwitch = document.getElementById('themeToggle'); // O switch na página
            const themeForm = document.getElementById('themeForm');

            const updateButtonIcon = () => {
                if (document.documentElement.classList.contains('dark')) {
                    if (themeToggleIcon) themeToggleIcon.className = 'fas fa-moon';
                    localStorage.setItem('theme', 'dark');
                } else {
                    if (themeToggleIcon) themeToggleIcon.className = 'fas fa-sun';
                    localStorage.setItem('theme', 'light');
                }
            };
            
            // Sincroniza o ícone e o switch ao carregar
            if (themeSwitch && themeSwitch.checked) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            updateButtonIcon();

            // Botão do Header
            if (themeToggleButton) {
                themeToggleButton.addEventListener('click', () => {
                    document.documentElement.classList.toggle('dark');
                    updateButtonIcon();
                    if(themeSwitch) { themeSwitch.checked = document.documentElement.classList.contains('dark'); }
                    // Salva a preferência no localStorage
                    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                });
            }
            
            // Switch da Página (AJUSTADO)
            if (themeSwitch) {
                themeSwitch.addEventListener('change', () => {
                    document.documentElement.classList.toggle('dark');
                    updateButtonIcon();
                    // Auto-submete o formulário de tema
                    // themeForm.submit(); // Você pode habilitar isso se quiser salvar no banco a cada clique
                });
            }
            // ============================================
            // FIM DO CÓDIGO DO BOTÃO DE TEMA
            // ============================================

            // 1. Configurar a aba inicial
            showSection('perfil');

            // 2. AJUSTE: Verifica se há um hash na URL para abrir a aba correta
            const hash = window.location.hash; // Pega o # da URL
            if (hash === '#sistema') {
                showSection('sistema');
            } else if (hash === '#monitoria') {
                showSection('monitoria');
            }

            // 3. Ativar gerenciamento de listas
            setupListManagement('formResiduos', 'inputResiduo', 'listaResiduos');
            setupListManagement('formMateriais', 'inputMaterial', 'listaMateriais');
            setupListManagement('formProblemas', 'inputProblema', 'listaProblemas');
            setupListManagement('formDestinos', 'inputDestino', 'listaDestinos');
            
            // 4. Lógica de Preview da Imagem
            const profileImageInput = document.getElementById('profileImageInput');
            const profileImagePreview = document.getElementById('profileImagePreview');

            if (profileImageInput && profileImagePreview) {
                profileImageInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            profileImagePreview.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }

            // --- Script do Menu Mobile ---
            const menuBtn = document.getElementById('menu-btn');
            const closeMenuBtn = document.getElementById('close-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuOverlay = document.getElementById('menu-overlay');

            window.closeMobileMenu = () => {
                 if (!mobileMenu || !menuOverlay) return;
                mobileMenu.classList.remove('active');
                mobileMenu.addEventListener('transitionend', () => {
                    if (!mobileMenu.classList.contains('active')) {
                        mobileMenu.classList.add('hidden');
                    }
                }, { once: true });
                menuOverlay.classList.add('hidden');
                document.body.style.overflow = '';
                const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;
                if(menuIcon) {
                  menuIcon.classList.remove('fa-times');
                  menuIcon.classList.add('fa-bars');
                }
            };

            if (menuBtn && mobileMenu && closeMenuBtn && menuOverlay) {
                const menuIcon = menuBtn.querySelector('i');
                const openMobileMenu = () => {
                    mobileMenu.classList.remove('hidden');
                    setTimeout(() => mobileMenu.classList.add('active'), 10);
                    menuOverlay.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    if(menuIcon) {
                      menuIcon.classList.remove('fa-bars');
                      menuIcon.classList.add('fa-times');
                    }
                };
                menuBtn.addEventListener('click', () => {
                   if (mobileMenu.classList.contains('hidden') || !mobileMenu.classList.contains('active')) {
                       openMobileMenu();
                   } else {
                       window.closeMobileMenu();
                   }
                });
                closeMenuBtn.addEventListener('click', window.closeMobileMenu);
                menuOverlay.addEventListener('click', window.closeMobileMenu);
            } else {
                console.error("Erro: Elementos do menu mobile não encontrados.");
            }

             // Fechar dropdown ao clicar fora
            document.addEventListener('click', (e) => {
                const container = document.getElementById('profileDropdownContainer');
                const dropdown = document.getElementById('profileDropdown');
                if (container && dropdown && !container.contains(e.target) && !dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                }
            });

        }); // Fim do DOMContentLoaded principal

        // Tamanho da fonte
        let nivelFonte = 1;
        const tamanhos = [87.5, 100, 112.5, 125];

        function alterarFonte(nivel) {
          if (nivel === 0) nivelFonte = 1;
          else {
            nivelFonte += nivel;
            if (nivelFonte < 0) nivelFonte = 0;
            if (nivelFonte > 3) nivelFonte = 3;
          }
          document.documentElement.style.fontSize = tamanhos[nivelFonte] + '%';
        }

        document.getElementById('toggleFontButtons').addEventListener('click', () => {
          document.getElementById('fontButtons').classList.toggle('hidden');
        });
    </script>
    </body>
</html>