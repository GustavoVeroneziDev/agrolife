<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/versao.php';

// conexao.php é gitignored — reforço aqui pro deploy nunca quebrar por defasagem
// entre o git push (auto) e o reenvio manual desse arquivo (FTP)
if (!defined('APP_NOME')) {
    define('APP_NOME', 'Agro Life');
}

$paginaTitulo = $paginaTitulo ?? APP_NOME;
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
    <title><?= h($paginaTitulo) ?> — <?= APP_NOME ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0d9488">

    <link rel="icon" href="<?= BASE ?>/assets/img/icone.ico">
    <link rel="apple-touch-icon" href="<?= BASE ?>/assets/img/logo.png">
    <link rel="manifest" href="<?= BASE ?>/manifest.php">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= h(APP_NOME) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/paleta.css?v=<?= APP_VERSAO ?>">
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/estrutura.css?v=<?= APP_VERSAO ?>">
    <script>
    var BASE = '<?= BASE ?>';

    // ── Picker de busca (dropdown com campo de busca) ──────────
    // Fica no <head> (não no footer) de propósito: páginas podem chamar
    // initPicker() no próprio <script> antes do footer.php ser incluído,
    // então a função precisa existir antes de qualquer conteúdo da página.
    // Uso: initPicker({ pickerId, triggerId, dropdownId, searchId, listId,
    //   hiddenId, labelId, items, chave(item), renderItem(item) -> {title, sub},
    //   matches(item, queryLower) -> bool, vazioMsg, onSelect(item) })
    function escHtmlPicker(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function initPicker(opts) {
        var aberto = false;
        var selecionado = null;
        var picker, trigger, dropdown, search, list, hidden, label, erroObrigatorio;

        function mostrarErroObrigatorio() {
            if (!erroObrigatorio) return;
            erroObrigatorio.textContent = 'Campo obrigatório.';
            erroObrigatorio.style.display = '';
            trigger.style.borderColor = 'var(--cor-perigo)';
        }
        function limparErroObrigatorio() {
            if (!erroObrigatorio) return;
            erroObrigatorio.style.display = 'none';
            trigger.style.borderColor = '';
        }

        function abrir() {
            aberto = true;
            trigger.classList.add('open');
            dropdown.classList.remove('d-none');
            renderizar(opts.items);
            if (search) {
                search.value = '';
                setTimeout(function () { search.focus(); }, 40);
            }
            document.addEventListener('click', clickFora, true);
        }
        function fechar() {
            aberto = false;
            trigger.classList.remove('open');
            dropdown.classList.add('d-none');
            document.removeEventListener('click', clickFora, true);
        }
        function clickFora(e) { if (!picker.contains(e.target)) fechar(); }
        function toggle() {
            if (trigger.classList.contains('disabled')) return;
            aberto ? fechar() : abrir();
        }
        function filtrar(q) {
            q = q.toLowerCase();
            renderizar(opts.items.filter(function (it) { return opts.matches(it, q); }));
        }
        function renderizar(lista) {
            list.innerHTML = '';
            if (!lista.length) {
                list.innerHTML = '<div class="picker-empty">' + escHtmlPicker(opts.vazioMsg || 'Nada encontrado.') + '</div>';
                return;
            }
            lista.forEach(function (it) {
                var r = opts.renderItem(it);
                var icone = r.icon ? '<i class="bi ' + r.icon + ' me-1"></i>' : '';
                var div = document.createElement('div');
                div.className = 'picker-item' + (selecionado && opts.chave(selecionado) === opts.chave(it) ? ' picker-active' : '');
                div.innerHTML = '<div class="picker-item-titulo">' + icone + escHtmlPicker(r.title) + '</div>'
                    + (r.sub ? '<div class="picker-item-sub">' + escHtmlPicker(r.sub) + '</div>' : '');
                div.addEventListener('mousedown', function (e) { e.preventDefault(); selecionar(it); });
                list.appendChild(div);
            });
        }
        function selecionar(it) {
            selecionado = it;
            hidden.value = opts.chave(it);
            limparErroObrigatorio();
            var r = opts.renderItem(it);
            var icone = r.icon ? '<i class="bi ' + r.icon + ' me-1"></i>' : '';
            // r.title/r.sub podem vir de dado do usuário (nome de dono, animal…) —
            // sempre escapa antes de jogar em innerHTML, só o ícone é HTML confiável
            // (vem sempre de uma classe fixa escrita no próprio renderItem()).
            label.innerHTML = icone + escHtmlPicker(r.title) + (r.sub ? ' — ' + escHtmlPicker(r.sub) : '');
            label.className = 'picker-selected';
            fechar();
            if (opts.onSelect) opts.onSelect(it);
        }
        function iniciar() {
            picker   = document.getElementById(opts.pickerId);
            trigger  = document.getElementById(opts.triggerId);
            dropdown = document.getElementById(opts.dropdownId);
            search   = document.getElementById(opts.searchId);
            list     = document.getElementById(opts.listId);
            hidden   = document.getElementById(opts.hiddenId);
            label    = document.getElementById(opts.labelId);
            if (!picker || !trigger) return;

            trigger.addEventListener('click', toggle);
            trigger.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
            });
            if (search) search.addEventListener('input', function () { filtrar(this.value); });

            // "required" em input[hidden] é ignorado pelo navegador (não existe
            // validação nativa pra campo hidden) — sem isso, um picker obrigatório
            // vazio ia até o servidor, voltava com erro e apagava tudo que já
            // tinha sido preenchido no resto do formulário. Valida aqui, antes de
            // sair da página — igual pra todo picker com required, sem precisar
            // repetir em cada tela.
            if (hidden && hidden.hasAttribute('required')) {
                erroObrigatorio = document.createElement('div');
                erroObrigatorio.className = 'small mt-1';
                erroObrigatorio.style.color = 'var(--cor-perigo)';
                erroObrigatorio.style.display = 'none';
                picker.insertAdjacentElement('afterend', erroObrigatorio);

                var form = picker.closest('form');
                if (form) {
                    form.addEventListener('submit', function (e) {
                        if (!hidden.value) {
                            e.preventDefault();
                            mostrarErroObrigatorio();
                        }
                    });
                }
            }
        }

        // initPicker() sempre roda num <script> que vem DEPOIS do HTML do
        // campo no documento (é assim em todo lugar que ela é chamada) —
        // então os elementos já existem, mesmo com document.readyState ainda
        // "loading" (o resto da página abaixo pode nao ter carregado, mas
        // isso não importa aqui). Roda direto: adiar pro DOMContentLoaded
        // quebrava desabilitar()/setItems() chamados logo após a criação,
        // porque "trigger" etc. só seriam preenchidos depois, no futuro.
        iniciar();

        function limpar() {
            selecionado = null;
            if (hidden) hidden.value = '';
            if (label) { label.textContent = opts.placeholder || ''; label.className = 'picker-placeholder'; }
        }

        return {
            selecionar: selecionar,
            limpar: limpar,
            getSelecionado: function () { return selecionado; },
            // Troca a lista de itens (ex: raças mudam conforme a espécie escolhida).
            // Limpa a seleção atual — o item selecionado pode não existir na lista nova.
            setItems: function (novosItems, novoPlaceholder) {
                opts.items = novosItems;
                if (novoPlaceholder !== undefined) opts.placeholder = novoPlaceholder;
                limpar();
            },
            desabilitar: function (motivoPlaceholder) {
                if (trigger) trigger.classList.add('disabled');
                limpar();
                if (motivoPlaceholder !== undefined && label) label.textContent = motivoPlaceholder;
            },
            habilitar: function () {
                if (trigger) trigger.classList.remove('disabled');
            },
        };
    }

    // Amarra Espécie + Raça (raça filtra pela espécie escolhida) + Sexo, com
    // os ícones de gênero do Bootstrap Icons. Espera campos gerados por
    // campoPicker() em funcoes.php com prefixo <base>Especie / <base>Raca / <base>Sexo.
    // especies: [{id, nome, icone}] · racas: [{especie, nome}]
    // especieInicialId: em tela de edição, a espécie já selecionada — filtra
    // a raça de cara sem precisar reabrir o picker de espécie.
    function initAnimalPickers(base, especies, racas, especieInicialId) {
        var SEXOS = [
            { id: 'macho', label: 'Macho', icon: 'bi-gender-male' },
            { id: 'femea', label: 'Fêmea', icon: 'bi-gender-female' },
            { id: 'indeterminado', label: 'Indeterminado', icon: '' },
        ];

        var racaPk = initPicker({
            pickerId: base + 'RacaPicker', triggerId: base + 'RacaTrigger', dropdownId: base + 'RacaDropdown',
            searchId: base + 'RacaSearch', listId: base + 'RacaList', hiddenId: 'inp' + base + 'RacaId', labelId: base + 'RacaLabel',
            items: especieInicialId ? racas.filter(function (r) { return r.especie === especieInicialId; }) : [],
            chave: function (r) { return r.nome; },
            renderItem: function (r) { return { title: r.nome }; },
            matches: function (r, q) { return r.nome.toLowerCase().indexOf(q) !== -1; },
            vazioMsg: 'Nenhuma raça encontrada.',
        });
        if (racaPk && !especieInicialId) racaPk.desabilitar('Selecione a espécie primeiro');

        var especiePk = initPicker({
            pickerId: base + 'EspeciePicker', triggerId: base + 'EspecieTrigger', dropdownId: base + 'EspecieDropdown',
            searchId: base + 'EspecieSearch', listId: base + 'EspecieList', hiddenId: 'inp' + base + 'EspecieId', labelId: base + 'EspecieLabel',
            items: especies,
            chave: function (e) { return e.id; },
            renderItem: function (e) { return { title: e.icone + ' ' + e.nome }; },
            matches: function (e, q) { return e.nome.toLowerCase().indexOf(q) !== -1; },
            vazioMsg: 'Nenhuma espécie encontrada.',
            onSelect: function (e) {
                if (!racaPk) return;
                var filtradas = racas.filter(function (r) { return r.especie === e.id; });
                racaPk.habilitar();
                racaPk.setItems(filtradas, 'Selecione a raça…');
            },
        });

        var sexoPk = initPicker({
            pickerId: base + 'SexoPicker', triggerId: base + 'SexoTrigger', dropdownId: base + 'SexoDropdown',
            searchId: base + 'SexoSearch', listId: base + 'SexoList', hiddenId: 'inp' + base + 'SexoId', labelId: base + 'SexoLabel',
            items: SEXOS,
            chave: function (s) { return s.id; },
            renderItem: function (s) { return { title: s.label, icon: s.icon }; },
            matches: function (s, q) { return s.label.toLowerCase().indexOf(q) !== -1; },
            vazioMsg: 'Nada encontrado.',
        });

        return { especiePk: especiePk, racaPk: racaPk, sexoPk: sexoPk };
    }
    </script>
