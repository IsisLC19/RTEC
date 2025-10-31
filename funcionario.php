<?php
// 1. INICIA A SESSÃO (OBRIGATÓRIO)
session_start();

// 2. INCLUI A CONEXÃO
require_once 'conexao.php'; // $conn (a conexão) está disponível agora

// 3. BLOCO DE SEGURANÇA
if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'funcionario') {
    session_destroy();
    header("Location: entrar.html");
    exit();
}

$nome_usuario = $_SESSION['nome_usuario'];

// ==========================================================
// 4. BUSCA DE DADOS (QUERIES) - EXPANDIDO
// ==========================================================

// --- QUERIES PARA OS CARDS ---
$result_total = $conn->query("SELECT COUNT(id_agendamento) AS total FROM tbl_agendamento");
$total_doacoes = $result_total->fetch_assoc()['total'];

$result_proc = $conn->query("SELECT COUNT(id_agendamento) AS total FROM tbl_agendamento WHERE status = 'Concluido'");
$itens_processados = $result_proc->fetch_assoc()['total'];

$result_mes = $conn->query("
    SELECT COUNT(id_agendamento) AS total 
    FROM tbl_agendamento 
    WHERE MONTH(data_agendada) = MONTH(CURRENT_DATE()) 
      AND YEAR(data_agendada) = YEAR(CURRENT_DATE())
");
$doacoes_mes = $result_mes->fetch_assoc()['total'];

$result_coletado = $conn->query("SELECT COUNT(id_agendamento) AS total FROM tbl_agendamento WHERE tipo_pessoa = 'coletor'");
$coletado = $result_coletado->fetch_assoc()['total'];

// --- QUERY PARA A TABELA "DOAÇÕES RECENTES" (Aba Doações) ---
// Junta tbl_agendamento (a) com tbl_tiporesiduo (r)
$query_doacoes = "
    SELECT DATE_FORMAT(a.data_agendada, '%d/%m/%Y') AS data_formatada, a.tipo_pessoa, r.nome_residuo, a.status 
    FROM tbl_agendamento a
    JOIN tbl_tiporesiduo r ON a.id_tipo_residuo = r.id_tipo_residuo
    ORDER BY a.data_agendada DESC 
    LIMIT 3
";
$result_doacoes_recentes = $conn->query($query_doacoes);

// --- QUERY PARA OS CARDS "PROTOCOLOS DE COLETAS" (Aba Doações) ---
// Busca agendamentos 'Agendado' ou 'Pendente'
$query_protocolos = "
    SELECT a.protocolo_agendamento, a.tipo_pessoa, r.nome_residuo, 
           DATE_FORMAT(a.data_agendada, '%d/%m/%Y') AS data_formatada, 
           TIME_FORMAT(a.hora_agendada, '%H:%i') AS hora_formatada, a.status 
    FROM tbl_agendamento a
    JOIN tbl_tiporesiduo r ON a.id_tipo_residuo = r.id_tipo_residuo
    WHERE a.status IN ('Agendado', 'Pendente') 
    ORDER BY a.data_agendada ASC
    LIMIT 4
";
$result_protocolos = $conn->query($query_protocolos);

// Busca o endereço de coleta para o modal (da tbl_configuracoes_sistema)
$result_config = $conn->query("SELECT endereco_coleta FROM tbl_configuracoes_sistema WHERE id_config = 1");
$endereco_coleta = $result_config->fetch_assoc()['endereco_coleta'];

// --- QUERY PARA OS "FEEDBACKS DOS USUÁRIOS" (Aba Doações) ---
$query_feedbacks = "
    SELECT avaliacao_estrelas, recomendaria, DATE_FORMAT(data_envio, '%d/%m/%Y') AS data_formatada 
    FROM tbl_feedback 
    ORDER BY data_envio DESC 
    LIMIT 2
";
$result_feedbacks = $conn->query($query_feedbacks);

// --- QUERIES PARA A ABA "MATERIAL EDUCACIONAL" (Ainda estático, vamos focar na aba Doações primeiro) ---
// (Adicionar queries para Itens para Ensino, Kits Montados, etc. aqui no futuro)

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relatórios - RTEC</title>
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
    /* ... [SEU CSS CONTINUA IDÊNTICO, NÃO PRECISA MUDAR NADA] ... */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; box-sizing: border-box; }
    .gradient-bg { background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%); }
    .nav-link:hover { color: #10b981; transform: translateY(-1px); }
    .report-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
    .dark .report-card:hover { box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3); }
    .chart-container { position: relative; height: 300px; }
    .progress-bar { transition: width 0.8s ease-in-out; }
    .filter-active { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white !important; }
    .filter-inactive:hover { background-color: #f3f4f6; }
    .dark .filter-inactive:hover { background-color: #374151; }
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
<body class="min-h-screen bg-gray-50 dark:bg-gray-900">
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
            <img
                src="img/usuario.png"
                alt="Usuário"
                id="profileButton"
                class="h-10 w-10 rounded-full object-cover cursor-pointer border-2 border-green-600 hover:border-green-800 transition"
                onclick="toggleProfileDropdown()"
            >
            <div id="profileDropdown" class="absolute right-0 mt-2 w-48 rounded-lg shadow-xl bg-white dark:bg-gray-700 ring-1 ring-black dark:ring-gray-600 ring-opacity-5 z-50 hidden">
                <div class="py-1">
                    <p class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 font-semibold border-b dark:border-gray-600">
                        Olá, <?php echo htmlspecialchars($nome_usuario); ?>
                    </p>
                    <a href="coletores.html" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                        <i class="fas fa-chart-line mr-2"></i>Dashboard
                    </a>
                    <a href="configuracoes.html" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600"><i class="fas fa-cog mr-2"></i>Configurações</a>
                    <a href="#" onclick="fazerLogout(event)" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900 hover:text-red-700 dark:hover:text-red-300 border-t dark:border-gray-600 mt-1">
                        <i class="fas fa-sign-out-alt mr-2"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<nav class="py-4">
    <div class="container mx-auto px-4 flex items-center justify-between">
        <a href="sobre.html" class="block"><img src="img/logo.png" alt="Logo" class="h-12 w-auto"></a>
        <ul class="hidden md:flex space-x-8">
             <li><a href="coletores.html" class="nav-link text-gray-700 dark:text-gray-300 font-medium hover:text-green-600 dark:hover:text-green-400">DASHBOARD</a></li>
             <li><a href="funcionario.php" class="nav-link text-green-600 dark:text-green-400 font-medium border-b-2 border-green-600">RELATÓRIOS</a></li>
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
     <li><a href="coletores.html">DASHBOARD</a></li>
     <li><a href="funcionario.php" class="active-link-mobile">RELATÓRIOS</a></li>
     <li><a href="configuracoes.html">CONFIGURAÇÕES</a></li>
</ul>
<div class="mobile-menu-footer">
    <a href="#" onclick="fazerLogout(event); closeMobileMenu();" class="btn-mobile-login btn-mobile-logout">
        <i class="fas fa-sign-out-alt mr-2"></i>Sair
    </a>
</div>
</div>

<div id="menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-[99] hidden"></div>

     <main class="min-h-screen py-8 bg-gray-50 dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 dark:text-white mb-4">Relatórios de Resíduos Eletrônicos</h1>
                <p class="text-lg sm:text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">Acompanhe as doações recebidas e o aproveitamento dos materiais para fins educacionais</p>
            </div>

            <div class="flex flex-col sm:flex-row justify-center items-center gap-4 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-2 shadow-md flex flex-wrap justify-center">
                    <button onclick="showReport('donations')" id="donationsTab" class="filter-inactive filter-active px-4 sm:px-6 py-3 rounded-lg font-medium transition-colors duration-300 mb-2 sm:mb-0">
                        <i class="fas fa-donate mr-2"></i>Doações Recebidas
                    </button>
                    <button onclick="showReport('educational')" id="educationalTab" class="filter-inactive px-4 sm:px-6 py-3 rounded-lg font-medium text-gray-600 dark:text-gray-300 transition-colors duration-300">
                        <i class="fas fa-graduation-cap mr-2"></i>Material Educacional
                    </button>
                </div>

                <button id="pdfButton" onclick="generatePDF()" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium shadow-md transition-all duration-300 flex items-center w-full sm:w-auto justify-center">
                    <i class="fas fa-file-pdf mr-2"></i><span id="pdfButtonText">Gerar PDF</span>
                </button>
            </div>

            <div id="donationsReport" class="report-section">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg report-card transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div><p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Total de Doações</p><p class="text-3xl font-bold text-green-600 dark:text-green-400"><?php echo $total_doacoes; ?></p></div>
                            <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full"><i class="fas fa-box text-green-600 dark:text-green-400 text-xl"></i></div>
                        </div>
                        </div>
                     <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg report-card transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div><p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Itens Processados</p><p class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?php echo $itens_processados; ?></p></div>
                            <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full"><i class="fas fa-check-circle text-blue-600 dark:text-blue-400 text-xl"></i></div>
                        </div>
                        </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg report-card transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div><p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Doações do Mês</p><p class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?php echo $doacoes_mes; ?></p></div>
                            <div class="bg-purple-100 dark:bg-purple-900 p-3 rounded-full"><i class="fas fa-users text-purple-600 dark:text-purple-400 text-xl"></i></div>
                        </div>
                        </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg report-card transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div><p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Coletado</p><p class="text-3xl font-bold text-orange-600 dark:text-orange-400"><?php echo $coletado; ?></p></div>
                            <div class="bg-orange-100 dark:bg-orange-900 p-3 rounded-full"><i class="fas fa-truck-loading text-orange-600 dark:text-orange-400 text-xl"></i></div>
                        </div>
                        </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg">
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Tipos de Resíduos Doados</h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700 dark:text-gray-300">Computadores <span class="text-gray-500 dark:text-gray-400 text-sm">35%</span></span> </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                <div class="bg-green-500 h-3 rounded-full progress-bar" style="width: 35%"></div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700 dark:text-gray-300">Celulares <span class="text-gray-500 dark:text-gray-400 text-sm">28%</span></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                <div class="bg-blue-500 h-3 rounded-full progress-bar" style="width: 28%"></div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700 dark:text-gray-300">Televisores <span class="text-gray-500 dark:text-gray-400 text-sm">18%</span></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                <div class="bg-purple-500 h-3 rounded-full progress-bar" style="width: 18%"></div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700 dark:text-gray-300">Outros <span class="text-gray-500 dark:text-gray-400 text-sm">19%</span></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                <div class="bg-orange-500 h-3 rounded-full progress-bar" style="width: 19%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg">
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Tendência Mensal</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">...</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white">Doações Recentes</h3>
                    </div>

                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Data</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Resíduo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php
                                // Inicia o loop PHP para preencher a tabela
                                if ($result_doacoes_recentes->num_rows > 0) {
                                    // 'fetch_assoc' pega uma linha de cada vez
                                    while($row = $result_doacoes_recentes->fetch_assoc()) {
                                        
                                        // Define a cor do status
                                        $status_class = '';
                                        if ($row['status'] == 'Concluido') {
                                            $status_class = 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200';
                                        } elseif ($row['status'] == 'Pendente') {
                                            $status_class = 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200';
                                        } elseif ($row['status'] == 'Agendado') {
                                            $status_class = 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200';
                                        }
                                ?>
                                <tr class="table-row">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($row['data_formatada']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"><?php echo ucfirst(htmlspecialchars($row['tipo_pessoa'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($row['nome_residuo']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php
                                    } // Fim do 'while'
                                } else {
                                    // Se o banco não retornar nada
                                    echo '<tr class="table-row"><td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">Nenhuma doação recente encontrada.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl mt-10 p-6 sm:p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center">
                            <i class="fas fa-clipboard-check text-green-600 dark:text-green-400 mr-2"></i> Protocolos de Coletas
                        </h2>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Visualize os protocolos pendentes</span>
                    </div>

                    <div class="mb-6 flex flex-col sm:flex-row gap-3">
                        <input type="text" id="buscarProtocolo" placeholder="🔍 Buscar protocolo..."
                            class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg px-4 py-2 w-full sm:w-1/2 focus:ring-2 focus:ring-green-500 focus:outline-none">
                    </div>

                    <div id="listaProtocolos" class="grid md:grid-cols-2 gap-6">
                        <?php
                        // Inicia o loop PHP para preencher os cards de protocolo
                        if ($result_protocolos->num_rows > 0) {
                            while($row = $result_protocolos->fetch_assoc()) {
                                
                                // Define a cor do status
                                $status_class = '';
                                if ($row['status'] == 'Pendente') {
                                    $status_class = 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200';
                                } elseif ($row['status'] == 'Agendado') {
                                    $status_class = 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200';
                                }
                                
                                // JSON data for modal
                                $modal_data = htmlspecialchars(json_encode([
                                    'protocolo' => $row['protocolo_agendamento'],
                                    'tipo' => ucfirst($row['tipo_pessoa']),
                                    'residuo' => $row['nome_residuo'],
                                    'data' => $row['data_formatada'],
                                    'hora' => $row['hora_formatada'],
                                    'local' => $endereco_coleta // Variável que buscamos no topo
                                ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-700 rounded-xl p-5 shadow-sm hover:shadow-lg transition">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-bold text-green-700 dark:text-green-400">#<?php echo htmlspecialchars($row['protocolo_agendamento']); ?></h3>
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </div>

                            <div class="text-sm space-y-2 text-gray-700 dark:text-gray-300">
                                <p><strong class="dark:text-gray-100">Tipo:</strong> <?php echo ucfirst(htmlspecialchars($row['tipo_pessoa'])); ?></p>
                                <p><strong class="dark:text-gray-100">Resíduo:</strong> <?php echo htmlspecialchars($row['nome_residuo']); ?></p>
                                <p><strong class="dark:text-gray-100">Data:</strong> <?php echo htmlspecialchars($row['data_formatada']); ?></p>
                                <p><strong class="dark:text-gray-100">Horário:</strong> <?php echo htmlspecialchars($row['hora_formatada']); ?></p>
                                <p><strong class="dark:text-gray-100">Local:</strong> <?php echo htmlspecialchars($endereco_coleta); ?></p>
                            </div>

                            <div class="mt-4 flex justify-end">
                                <button onclick='abrirModalProtocolo(<?php echo $modal_data; ?>)'
                                    class="text-sm bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    <i class="fas fa-eye mr-1"></i> Ver Detalhes
                                </button>
                            </div>
                        </div>
                        <?php
                            } // Fim do 'while'
                        } else {
                            // Se não houver protocolos pendentes
                            echo '<p class="text-gray-500 dark:text-gray-400 md:col-span-2 text-center">Nenhum protocolo pendente encontrado.</p>';
                        }
                        ?>
                    </div>
                </div>
                <div id="visualizarProtocolo" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
                    <button onclick="fecharModalProtocolo()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full">
                        <div class="p-6 md:p-8 text-center">
                            <div class="w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-clipboard-list text-green-600 dark:text-green-400 text-3xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Detalhes do Protocolo</h3>
                            <p class="text-gray-600 dark:text-gray-300 mb-6">Informações completas da coleta agendada</p>

                            <div class="bg-green-50 dark:bg-gray-700 border-2 border-green-200 dark:border-gray-600 rounded-lg p-4 mb-6">
                                <div class="text-sm text-gray-600 dark:text-gray-300 mb-1">Protocolo:</div>
                                <div id="protocoloNumero" class="text-2xl font-bold text-green-600 font-mono"></div>
                            </div>

                            <div class="text-left bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6 space-y-2 text-sm">
                                <div class="flex flex-col sm:flex-row sm:justify-between"><span class="text-gray-600 dark:text-gray-300">Tipo:</span><span id="protocoloTipo" class="font-medium dark:text-white sm:text-right"></span></div>
                                <div class="flex flex-col sm:flex-row sm:justify-between"><span class="text-gray-600 dark:text-gray-300">Resíduo:</span><span id="protocoloResiduo" class="font-medium dark:text-white sm:text-right"></span></div>
                                <div class="flex flex-col sm:flex-row sm:justify-between"><span class="text-gray-600 dark:text-gray-300">Data:</span><span id="protocoloData" class="font-medium dark:text-white sm:text-right"></span></div>
                                <div class="flex flex-col sm:flex-row sm:justify-between"><span class="text-gray-600 dark:text-gray-300">Horário:</span><span id="protocoloHora" class="font-medium dark:text-white sm:text-right"></span></div>
                                <div class="flex flex-col sm:flex-row sm:justify-between"><span class="text-gray-600 dark:text-gray-300 shrink-0">Local:</span><span id="protocoloLocal" class="font-medium dark:text-white sm:text-right"></span></div>
                            </div>

                            <button onclick="fecharModalProtocolo()" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-4 rounded-lg">Fechar</button>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden mt-10 mb-10">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white flex items-center">
                        <i class="fas fa-comment-dots text-green-600 dark:text-green-400 mr-2"></i> Feedbacks dos Usuários
                        </h3>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Avaliações recentes</span>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php
                        // Inicia o loop PHP para preencher os feedbacks
                        if ($result_feedbacks->num_rows > 0) {
                            while($row = $result_feedbacks->fetch_assoc()) {
                                
                                // Converte 'sim'/'nao' para texto
                                $recomendaria_texto = ($row['recomendaria'] == 'sim') ? 'Sim' : 'Não';
                                // Cria as estrelas
                                $estrelas_html = '';
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $row['avaliacao_estrelas']) {
                                        $estrelas_html .= '<i class="fas fa-star"></i>'; // Cheia
                                    } else {
                                        $estrelas_html .= '<i class="far fa-star text-gray-300 dark:text-gray-600"></i>'; // Vazia
                                    }
                                }
                        ?>
                        <div class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center text-yellow-400 dark:text-yellow-500">
                                    <?php echo $estrelas_html; ?>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mb-2"><strong>Recomendaria:</strong> <?php echo $recomendaria_texto; ?></p>
                            <div class="flex flex-wrap gap-2 mb-2">
                                <span class="px-3 py-1 text-xs rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Tag Exemplo 1</span>
                            </div>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-3">Enviado em <?php echo $row['data_formatada']; ?></p>
                        </div>
                        <?php
                            } // Fim do 'while'
                        } else {
                            // Se não houver feedbacks
                            echo '<div class="p-6 text-center text-gray-500 dark:text-gray-400">Nenhum feedback recebido ainda.</div>';
                        }
                        ?>
                    </div>
                </div>
                </div>
            <div id="educationalReport" class="report-section hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 max-w-2xl mx-auto">
                    </div>
                <div class="max-w-2xl mx-auto mb-8">
                    </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    </div>
            </div>
            </div>
    </main>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        // --- FUNÇÕES DE RELATÓRIO E PDF ---
        // ... (Seu JavaScript de showReport e generatePDF continua o mesmo) ...
        function showReport(reportType) {
            const donationsReport = document.getElementById('donationsReport');
            const educationalReport = document.getElementById('educationalReport');
            const donationsTab = document.getElementById('donationsTab');
            const educationalTab = document.getElementById('educationalTab');
            if(donationsReport) donationsReport.classList.add('hidden');
            if(educationalReport) educationalReport.classList.add('hidden');
            if(donationsTab) {
                donationsTab.classList.remove('filter-active', 'text-white');
                donationsTab.classList.add('text-gray-600', 'dark:text-gray-300', 'filter-inactive');
            }
             if(educationalTab) {
                educationalTab.classList.remove('filter-active', 'text-white');
                educationalTab.classList.add('text-gray-600', 'dark:text-gray-300', 'filter-inactive');
            }
            if (reportType === 'donations' && donationsReport) {
                donationsReport.classList.remove('hidden');
                if(donationsTab) {
                    donationsTab.classList.add('filter-active', 'text-white');
                    donationsTab.classList.remove('text-gray-600', 'dark:text-gray-300', 'filter-inactive');
                }
            } else if (reportType === 'educational' && educationalReport) {
                educationalReport.classList.remove('hidden');
                 if(educationalTab) {
                    educationalTab.classList.add('filter-active', 'text-white');
                    educationalTab.classList.remove('text-gray-600', 'dark:text-gray-300', 'filter-inactive');
                }
            }
        }
        async function generatePDF() { /* ... (Seu código de PDF) ... */ }


        // --- Funções do Dropdown de Perfil ---
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            if (dropdown) dropdown.classList.toggle('hidden');
        }
        function fazerLogout(event) {
            event.preventDefault();
            // Limpa a sessão no servidor (idealmente, teria um 'logout.php')
            <?php session_destroy(); ?> 
            // Limpa o localStorage do cliente
            localStorage.removeItem('userToken');
            localStorage.removeItem('userRole');
            sessionStorage.removeItem('userData');
            alert('Sessão encerrada com sucesso.');
            window.location.href = 'entrar.html';
        }

        // --- Script do Menu Mobile e Inicialização ---
        document.addEventListener('DOMContentLoaded', () => {
            // ... (Seu script de progress bars, dropdown click outside, e menu mobile) ...
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => { const width = bar.style.width; bar.style.width = '0%'; setTimeout(() => { bar.style.width = width; }, 100); });
            document.addEventListener('click', (e) => {
                const container = document.getElementById('profileDropdownContainer');
                const dropdown = document.getElementById('profileDropdown');
                if (container && dropdown && !container.contains(e.target) && !dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                }
            });
            const menuBtn = document.getElementById('menu-btn');
            const closeMenuBtn = document.getElementById('close-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuOverlay = document.getElementById('menu-overlay');
            window.closeMobileMenu = () => {
                 if (!mobileMenu || !menuOverlay) return;
                mobileMenu.classList.remove('active');
                mobileMenu.addEventListener('transitionend', () => { if (!mobileMenu.classList.contains('active')) { mobileMenu.classList.add('hidden'); } }, { once: true });
                menuOverlay.classList.add('hidden');
                document.body.style.overflow = '';
                const menuIcon = menuBtn ? menuBtn.querySelector('i') : null;
                if(menuIcon) { menuIcon.classList.remove('fa-times'); menuIcon.classList.add('fa-bars'); }
            };
            if (menuBtn && mobileMenu && closeMenuBtn && menuOverlay) {
                const menuIcon = menuBtn.querySelector('i');
                const openMobileMenu = () => {
                    mobileMenu.classList.remove('hidden');
                    setTimeout(() => mobileMenu.classList.add('active'), 10);
                    menuOverlay.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    if(menuIcon) { menuIcon.classList.remove('fa-bars'); menuIcon.classList.add('fa-times'); }
                };
                menuBtn.addEventListener('click', () => {
                   if (mobileMenu.classList.contains('hidden') || !mobileMenu.classList.contains('active')) { openMobileMenu(); } 
                   else { window.closeMobileMenu(); }
                });
                closeMenuBtn.addEventListener('click', window.closeMobileMenu);
                menuOverlay.addEventListener('click', window.closeMobileMenu);
            } else { console.error("Erro: Elementos do menu mobile não encontrados."); }
            showReport('donations');
        });

          
        // ============================================
        // TROCA #6: FUNÇÃO DO MODAL ATUALIZADA
        // ============================================
        // A função agora aceita um objeto 'data' com todos os detalhes
        function abrirModalProtocolo(data) {
            const modal = document.getElementById("visualizarProtocolo");
            if (!modal) return;
            
            // Preenche os campos do modal com os dados do PHP
            document.getElementById("protocoloNumero").textContent = '#' + (data.protocolo || 'N/D');
            document.getElementById("protocoloTipo").textContent = data.tipo || 'N/D';
            document.getElementById("protocoloResiduo").textContent = data.residuo || 'N/D';
            document.getElementById("protocoloData").textContent = data.data || 'N/D';
            document.getElementById("protocoloHora").textContent = data.hora || 'N/D';
            document.getElementById("protocoloLocal").textContent = data.local || 'Endereço não definido';
            
            modal.classList.remove("hidden");
            modal.classList.add("flex");
        }

        function fecharModalProtocolo() {
            const modal = document.getElementById("visualizarProtocolo");
            if (!modal) return;
            modal.classList.add("hidden");
            modal.classList.remove("flex");
        }
    </script>

    <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
    <script>
        new window.VLibras.Widget('https://vlibras.gov.br/app');
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // ... (Seu script de botão de tema, que já está correto) ...
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
                if (isDarkMode) { localStorage.setItem('theme', 'dark'); } 
                else { localStorage.setItem('theme', 'light'); }
                updateButtonIcon();
                const themeSwitch = document.getElementById('themeToggle');
                if(themeSwitch) { themeSwitch.checked = isDarkMode; }
            });
        }
        const themeSwitch = document.getElementById('themeToggle');
        if (themeSwitch) {
            themeSwitch.addEventListener('change', () => { updateButtonIcon(); });
        }
    }); // Fim do DOMContentLoaded

       
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