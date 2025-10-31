<?php
// 1. INICIA A SESSÃO (OBRIGATÓRIO)
session_start();

// 2. INCLUI A CONEXÃO
require_once 'conexao.php'; // $conn (a conexão) está disponível agora

// 3. BLOCO DE SEGURANÇA
// Verifica se o usuário está logado E se ele é um 'monitor'
if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'monitor') {
    session_destroy();
    header("Location: entrar.html");
    exit();
}

// Se o script chegou até aqui, o usuário é um monitor logado.
$nome_usuario = $_SESSION['nome_usuario'];
$id_monitor = $_SESSION['id_usuario']; // Guardamos o ID para o cadastro de material

// ==========================================================
// 4. BUSCA DE DADOS (QUERIES)
// ==========================================================

// --- CARD: RECICLAGEM DO MÊS ---
$result_total_mes = $conn->query("
    SELECT COUNT(id_material_cadastrado) AS total 
    FROM tbl_material_cadastrado 
    WHERE MONTH(data_cadastro) = MONTH(CURRENT_DATE()) AND YEAR(data_cadastro) = YEAR(CURRENT_DATE())
");
$total_mes = $result_total_mes->fetch_assoc()['total'];

$result_coleta_mes = $conn->query("
    SELECT COUNT(id_material_cadastrado) AS total 
    FROM tbl_material_cadastrado 
    WHERE MONTH(data_cadastro) = MONTH(CURRENT_DATE()) AND YEAR(data_cadastro) = YEAR(CURRENT_DATE())
      AND id_tipo_destino = 1 
");
$coleta_mes = $result_coleta_mes->fetch_assoc()['total'];

if ($total_mes > 0) {
    $percentual_reciclagem = round(($coleta_mes / $total_mes) * 100);
} else {
    $percentual_reciclagem = 0;
}

// --- GRÁFICOS DE DISTRIBUIÇÃO ---
$query_green_dist = "
    SELECT tm.nome_material, COUNT(mc.id_material_cadastrado) AS total 
    FROM tbl_material_cadastrado mc 
    JOIN tbl_tipomaterial tm ON mc.id_tipo_material = tm.id_tipo_material 
    WHERE mc.categoria = 'green' 
    GROUP BY tm.nome_material
    ORDER BY total DESC";
$result_green_dist = $conn->query($query_green_dist);

$query_brown_dist = "
    SELECT tm.nome_material, COUNT(mc.id_material_cadastrado) AS total 
    FROM tbl_material_cadastrado mc 
    JOIN tbl_tipomaterial tm ON mc.id_tipo_material = tm.id_tipo_material 
    WHERE mc.categoria = 'brown' 
    GROUP BY tm.nome_material
    ORDER BY total DESC";
$result_brown_dist = $conn->query($query_brown_dist);

// --- TABELAS DE PROCESSAMENTO RECENTE ---
$query_green_recent = "
    SELECT DATE_FORMAT(mc.data_cadastro, '%d/%m/%y') AS data_format, tm.nome_material, mc.status_processamento 
    FROM tbl_material_cadastrado mc 
    JOIN tbl_tipomaterial tm ON mc.id_tipo_material = tm.id_tipo_material 
    WHERE mc.categoria = 'green' 
    ORDER BY mc.data_cadastro DESC LIMIT 5";
$result_green_recent = $conn->query($query_green_recent);

$query_brown_recent = "
    SELECT DATE_FORMAT(mc.data_cadastro, '%d/%m/%y') AS data_format, tm.nome_material, mc.status_processamento 
    FROM tbl_material_cadastrado mc 
    JOIN tbl_tipomaterial tm ON mc.id_tipo_material = tm.id_tipo_material 
    WHERE mc.categoria = 'brown' 
    ORDER BY mc.data_cadastro DESC LIMIT 5";
$result_brown_recent = $conn->query($query_brown_recent);

// --- RELATÓRIOS ANTERIORES ---
$query_relatorios = "
    SELECT DATE_FORMAT(data_cadastro, '%M/%Y') AS mes_ano_format, 
           CASE WHEN categoria = 'green' THEN 'Informática' ELSE 'Áudio/Vídeo' END AS nome_categoria, 
           COUNT(id_material_cadastrado) AS total 
    FROM tbl_material_cadastrado 
    GROUP BY mes_ano_format, nome_categoria 
    ORDER BY data_cadastro DESC LIMIT 4";
$result_relatorios = $conn->query($query_relatorios);

// --- PENDÊNCIAS DE DESTINO ---
$query_pend_coleta = "
    SELECT tm.nome_material, DATE_FORMAT(mc.data_cadastro, '%d/%m') AS data_format
    FROM tbl_material_cadastrado mc
    JOIN tbl_tipomaterial tm ON mc.id_tipo_material = tm.id_tipo_material
    WHERE mc.id_tipo_destino = 1 AND mc.status_processamento = 'Em Espera'
    ORDER BY mc.data_cadastro ASC";
$result_pend_coleta = $conn->query($query_pend_coleta);

$query_pend_aula = "
    SELECT tm.nome_material, DATE_FORMAT(mc.data_cadastro, '%d/%m') AS data_format
    FROM tbl_material_cadastrado mc
    JOIN tbl_tipomaterial tm ON mc.id_tipo_material = tm.id_tipo_material
    WHERE mc.id_tipo_destino = 2 AND mc.status_processamento = 'Em Espera'
    ORDER BY mc.data_cadastro ASC";
$result_pend_aula = $conn->query($query_pend_aula);


// --- DADOS PARA OS DROPDOWNS DO MODAL ---
$result_tipos_material = $conn->query("SELECT id_tipo_material, nome_material FROM tbl_tipomaterial ORDER BY nome_material ASC");
$result_tipos_problema = $conn->query("SELECT id_tipo_problema, nome_problema FROM tbl_tipoproblema ORDER BY nome_problema ASC");
$result_tipos_destino = $conn->query("SELECT id_tipo_destino, nome_destino FROM tbl_tipodestino ORDER BY nome_destino ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monitor de Materiais - RTEC</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    darkMode: 'class', 
    theme: {
      extend: {}
    }
  }
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="icon" href="img/logo.png" type="image/x-icon">

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
    body { font-family: 'Inter', sans-serif; box-sizing: border-box; }
    .nav-link:hover { color: #10b981; transform: translateY(-1px); }
    .card-hover:hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1); }
    .dark .card-hover:hover { box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3); }
    .filter-active-green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white !important; box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4); }
    .filter-active-brown { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white !important; box-shadow: 0 6px 15px rgba(245, 158, 11, 0.4); }
    .filter-inactive:hover { background-color: #f3f4f6; }
    .dark .filter-inactive:hover { background-color: #374151; }
    .progress-bar { transition: width 1.2s ease-in-out; }
    .table-row { transition: all 0.2s ease-in-out; }
    .table-row:hover { background-color: #f0fdf4; }
    .dark .table-row:hover { background-color: #064e3b; }
    #mobile-menu { position: fixed; top: 0; right: 0; width: 80%; max-width: 300px; height: 100vh; background-color: rgba(255, 255, 255, 0.98); backdrop-filter: blur(8px); box-shadow: -8px 0 20px rgba(0,0,0,0.2); transform: translateX(100%); transition: transform 0.4s ease-out; z-index: 100; display: flex; flex-direction: column; }
    .dark #mobile-menu { background-color: rgba(31, 41, 55, 0.98); border-left: 1px solid #374151; }
    #mobile-menu.active { transform: translateX(0); }
    #mobile-menu-header { padding: 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
    .dark #mobile-menu-header { border-bottom-color: #374151; }
    #mobile-menu .nav-list-mobile { padding: 1.5rem 1rem; flex-grow: 1; }
    #mobile-menu .nav-list-mobile li a { display: block; padding: 0.75rem 1rem; margin-bottom: 0.5rem; font-size: 1.1rem; font-weight: 500; color: #374151; border-radius: 0.5rem; transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease; position: relative; }
    .dark #mobile-menu .nav-list-mobile li a { color: #d1d5db; }
    #mobile-menu .nav-list-mobile li a:hover { background-color: #ecfdf5; color: #047857; transform: translateX(5px); }
    .dark #mobile-menu .nav-list-mobile li a:hover { background-color: #374151; color: #34d399; }
    #mobile-menu .nav-list-mobile li .active-link-mobile { background-color: #d1fae5; color: #047857; font-weight: 700; border-left: 4px solid #10b981; padding-left: 1rem; }
    .dark #mobile-menu .nav-list-mobile li .active-link-mobile { background-color: #065f46; color: #6ee7b7; border-left-color: #10b981; }
    .mobile-menu-footer { padding: 1rem; margin-top: auto; border-top: 1px solid #e5e7eb; background-color: #f9fafb; }
    .dark .mobile-menu-footer { border-top-color: #374151; background-color: #1f2937; }
    .mobile-menu-footer .btn-mobile-login { display: block; width: 100%; text-align: center; padding: 0.75rem 1rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.2s ease; }
    .btn-mobile-logout { background-color: #fee2e2; color: #b91c1c; margin-top: 0.5rem; }
    .btn-mobile-logout:hover { background-color: #fecaca; color: #991b1b; }
    .dark .btn-mobile-logout { background-color: #450a0a; color: #fca5a5; }
    .dark .btn-mobile-logout:hover { background-color: #7f1d1d; color: #fecaca; }
    .btn-mobile-primary { background-color: #10b981; color: white; }
    .btn-mobile-primary:hover { background-color: #059669; }
    .btn-mobile-secondary { background-color: #f3f4f6; color: #374151; }
    .dark .btn-mobile-secondary { background-color: #374151; color: #d1d5db; }
    .btn-mobile-secondary:hover { background-color: #e5e7eb; }
    .dark .btn-mobile-secondary:hover { background-color: #4b5563; }
    label:has(input:checked) { border-color: #d1d5db; }
    .dark label:has(input:checked) { border-color: #4b5563; }
    label:has(input[type="radio"]:checked) { border-color: #10b981; background-color: #ecfdf5; }
    .dark label:has(input[type="radio"]:checked) { border-color: #34d399; background-color: #064e3b; }
    label:has(input[type="radio"][value="brown"]:checked) { border-color: #f59e0b; background-color: #fffbeb; }
    .dark label:has(input[type="radio"][value="brown"]:checked) { border-color: #facc15; background-color: #78350f; }
    label:has(input[type="checkbox"]:checked) { border-color: #3b82f6; background-color: #eff6ff; }
    .dark label:has(input[type="checkbox"]:checked) { border-color: #60a5fa; background-color: #1e3a8a; }
    html { scroll-behavior: smooth; }
    .font-controls { position: fixed; top: 390px; right: 10px; z-index: 999; background: none; padding: 0; }
    #toggleFontButtons { width: 40px; height: 40px; line-height: 40px; padding: 0; font-size: 18px !important; text-align: center; }
    .font-controls button { padding: 6px 10px; font-size: 14px; cursor: pointer; border: none; border-radius: 4px; background-color: #007bff; color: white; }
    #fontButtons button { background-color: #3f90ff; }
    #fontButtons.hidden { display: none; }
    #fontButtons { position: absolute; bottom: 100%; right: 0; display: flex; flex-direction: column-reverse; gap: 3px; margin-bottom: 5px; margin-top: 0; }
    @media (max-width: 400px) {
    .font-controls { top: 350px; right: 5px; padding: 3; }
    .font-controls button { padding: 4px 8px; font-size: 12px; }
    #fontButtons { gap: 2px; margin-bottom: 4px; }
    }
</style>

<script>
    // Este script aplica o tema (claro ou escuro) antes mesmo da página carregar
    if (localStorage.getItem('theme') === 'dark') {
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
            <img src="img/usuario.png" alt="Usuário" id="profileButton" class="h-10 w-10 rounded-full object-cover cursor-pointer border-2 border-green-600 hover:border-green-800 transition" onclick="toggleProfileDropdown()">
            <div id="profileDropdown" class="absolute right-0 mt-2 w-48 rounded-lg shadow-xl bg-white dark:bg-gray-700 ring-1 ring-black dark:ring-gray-600 ring-opacity-5 z-50 hidden">
                <div class="py-1">
                    <p class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 font-semibold border-b dark:border-gray-600">
                        Olá, <?php echo htmlspecialchars($nome_usuario); ?>
                    </p>
                    <a href="monitoria.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600"><i class="fas fa-desktop mr-2"></i>Monitor</a>
                    <a href="#relatorios" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                        <i class="fas fa-file-alt mr-2"></i>Relatórios
                    </a>
                    <a href="configuracoes.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600"><i class="fas fa-cog mr-2"></i>Configurações</a>
                    <a href="#" onclick="fazerLogout(event)" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900 hover:text-red-700 dark:hover:text-red-300 border-t dark:border-gray-600 mt-1"><i class="fas fa-sign-out-alt mr-2"></i>Sair</a>
                </div>
            </div>
        </div>
    </div>
</nav>
<nav class="py-4">
    <div class="container mx-auto px-4 flex items-center justify-between">
        <a href="sobre.html" class="block"><img src="img/logo.png" alt="Logo" class="h-12 w-auto"></a>
        <ul class="hidden md:flex space-x-8">
            <li><a href="monitoria.php" class="nav-link text-green-600 dark:text-green-400 font-medium border-b-2 border-green-600">MONITOR</a></li>
            <li><a href="#relatorios" class="nav-link text-gray-700 dark:text-gray-300 font-medium hover:text-green-600 dark:hover:text-green-400">RELATÓRIOS</a></li>
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
<ul class="nav-list-mobile">
     <li><a href="monitoria.php" class="active-link-mobile">MONITOR</a></li>
     <li><a href="#relatorios">RELATÓRIOS</a></li>
     <li><a href="configuracoes.php">CONFIGURAÇÕES</a></li>
     </ul>
<div class="mobile-menu-footer">
    <a href="#" onclick="fazerLogout(event); window.closeMobileMenu();" class="btn-mobile-login btn-mobile-logout"><i class="fas fa-sign-out-alt mr-2"></i>Sair</a>
</div>
</div>

<div id="menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-[99] hidden"></div>

    <main class="py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 dark:text-white mb-2">Monitor de Materiais</h1>
                <p class="text-lg sm:text-xl text-gray-600 dark:text-gray-300">Acompanhe a separação e processamento de materiais eletrônicos por categoria</p>
            </div>

            <div class="flex flex-col md:flex-row gap-6 md:gap-8 justify-center items-center md:items-stretch mb-10 px-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 sm:p-6 shadow-md w-full md:w-auto">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-800 dark:text-white mb-4 text-center">Selecionar Categoria</h3>
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                        <button onclick="showCategory('green')" id="greenTab" class="filter-inactive filter-active-green flex-1 px-4 py-2 rounded-lg font-semibold transition-all duration-300 flex items-center justify-center text-sm"><i class="fas fa-desktop mr-2"></i>Informática</button>
                        <button onclick="showCategory('brown')" id="brownTab" class="filter-inactive flex-1 px-4 py-2 rounded-lg font-semibold text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 transition-all duration-300 flex items-center justify-center text-sm"><i class="fas fa-tv mr-2"></i>Áudio/Vídeo</button>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 sm:p-6 shadow-md w-full md:w-auto flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0 sm:space-x-6">
                    <div class="flex items-center space-x-4">
                        <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full flex-shrink-0"><i class="fas fa-recycle text-green-600 dark:text-green-400 text-2xl"></i></div>
                        <div><p class="text-base font-medium text-gray-600 dark:text-gray-300">Coleta do Mês</p><p class="text-3xl font-extrabold text-green-600 dark:text-green-400" id="recyclingRate"><?php echo $percentual_reciclagem; ?>%</p></div>
                    </div>
                    <div class="flex flex-col space-y-3 w-full sm:w-auto">
                        <button onclick="openNewMaterialModal()" class="w-full sm:w-44 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-bold shadow-md hover:shadow-lg transition-all duration-300 flex items-center justify-center text-sm"><i class="fas fa-plus mr-2"></i>Cadastrar Material</button>
                        <button id="pdfButtonMonitor" onclick="generatePDF()" class="w-full sm:w-44 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg font-bold shadow-md hover:shadow-lg transition-all duration-300 flex items-center justify-center text-sm"><i class="fas fa-file-pdf mr-2"></i><span id="pdfButtonTextMonitor">Gerar PDF</span></button>
                    </div>
                </div>
            </div>

            <div id="reportContainer" class="max-w-7xl mx-auto">
                <div id="greenReport" class="report-section">
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-white mb-6 text-center md:text-left">Equipamentos de Informática e Telefonia</h2>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                        
                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 sm:p-8 shadow-lg card-hover">
                             <h3 class="text-xl sm:text-2xl font-bold text-gray-700 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-3">Distribuição por Tipo</h3>
                            <div class="space-y-6">
                                <?php
                                // Resetar o ponteiro do resultado (boa prática se for usar de novo, mas aqui é só para garantir)
                                $result_green_dist->data_seek(0); 
                                if ($result_green_dist->num_rows > 0) {
                                    while($row = $result_green_dist->fetch_assoc()) {
                                        // Você pode adicionar uma lógica para mudar o ícone e a cor se quiser
                                        $icon_class = 'fa-desktop';
                                ?>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 shadow-sm">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center">
                                            <i class="fas <?php echo $icon_class; ?> text-green-600 dark:text-green-400 mr-3"></i>
                                            <span class="text-gray-800 dark:text-gray-100 font-semibold"><?php echo htmlspecialchars($row['nome_material']); ?></span>
                                        </div>
                                        <span class="text-gray-500 dark:text-gray-400 text-sm font-normal"><?php echo $row['total']; ?> un.</span>
                                    </div>
                                    </div>
                                <?php
                                    }
                                } else {
                                    echo '<p class="text-gray-500 dark:text-gray-400 text-center">Nenhum material de informática cadastrado.</p>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden card-hover">
                            <div class="bg-green-600 p-6">
                                <h3 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                                    <i class="fas fa-clock mr-3"></i>Processamento Recente
                                </h3>
                            </div>
                            <div class="block md:hidden p-4 space-y-4 bg-gray-50 dark:bg-gray-700 rounded-b-2xl max-h-96 overflow-y-auto ...">
                                </div>
                            <div class="hidden md:block max-h-96 overflow-y-auto scroll-smooth ...">
                                <table class="w-full min-w-[500px]">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Data</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Material</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php
                                        $result_green_recent->data_seek(0);
                                        if ($result_green_recent->num_rows > 0) {
                                            while($row = $result_green_recent->fetch_assoc()) {
                                                // Define a cor do status
                                                $status_class = '';
                                                if ($row['status_processamento'] == 'Processado' || $row['status_processamento'] == 'Finalizado') {
                                                    $status_class = 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200';
                                                } elseif ($row['status_processamento'] == 'Em Processo' || $row['status_processamento'] == 'Preparando') {
                                                    $status_class = 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200';
                                                } else {
                                                    $status_class = 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200';
                                                }
                                        ?>
                                        <tr class="table-row">
                                            <td class="px-6 py-5 text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($row['data_format']); ?></td>
                                            <td class="px-6 py-5 text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($row['nome_material']); ?></td>
                                            <td class="px-6 py-5">
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                                    <?php echo htmlspecialchars($row['status_processamento']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php
                                            }
                                        } else {
                                            echo '<tr class="table-row"><td colspan="3" class="px-6 py-5 text-center text-gray-500 dark:text-gray-400">Nenhum processamento recente.</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="brownReport" class="report-section hidden">
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-white mb-6 text-center md:text-left">
                        Equipamentos de Áudio e Vídeo
                    </h2>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 sm:p-8 shadow-lg card-hover">
                            <h3 class="text-xl sm:text-2xl font-bold text-gray-700 dark:text-gray-100 mb-6 border-b dark:border-gray-700 pb-3">
                                Distribuição por Tipo
                            </h3>
                            <div class="space-y-6">
                                <?php
                                $result_brown_dist->data_seek(0);
                                if ($result_brown_dist->num_rows > 0) {
                                    while($row = $result_brown_dist->fetch_assoc()) {
                                        $icon_class = 'fa-tv'; // Ícone padrão
                                ?>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 shadow-sm">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center">
                                            <i class="fas <?php echo $icon_class; ?> text-yellow-600 dark:text-yellow-400 mr-3"></i>
                                            <span class="text-gray-800 dark:text-gray-100 font-semibold"><?php echo htmlspecialchars($row['nome_material']); ?></span>
                                        </div>
                                         <span class="text-gray-500 dark:text-gray-400 text-sm font-normal"><?php echo $row['total']; ?> un.</span>
                                    </div>
                                </div>
                                <?php
                                    }
                                } else {
                                    echo '<p class="text-gray-500 dark:text-gray-400 text-center">Nenhum material de áudio/vídeo cadastrado.</p>';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden card-hover">
                            <div class="bg-yellow-600 p-6">
                                <h3 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-clock mr-3"></i>Processamento Recente
                                </h3>
                            </div>
                            <div class="block md:hidden p-4 space-y-4 bg-gray-50 dark:bg-gray-700 ...">
                                </div>
                            <div class="hidden md:block max-h-96 overflow-y-auto scroll-smooth ...">
                                <table class="w-full min-w-[500px]">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Data</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Material</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php
                                    $result_brown_recent->data_seek(0);
                                    if ($result_brown_recent->num_rows > 0) {
                                        while($row = $result_brown_recent->fetch_assoc()) {
                                            $status_class = '';
                                            if ($row['status_processamento'] == 'Processado' || $row['status_processamento'] == 'Finalizado') {
                                                $status_class = 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200';
                                            } elseif ($row['status_processamento'] == 'Em Processo' || $row['status_processamento'] == 'Preparando') {
                                                $status_class = 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200';
                                            } else {
                                                $status_class = 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200';
                                            }
                                    ?>
                                    <tr class="table-row">
                                        <td class="px-6 py-5 text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($row['data_format']); ?></td>
                                        <td class="px-6 py-5 text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($row['nome_material']); ?></td>
                                        <td class="px-6 py-5">
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($row['status_processamento']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr class="table-row"><td colspan="3" class="px-6 py-5 text-center text-gray-500 dark:text-gray-400">Nenhum processamento recente.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<section id="relatorios" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-16">
    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-white mb-6">Relatórios Anteriores</h2>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-4">
        <?php
        $result_relatorios->data_seek(0);
        if ($result_relatorios->num_rows > 0) {
            while($row = $result_relatorios->fetch_assoc()) {
        ?>
        <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
            <div>
                <p class="text-lg font-semibold text-gray-700 dark:text-gray-100">Relatório de <?php echo htmlspecialchars($row['nome_categoria']); ?> - <?php echo htmlspecialchars($row['mes_ano_format']); ?></p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total de <?php echo $row['total']; ?> itens</p>
            </div>
        </div>
        <?php
            }
        } else {
            echo '<p class="text-gray-500 dark:text-gray-400 text-center">Nenhum relatório anterior gerado.</p>';
        }
        ?>
    </div>
</section>

<section id="pendingArea" class="mt-16 mb-20 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="w-full">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center justify-center sm:justify-start">
        <i class="fas fa-boxes mr-3 text-blue-600 dark:text-blue-400"></i>
        Pendências de Destino
    </h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div id="pendingColeta" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-md border-t-4 border-red-500">
            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-100 mb-3 flex items-center">
                <i class="fas fa-truck-loading mr-2 text-red-500 dark:text-red-400"></i>
                Aguardando Coleta/Reciclagem
            </h3>
            <ul id="listColeta" class="space-y-2 max-h-48 overflow-y-auto pr-2 text-sm">
                <?php
                $result_pend_coleta->data_seek(0);
                if ($result_pend_coleta->num_rows > 0) {
                    while($row = $result_pend_coleta->fetch_assoc()) {
                ?>
                <li class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-colors border-l-4 border-yellow-400 dark:border-yellow-500">
                    <span class="font-medium text-gray-800 dark:text-gray-100"><?php echo htmlspecialchars($row['nome_material']); ?></span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Cad. em <?php echo $row['data_format']; ?></span>
                </li>
                <?php
                    }
                } else {
                    echo '<li class="text-gray-500 dark:text-gray-400 italic p-1 border-b border-gray-100 dark:border-gray-700">Nenhum material pendente para coleta.</li>';
                }
                ?>
            </ul>
        </div>

        <div id="pendingAula" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-md border-t-4 border-blue-500">
            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-100 mb-3 flex items-center">
                <i class="fas fa-school mr-2 text-blue-500 dark:text-blue-400"></i>
                Aguardando Aula/Reaproveitamento
            </h3>
            <ul id="listAula" class="space-y-2 max-h-48 overflow-y-auto pr-2 text-sm">
                <?php
                $result_pend_aula->data_seek(0);
                if ($result_pend_aula->num_rows > 0) {
                    while($row = $result_pend_aula->fetch_assoc()) {
                ?>
                <li class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-colors border-l-4 border-yellow-400 dark:border-yellow-500">
                    <span class="font-medium text-gray-800 dark:text-gray-100"><?php echo htmlspecialchars($row['nome_material']); ?></span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Cad. em <?php echo $row['data_format']; ?></span>
                </li>
                <?php
                    }
                } else {
                    echo '<li class="text-gray-500 dark:text-gray-400 italic p-1 border-b border-gray-100 dark:border-gray-700">Nenhum material pendente para aula.</li>';
                }
                ?>
            </ul>
        </div>
    </div>
</div>
</section>

    </main>

    <div id="newMaterialModal" class="fixed inset-0 hidden z-[101] flex items-center justify-center p-4" style="background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(8px);">
    <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl m-4">
        <div class="bg-blue-600 p-6 rounded-t-2xl"><div class="flex justify-between items-center"><h2 class="text-xl sm:text-2xl font-bold text-white flex items-center"><i class="fas fa-plus-circle mr-3"></i>Cadastrar Novo Material</h2><button onclick="closeNewMaterialModal()" class="text-white hover:text-gray-200 transition-colors"><i class="fas fa-times text-2xl"></i></button></div></div>
        
        <form id="newMaterialForm" class="p-6 sm:p-8 space-y-6 sm:space-y-8">
            <div>
                <div>
                    <label class="block text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2">Tipo de Material</label>
                    <select id="materialType" name="id_tipo_material" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-xl focus:border-blue-500 focus:outline-none transition-colors text-lg" required>
                        <option value="">Selecione um tipo</option>
                        <?php
                        $result_tipos_material->data_seek(0);
                        if ($result_tipos_material->num_rows > 0) {
                            while($row = $result_tipos_material->fetch_assoc()) {
                                echo '<option value="' . $row['id_tipo_material'] . '">' . htmlspecialchars($row['nome_material']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-base sm:text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2">Categoria</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="flex items-center p-4 border-2 border-gray-200 dark:border-gray-600 dark:text-gray-300 rounded-xl cursor-pointer hover:border-green-400 dark:hover:border-green-500 transition-colors has-[:checked]:border-green-500 has-[:checked]:bg-green-50 dark:has-[:checked]:bg-green-900 dark:has-[:checked]:border-green-400">
                        <input type="radio" name="category" value="green" class="mr-3 h-5 w-5 text-green-600 focus:ring-green-500 border-gray-300 dark:border-gray-500 dark:bg-gray-600 dark:focus:ring-offset-gray-800" required>
                        <i class="fas fa-laptop text-green-600 dark:text-green-400 mr-2"></i>
                        <span class="font-medium">Informática e Telefonia</span>
                    </label>
                    <label class="flex items-center p-4 border-2 border-gray-200 dark:border-gray-600 dark:text-gray-300 rounded-xl cursor-pointer hover:border-yellow-400 dark:hover:border-yellow-500 transition-colors has-[:checked]:border-yellow-500 has-[:checked]:bg-yellow-50 dark:has-[:checked]:bg-yellow-900 dark:has-[:checked]:border-yellow-400">
                        <input type="radio" name="category" value="brown" class="mr-3 h-5 w-5 text-yellow-600 focus:ring-yellow-500 border-gray-300 dark:border-gray-500 dark:bg-gray-600 dark:focus:ring-offset-gray-800" required>
                        <i class="fas fa-tv text-yellow-600 dark:text-yellow-400 mr-2"></i>
                        <span class="font-medium">Áudio e Vídeo</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-base sm:text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2">Destino Final</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php
                    $result_tipos_destino->data_seek(0);
                    if ($result_tipos_destino->num_rows > 0) {
                        while($row = $result_tipos_destino->fetch_assoc()) {
                            $icon = ($row['nome_destino'] == 'Para Coleta') ? 'fa-trash-alt text-red-600' : 'fa-chalkboard-teacher text-blue-600';
                            $color_class = ($row['nome_destino'] == 'Para Coleta') ? 'red' : 'blue';
                    ?>
                    <label class="flex items-center p-4 border-2 border-gray-200 dark:border-gray-600 dark:text-gray-300 rounded-xl cursor-pointer hover:border-<?php echo $color_class; ?>-400 dark:hover:border-<?php echo $color_class; ?>-500 transition-colors has-[:checked]:border-<?php echo $color_class; ?>-500 has-[:checked]:bg-<?php echo $color_class; ?>-50 dark:has-[:checked]:bg-<?php echo $color_class; ?>-900 dark:has-[:checked]:border-<?php echo $color_class; ?>-400">
                        <input type="radio" name="id_tipo_destino" value="<?php echo $row['id_tipo_destino']; ?>" class="mr-3 h-5 w-5 text-<?php echo $color_class; ?>-600 focus:ring-<?php echo $color_class; ?>-500 border-gray-300 dark:border-gray-500 dark:bg-gray-600 dark:focus:ring-offset-gray-800" required>
                        <i class="fas <?php echo $icon; ?> dark:text-<?php echo $color_class; ?>-400 mr-2"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($row['nome_destino']); ?></span>
                    </label>
                    <?php
                        }
                    }
                    ?>
                </div>
            </div>
            <div>
                <label class="block text-base sm:text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2">Possíveis Problemas <span class="text-gray-500 dark:text-gray-400 font-normal text-sm">(Opcional)</span></label>
                <div class="grid grid-cols-2 gap-3 sm:gap-4">
                    <?php
                    $result_tipos_problema->data_seek(0);
                    if ($result_tipos_problema->num_rows > 0) {
                        while($row = $result_tipos_problema->fetch_assoc()) {
                    ?>
                    <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 dark:text-gray-300 rounded-xl cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors has-[:checked]:bg-blue-100 dark:has-[:checked]:bg-blue-900 has-[:checked]:ring-2 has-[:checked]:ring-blue-300 dark:has-[:checked]:ring-blue-500">
                        <input type="checkbox" class="h-5 w-5 rounded border-gray-300 dark:border-gray-500 text-blue-600 focus:ring-blue-500 dark:bg-gray-600 dark:focus:ring-offset-gray-800 mr-3" name="problems[]" value="<?php echo $row['id_tipo_problema']; ?>">
                        <span><?php echo htmlspecialchars($row['nome_problema']); ?></span>
                    </label>
                    <?php
                        }
                    }
                    ?>
                </div>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-bold shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center text-lg mt-8"><i class="fas fa-check-circle mr-2"></i>Salvar Novo Material</button>
        </form>
    </div>
</div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        // --- FUNÇÕES DE RELATÓRIO E PDF ---
        function showCategory(category) {
            const greenReport = document.getElementById('greenReport'); const brownReport = document.getElementById('brownReport');
            const greenTab = document.getElementById('greenTab'); const brownTab = document.getElementById('brownTab');
            const recyclingRateEl = document.getElementById('recyclingRate');
            if(greenTab) {
                greenTab.classList.remove('filter-active-green');
                greenTab.classList.add('bg-gray-100', 'text-gray-600', 'filter-inactive', 'dark:bg-gray-700', 'dark:text-gray-300');
            }
            if(brownTab) {
                brownTab.classList.remove('filter-active-brown');
                brownTab.classList.add('bg-gray-100', 'text-gray-600', 'filter-inactive', 'dark:bg-gray-700', 'dark:text-gray-300');
             }

            if (category === 'green') {
                if(greenReport) greenReport.classList.remove('hidden'); if(brownReport) brownReport.classList.add('hidden');
                if(greenTab) {
                    greenTab.classList.add('filter-active-green');
                    greenTab.classList.remove('bg-gray-100', 'text-gray-600', 'filter-inactive', 'dark:bg-gray-700', 'dark:text-gray-300');
                 }
                // TROCA: Atualiza a taxa de reciclagem (baseada no PHP)
                if(recyclingRateEl) { 
                    recyclingRateEl.textContent = '<?php echo $percentual_reciclagem; ?>%'; // Usa a variável PHP
                    recyclingRateEl.classList.remove('text-yellow-600', 'dark:text-yellow-400'); 
                    recyclingRateEl.classList.add('text-green-600', 'dark:text-green-400'); 
                } 
            } else if (category === 'brown') {
                 if(greenReport) greenReport.classList.add('hidden'); if(brownReport) brownReport.classList.remove('hidden');
                 if(brownTab) {
                     brownTab.classList.add('filter-active-brown');
                     brownTab.classList.remove('bg-gray-100', 'text-gray-600', 'filter-inactive', 'dark:bg-gray-700', 'dark:text-gray-300');
                 }
                 // TROCA: (Poderia buscar outra taxa, mas vamos manter a principal por enquanto)
                 if(recyclingRateEl) { 
                     recyclingRateEl.textContent = '<?php echo $percentual_reciclagem; ?>%'; // Pode mudar se tiver outra lógica
                     recyclingRateEl.classList.remove('text-green-600', 'dark:text-green-400'); 
                     recyclingRateEl.classList.add('text-yellow-600', 'dark:text-yellow-400'); 
                }
            }
        }
        
         async function generatePDF() {
            // ... (Seu código JS de gerar PDF permanece o mesmo) ...
        }

        // --- Funções do Modal ---
        function openNewMaterialModal() { const m = document.getElementById('newMaterialModal'); if(m) m.classList.remove('hidden'); }
        function closeNewMaterialModal() { const m = document.getElementById('newMaterialModal'); if(m) m.classList.add('hidden'); }
        
        // --- Funções do Dropdown de Perfil ---
        function toggleProfileDropdown() { const d = document.getElementById('profileDropdown'); if (d) d.classList.toggle('hidden'); }
        function fazerLogout(event) { 
            event.preventDefault(); 
            // Limpa a sessão no PHP (embora a sessão seja destruída no próximo load de página protegida)
            <?php session_destroy(); ?> 
            // Limpa o localStorage para garantir
            localStorage.clear(); 
             sessionStorage.clear();
            alert('Sessão encerrada.'); 
            window.location.href = 'entrar.html'; 
        }

        // --- Script do Menu Mobile e Inicialização ---
        document.addEventListener('DOMContentLoaded', () => {
            // --- CÓDIGO DO BOTÃO DE TEMA ---
            const themeToggleButton = document.getElementById('theme-toggle-button');
            const themeToggleIcon = document.getElementById('theme-toggle-icon');
            const updateButtonIcon = () => {
                if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    if (themeToggleIcon) themeToggleIcon.className = 'fas fa-moon';
                } else {
                    if (themeToggleIcon) themeToggleIcon.className = 'fas fa-sun';
                }
            };
            updateButtonIcon();
            if (themeToggleButton) {
                themeToggleButton.addEventListener('click', () => {
                    const isDarkMode = document.documentElement.classList.toggle('dark');
                    localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
                    updateButtonIcon();
                    const themeSwitch = document.getElementById('themeToggle'); 
                    if(themeSwitch) { themeSwitch.checked = isDarkMode; }
                });
            }
            const themeSwitch = document.getElementById('themeToggle');
            if (themeSwitch) {
                themeSwitch.addEventListener('change', () => { updateButtonIcon(); });
            }
            // --- FIM DO CÓDIGO DO BOTÃO DE TEMA ---

            const progressBars = document.querySelectorAll('.progress-bar'); progressBars.forEach(bar => { const w = bar.style.width; bar.style.width = '0%'; setTimeout(() => { bar.style.width = w; }, 100); });
            document.addEventListener('click', (e) => { const c = document.getElementById('profileDropdownContainer'), d = document.getElementById('profileDropdown'); if (c && d && !c.contains(e.target) && !d.classList.contains('hidden')) { d.classList.add('hidden'); } });
            const menuBtn = document.getElementById('menu-btn'), closeMenuBtn = document.getElementById('close-menu-btn'), mobileMenu = document.getElementById('mobile-menu'), menuOverlay = document.getElementById('menu-overlay');
            window.closeMobileMenu = () => { if (!mobileMenu || !menuOverlay) return; mobileMenu.classList.remove('active'); mobileMenu.addEventListener('transitionend', () => { if (!mobileMenu.classList.contains('active')) { mobileMenu.classList.add('hidden'); } }, { once: true }); menuOverlay.classList.add('hidden'); document.body.style.overflow = ''; const i = menuBtn ? menuBtn.querySelector('i') : null; if(i) { i.classList.remove('fa-times'); i.classList.add('fa-bars'); } };
            if (menuBtn && mobileMenu && closeMenuBtn && menuOverlay) { const i = menuBtn.querySelector('i'); const o = () => { mobileMenu.classList.remove('hidden'); setTimeout(() => mobileMenu.classList.add('active'), 10); menuOverlay.classList.remove('hidden'); document.body.style.overflow = 'hidden'; if(i) { i.classList.remove('fa-bars'); i.classList.add('fa-times'); } }; menuBtn.addEventListener('click', () => { if (mobileMenu.classList.contains('hidden') || !mobileMenu.classList.contains('active')) { o(); } else { window.closeMobileMenu(); } }); closeMenuBtn.addEventListener('click', window.closeMobileMenu); menuOverlay.addEventListener('click', window.closeMobileMenu); } else { console.error("Erro menu mobile."); }
            showCategory('green');
        });


        // --- PENDÊNCIA DE DESTINO (JavaScript para atualização ao vivo) ---
        function createPendingListItem(materialType, dataFormatada) {
            const li = document.createElement('li');
            li.className = 'flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-colors border-l-4 border-yellow-400 dark:border-yellow-500';
            li.innerHTML = `
                <span class="font-medium text-gray-800 dark:text-gray-100">${materialType}</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">Cad. em ${dataFormatada}</span>
            `;
            return li;
        }

        function addPendingItem(materialType, destination, dataFormatada) {
             let listElementId;
             let emptyMessage;

             if (destination === 'coleta') {
                 listElementId = 'listColeta';
                 emptyMessage = 'Nenhum material pendente para coleta.';
             } else if (destination === 'aula') {
                 listElementId = 'listAula';
                 emptyMessage = 'Nenhum material pendente para aula.';
             } else {
                 return;
             }

             const list = document.getElementById(listElementId);
             if (!list) return;

             const emptyLi = list.querySelector('li.italic');
             if (emptyLi && emptyLi.textContent === emptyMessage) {
                 list.innerHTML = '';
             }

             const newItem = createPendingListItem(materialType, dataFormatada);
             list.appendChild(newItem);
        }

        // ============================================
        // TROCA #10: JAVASCRIPT DO FORMULÁRIO (FETCH)
        // ============================================
        const newMaterialForm = document.getElementById('newMaterialForm');
        if (newMaterialForm) { 
            newMaterialForm.onsubmit = async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const problems = formData.getAll('problems[]'); // Pega todos os checkboxes marcados
                
                // Constrói o objeto de dados
                const data = {
                    id_tipo_material: formData.get('id_tipo_material'),
                    category: formData.get('category'),
                    id_tipo_destino: formData.get('id_tipo_destino'),
                    problems: problems // Envia como um array
                };

                if (!data.id_tipo_material || !data.category || !data.id_tipo_destino) {
                     alert('Preencha Tipo, Categoria e Destino Final.');
                     return;
                }
                
                console.log("Enviando:", data);

                // Envia para o 'cadastrar_material.php'
                try {
                    const response = await fetch('cadastrar_material.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(data)
                    });

                    const responseData = await response.json();

                    if (response.status !== 200 || responseData.status !== 'success') {
                        throw new Error(responseData.message || 'Erro desconhecido do servidor.');
                    }

                    // SUCESSO!
                    console.log("Material salvo:", responseData);
                    
                    // Pega a data de hoje para o feedback ao vivo
                    const hoje = new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });

                    // Adiciona na lista de pendência (ao vivo)
                    addPendingItem(responseData.material_nome, responseData.destino_slug, hoje);

                    alert('Material Cadastrado e adicionado à lista de pendências!');
                    closeNewMaterialModal();
                    this.reset(); // Limpa o formulário

                } catch (error) {
                    console.error('Erro ao cadastrar material:', error);
                    alert('Erro ao cadastrar: ' + error.message);
                }
            };
        } else {
            console.error("Formulário newMaterialForm não encontrado.");
        }

        // --- CONTROLE DE FONTE ---
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
   
   <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
    <script>
        new window.VLibras.Widget('https://vlibras.gov.br/app');
    </script>
  
</body>
</html>