</head>

<body>

    <?php if ($ehPainel): ?>
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="fecharSidebar()"></div>
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <a href="<?= BASE ?>/index.php" class="d-flex align-items-center gap-2 text-decoration-none" style="color:inherit;">
                    <img src="<?= BASE ?>/assets/img/logo.png" alt="" width="28" height="28">
                    <span><?= APP_NOME ?></span>
                </a>
            </div>
            <?php
            $uri = $_SERVER['REQUEST_URI'];
            $menuItens = [
                ['href' => BASE . '/painel/index.php',        'icon' => 'bi-house-door',    'label' => 'Dashboard'],
                ['href' => BASE . '/painel/agenda.php',       'icon' => 'bi-calendar3',     'label' => 'Agenda'],
                ['href' => BASE . '/painel/animais.php',      'icon' => 'bi-clipboard2-pulse', 'label' => 'Animais'],
                ['href' => BASE . '/painel/clientes.php',      'icon' => 'bi-people',        'label' => 'Clientes'],
                ['href' => BASE . '/painel/tipos_vacina.php',  'icon' => 'bi-shield-plus',   'label' => 'Tipos de Vacina'],
            ];
            // Equipe e Configurações: só o admin dono do sistema mexe nisso, não os veterinários
            if ($nivelAcesso === 'admin') {
                $menuItens[] = ['href' => BASE . '/painel/equipe.php',        'icon' => 'bi-person-badge', 'label' => 'Equipe'];
                $menuItens[] = ['href' => BASE . '/painel/configuracoes.php', 'icon' => 'bi-gear',         'label' => 'Configurações'];
            }
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
                    <img src="<?= BASE ?>/assets/img/logo.png" alt="" width="26" height="26">
                    <span class="fw-bold" style="color:var(--text-main);"><?= APP_NOME ?></span>
                </a>
            </div>

            <?php flashMsg() ?>

        <?php else: ?>
            <nav class="navbar topnav sticky-top">
                <div class="container-lg">
                    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE ?>/index.php">
                        <img src="<?= BASE ?>/assets/img/logo.png" alt="" width="26" height="26"> <?= APP_NOME ?>
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
                                <li><a class="dropdown-item" href="<?= BASE ?>/usuario/meus_agendamentos.php">
                                        <i class="bi bi-calendar3 me-2"></i>Meus Agendamentos</a></li>
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