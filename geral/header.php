<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/versao.php';

$paginaTitulo = $paginaTitulo ?? 'VetSul';
$areaAtual    = $areaAtual    ?? '';
$ehPainel     = $areaAtual === 'painel';

// Auto-login por cookie lembrar-me em páginas públicas (protegidas já tratam em exigirLogin)
if (!estaLogado() && !empty($_COOKIE['vs_lembrar']) && isset($pdo)) {
    tentarLoginLembrado($pdo);
}

$_nomeSession = $_SESSION['usuario_nome'] ?? '';
$nivelAcesso  = $_SESSION['nivel_acesso'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($paginaTitulo) ?> — VetSul</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0d9488">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/paleta.css">
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/estrutura.css">
    <script>var BASE = '<?= BASE ?>';</script>
</head>

<body>

    <?php if ($ehPainel): ?>
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="fecharSidebar()"></div>
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <a href="<?= BASE ?>/index.php" class="d-flex align-items-center gap-2 text-decoration-none" style="color:inherit;">
                    <i class="bi bi-heart-pulse-fill" style="font-size:1.4rem;color:var(--accent);"></i>
                    <span>VetSul</span>
                </a>
            </div>
            <?php
            $uri = $_SERVER['REQUEST_URI'];
            $menuItens = [
                ['href' => BASE . '/painel/index.php',        'icon' => 'bi-house-door',    'label' => 'Dashboard'],
                ['href' => BASE . '/painel/animais.php',      'icon' => 'bi-clipboard2-pulse', 'label' => 'Animais'],
                ['href' => BASE . '/painel/clientes.php',      'icon' => 'bi-people',        'label' => 'Donos'],
                ['href' => BASE . '/painel/tipos_vacina.php',  'icon' => 'bi-shield-plus',   'label' => 'Tipos de Vacina'],
                ['href' => BASE . '/painel/configuracoes.php', 'icon' => 'bi-gear',          'label' => 'Configurações'],
            ];
            ?>
            <ul class="sidebar-nav">
                <?php foreach ($menuItens as $item): ?>
                    <li>
                        <a href="<?= $item['href'] ?>" class="<?= str_contains($uri, $item['href']) ? 'ativo' : '' ?>">
                            <i class="bi <?= $item['icon'] ?>"></i>
                            <?= $item['label'] ?>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
            <div class="sidebar-footer">
                <div class="mb-1 d-flex align-items-center">
                    <i class="bi bi-person-circle me-1 flex-shrink-0"></i>
                    <span class="nome-truncado"><?= h($_nomeSession) ?></span>
                </div>
                <a href="<?= BASE ?>/usuario/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Sair</a>
                <?php
                    // Nota: cada exec() usa no máximo um "%" — no Windows, escapeshellarg()
                    // neutraliza "%" (risco de expansão de variável do cmd.exe), e uma string
                    // com dois "%" formando um par (ex: %h|||%cd) é lida como uma única
                    // variável "%h|||%" e quebra o comando.
                    $gitVer = null;
                    $repoDir = escapeshellarg(__DIR__ . '/..');
                    $hashOut = $dateOut = [];
                    @exec("git -C {$repoDir} rev-parse --short HEAD 2>&1", $hashOut, $retHash);
                    @exec("git -C {$repoDir} log -1 --format=%cI 2>&1", $dateOut, $retData);
                    if ($retHash === 0 && $retData === 0 && !empty($hashOut[0]) && !empty($dateOut[0])) {
                        $gitVer = trim($hashOut[0]) . ' · ' . date('d/m/y H:i', strtotime(trim($dateOut[0])));
                    }
                ?>
                <div class="sidebar-version" title="Confirme aqui se o deploy já chegou">
                    <?php if ($gitVer): ?>
                        <i class="bi bi-tag-fill me-1 opacity-50"></i><?= h($gitVer) ?>
                    <?php else: ?>
                        build <?= h(APP_VERSAO) ?>
                    <?php endif ?>
                </div>
            </div>
        </nav>

        <div class="painel-content">
            <div class="d-flex d-md-none align-items-center mb-3 gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="abrirSidebar()">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <a href="<?= BASE ?>/index.php" class="d-flex align-items-center gap-2 text-decoration-none">
                    <i class="bi bi-heart-pulse-fill" style="font-size:1.3rem;color:var(--accent);"></i>
                    <span class="fw-bold" style="color:var(--text-main);">VetSul</span>
                </a>
            </div>

            <?php flashMsg() ?>

        <?php else: ?>
            <nav class="navbar topnav sticky-top">
                <div class="container-lg">
                    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE ?>/index.php">
                        <i class="bi bi-heart-pulse-fill"></i> VetSul
                    </a>

                    <?php if (estaLogado()): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1 flex-shrink-0"></i>
                                <span class="nome-truncado"><?= h($_nomeSession) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= BASE ?>/usuario/meus_animais.php">
                                        <i class="bi bi-clipboard2-pulse me-2"></i>Meus Animais</a></li>
                                <li><a class="dropdown-item" href="<?= BASE ?>/usuario/perfil.php">
                                        <i class="bi bi-person me-2"></i>Meu Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= BASE ?>/usuario/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="d-flex gap-2">
                            <a href="<?= BASE ?>/usuario/login.php" class="btn btn-sm btn-outline-accent"><i class="bi bi-box-arrow-in-right me-1"></i>Entrar</a>
                            <a href="<?= BASE ?>/usuario/cadastro.php" class="btn btn-sm btn-accent"><i class="bi bi-person-plus me-1"></i>Cadastrar</a>
                        </div>
                    <?php endif ?>
                </div>
            </nav>

            <main class="container-lg py-4">
                <?php flashMsg() ?>
            <?php endif ?>