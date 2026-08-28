<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin', 'funcionario');

$hoje = date('Y-m-d');

try {
    $totalAnimais = (int) $pdo->query("SELECT COUNT(*) FROM Animais WHERE Ativo = 1")->fetchColumn();
    $totalDonos   = (int) $pdo->query("SELECT COUNT(*) FROM Usuarios WHERE NivelAcesso = 'cliente' AND Ativo = 1")->fetchColumn();

    $atrasadas = (int) $pdo->query(
        "SELECT COUNT(*) FROM RegistrosVacinas rv
         JOIN Animais a ON a.IDAnimal = rv.FKAnimal
         WHERE rv.ProximaData < CURDATE() AND a.Ativo = 1"
    )->fetchColumn();

    $vencendo = (int) $pdo->query(
        "SELECT COUNT(*) FROM RegistrosVacinas rv
         JOIN Animais a ON a.IDAnimal = rv.FKAnimal
         WHERE rv.ProximaData BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
           AND a.Ativo = 1"
    )->fetchColumn();

    // Filtro dos cards clicáveis "Vacinas atrasadas" / "Vencendo em 30 dias"
    // — só aceita os dois valores conhecidos, qualquer outra coisa cai no
    // padrão (sem filtro, mostra tudo que tem próxima data marcada).
    $filtroVac = in_array($_GET['vac'] ?? '', ['atrasadas', 'vencendo'], true) ? $_GET['vac'] : '';
    $ondeVac = "rv.ProximaData IS NOT NULL AND a.Ativo = 1";
    if ($filtroVac === 'atrasadas') {
        $ondeVac .= " AND rv.ProximaData < CURDATE()";
    } elseif ($filtroVac === 'vencendo') {
        $ondeVac .= " AND rv.ProximaData BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }
    $proximas = $pdo->query(
        "SELECT rv.IDRegistro, rv.ProximaData, a.IDAnimal, a.Nome AS NomeAnimal,
                u.Nome AS NomeDono, u.Telefone, tv.Nome AS NomeVacina
         FROM RegistrosVacinas rv
         JOIN Animais a   ON a.IDAnimal   = rv.FKAnimal
         JOIN Usuarios u  ON u.IDUsuario  = a.FKDono
         JOIN TiposVacina tv ON tv.IDTipo = rv.FKTipoVacina
         WHERE {$ondeVac}
         ORDER BY rv.ProximaData ASC
         LIMIT 10"
    )->fetchAll();

    // Agenda separada em "hoje" (foco imediato) e "próximos dias" (visão
    // geral), pra não misturar o que precisa de atenção agora com o resto.
    // Quem tem cargo de veterinário só vê os próprios agendamentos (é sobre
    // a função clínica, não o nível de acesso — admin donos que também são
    // veterinários caem aqui igual); quem não é veterinário (nem cargo nem
    // admin sem esse cargo) vê tudo.
    $souVeterinario = ($_SESSION['cargo'] ?? '') === 'veterinario';
    $filtroVetSql   = $souVeterinario ? ' AND ag.FKVeterinario = :uid' : '';
    $paramsAg       = $souVeterinario ? [':uid' => $_SESSION['usuario_id']] : [];

    $stmtHoje = $pdo->prepare(
        "SELECT ag.IDAgendamento, ag.Tipo, ag.Titulo, ag.DataHoraInicio, ag.Status,
                a.Nome AS NomeAnimal, e.Icone AS IconeEspecie
         FROM Agendamentos ag
         JOIN Animais a  ON a.IDAnimal = ag.FKAnimal
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         WHERE ag.Status IN ('pendente','confirmado')
           AND DATE(ag.DataHoraInicio) = CURDATE()
           {$filtroVetSql}
         ORDER BY ag.DataHoraInicio ASC"
    );
    $stmtHoje->execute($paramsAg);
    $agendamentosHoje = $stmtHoje->fetchAll();

    $stmtProximos = $pdo->prepare(
        "SELECT ag.IDAgendamento, ag.Tipo, ag.Titulo, ag.DataHoraInicio, ag.Status,
                a.Nome AS NomeAnimal, e.Icone AS IconeEspecie
         FROM Agendamentos ag
         JOIN Animais a  ON a.IDAnimal = ag.FKAnimal
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         WHERE ag.Status IN ('pendente','confirmado')
           AND ag.DataHoraInicio >= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
           {$filtroVetSql}
         ORDER BY ag.DataHoraInicio ASC
         LIMIT 6"
    );
    $stmtProximos->execute($paramsAg);
    $proximosAgendamentos = $stmtProximos->fetchAll();

    // Últimos clientes e animais cadastrados, misturados por data — dá pra
    // conferir rápido se um cadastro feito por telefone/balcão entrou certo.
    $recentes = $pdo->query(
        "(SELECT 'cliente' AS Tipo, IDUsuario AS ID, Nome, MomentoRegistro
          FROM Usuarios WHERE NivelAcesso = 'cliente' AND Ativo = 1)
         UNION ALL
         (SELECT 'animal' AS Tipo, IDAnimal AS ID, Nome, MomentoRegistro
          FROM Animais WHERE Ativo = 1)
         ORDER BY MomentoRegistro DESC
         LIMIT 5"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[PainelDash] ' . $e->getMessage());
    $totalAnimais = $totalDonos = $vencendo = $atrasadas = 0;
    $filtroVac = '';
    $souVeterinario = false;
    $proximas = $agendamentosHoje = $proximosAgendamentos = $recentes = [];
}

$paginaTitulo = 'Dashboard';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<h4 class="fw-bold mb-4">Dashboard</h4>

<div class="row g-3 mb-4">
    <?php
    $stats = [
        ['bi-exclamation-triangle-fill', 'var(--cor-perigo)',  'var(--cor-perigo-bg)',  'Vacinas atrasadas',   $atrasadas,    BASE . '/painel/index.php?vac=atrasadas#vacinas', $filtroVac === 'atrasadas'],
        ['bi-clock-fill',                'var(--cor-atencao)', 'var(--cor-atencao-bg)', 'Vencendo em 30 dias', $vencendo,     BASE . '/painel/index.php?vac=vencendo#vacinas',   $filtroVac === 'vencendo'],
        ['bi-clipboard2-pulse',          'var(--accent)',      'var(--accent-light)',   'Animais ativos',       $totalAnimais, BASE . '/painel/animais.php',  false],
        ['bi-people',                    'var(--cor-info)',    'var(--cor-info-bg)',    'Clientes cadastrados', $totalDonos,   BASE . '/painel/clientes.php', false],
    ];
    foreach ($stats as [$icon, $color, $bg, $label, $valor, $link, $ativo]):
    ?>
        <div class="col-6 col-xl-3">
            <a href="<?= h($link) ?>" class="card stat-card stat-card-link h-100 <?= $ativo ? 'stat-card-active' : '' ?>">
                <div class="stat-card-top">
                    <span class="stat-card-label"><?= $label ?></span>
                    <div class="stat-icon" style="background:<?= $bg ?>;color:<?= $color ?>">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                </div>
                <div class="stat-card-valor"><?= number_format($valor) ?></div>
            </a>
        </div>
    <?php endforeach ?>
</div>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between px-4 py-3">
        <span><i class="bi bi-calendar-day me-2 text-accent"></i><?= $souVeterinario ? 'Sua agenda de hoje' : 'Agenda de hoje' ?></span>
        <a href="<?= BASE ?>/painel/agenda.php?vista=semana&dia=<?= $hoje ?>" class="small">Ver na agenda</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($agendamentosHoje)): ?>
            <div class="text-center py-4 text-secondary">
                <i class="bi bi-cup-hot fs-2 d-block mb-2 opacity-25"></i>
                <p class="mb-0 small">Nada agendado pra hoje.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:var(--bg-hover);">
                        <tr>
                            <th class="px-4 py-3">Horário</th>
                            <th>Animal</th>
                            <th>Título</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agendamentosHoje as $ag): ?>
                            <tr>
                                <td class="px-4 small fw-medium"><?= date('H:i', strtotime($ag['DataHoraInicio'])) ?></td>
                                <td class="small"><?= especieIconeHtml($ag['IconeEspecie']) ?> <?= h($ag['NomeAnimal']) ?></td>
                                <td class="small"><?= h($ag['Titulo']) ?></td>
                                <td><?= labelStatusAgendamento($ag['Status']) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>

<?php if (!empty($proximosAgendamentos)): ?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between px-4 py-3">
        <span><i class="bi bi-calendar3 me-2 text-accent"></i>Próximos dias</span>
        <a href="<?= BASE ?>/painel/agenda.php" class="small">Ver agenda</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background:var(--bg-hover);">
                    <tr>
                        <th class="px-4 py-3">Quando</th>
                        <th>Animal</th>
                        <th>Título</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proximosAgendamentos as $ag): ?>
                        <tr>
                            <td class="px-4 small fw-medium"><?= formatarData($ag['DataHoraInicio']) ?> às <?= date('H:i', strtotime($ag['DataHoraInicio'])) ?></td>
                            <td class="small"><?= especieIconeHtml($ag['IconeEspecie']) ?> <?= h($ag['NomeAnimal']) ?></td>
                            <td class="small"><?= h($ag['Titulo']) ?></td>
                            <td><?= labelStatusAgendamento($ag['Status']) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card h-100" id="vacinas">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 px-4 py-3">
                <span><i class="bi bi-calendar-check me-2 text-accent"></i>Vacinas a vencer</span>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="<?= BASE ?>/painel/index.php#vacinas" class="btn <?= $filtroVac === '' ? 'btn-accent' : 'btn-outline-accent' ?>">Todas</a>
                    <a href="<?= BASE ?>/painel/index.php?vac=atrasadas#vacinas" class="btn <?= $filtroVac === 'atrasadas' ? 'btn-accent' : 'btn-outline-accent' ?>">Atrasadas</a>
                    <a href="<?= BASE ?>/painel/index.php?vac=vencendo#vacinas" class="btn <?= $filtroVac === 'vencendo' ? 'btn-accent' : 'btn-outline-accent' ?>">Vencendo</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($proximas)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-25"></i>
                        <p class="mb-0">Nenhuma vacina <?= $filtroVac ? 'nessa situação.' : 'agendada.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background:var(--bg-hover);">
                                <tr>
                                    <th class="px-4 py-3">Animal</th>
                                    <th class="d-none d-md-table-cell">Cliente</th>
                                    <th>Vacina</th>
                                    <th>Data</th>
                                    <th>Situação</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proximas as $p): ?>
                                    <tr>
                                        <td class="px-4 fw-medium">
                                            <a href="<?= BASE ?>/painel/animal_detalhe.php?id=<?= h($p['IDAnimal']) ?>"><?= h($p['NomeAnimal']) ?></a>
                                        </td>
                                        <td class="d-none d-md-table-cell"><?= h($p['NomeDono']) ?></td>
                                        <td class="small"><?= h($p['NomeVacina']) ?></td>
                                        <td class="small"><?= formatarData($p['ProximaData']) ?></td>
                                        <td><?= labelSituacaoVacina($p['ProximaData']) ?></td>
                                        <td>
                                            <?php if ($p['Telefone']): ?>
                                                <a href="<?= h(waLink($p['Telefone'])) ?>" target="_blank" class="btn btn-sm btn-outline-success" title="WhatsApp">
                                                    <i class="bi bi-whatsapp"></i>
                                                </a>
                                            <?php endif ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <?php $souAdmin = ($_SESSION['nivel_acesso'] ?? '') === 'admin'; ?>
    <div class="col-lg-4">
        <div class="card p-4 mb-4">
            <h6 class="fw-semibold mb-3"><i class="bi bi-lightning me-2 text-accent"></i>Ações rápidas</h6>
            <div class="d-grid gap-2">
                <?php if ($souAdmin): ?>
                    <a href="<?= BASE ?>/painel/agenda.php?acao=novo" class="btn btn-accent">
                        <i class="bi bi-calendar-plus me-2"></i>Novo agendamento
                    </a>
                    <a href="<?= BASE ?>/painel/registrar_vacina.php" class="btn btn-outline-accent">
                        <i class="bi bi-shield-plus me-2"></i>Registrar vacina
                    </a>
                    <a href="<?= BASE ?>/painel/animais.php?acao=novo" class="btn btn-outline-accent">
                        <i class="bi bi-clipboard2-plus me-2"></i>Cadastrar animal
                    </a>
                    <a href="<?= BASE ?>/painel/clientes.php?acao=novo" class="btn btn-outline-accent">
                        <i class="bi bi-person-plus me-2"></i>Cadastrar cliente
                    </a>
                <?php else: ?>
                    <a href="<?= BASE ?>/painel/agenda.php" class="btn btn-outline-accent">
                        <i class="bi bi-calendar3 me-2"></i>Ver agenda
                    </a>
                    <a href="<?= BASE ?>/painel/clientes.php" class="btn btn-outline-accent">
                        <i class="bi bi-people me-2"></i>Ver clientes
                    </a>
                <?php endif ?>
                <a href="<?= BASE ?>/painel/animais.php" class="btn btn-outline-secondary d-md-none">
                    <i class="bi bi-clipboard2-pulse me-2"></i>Ver todos os animais
                </a>
            </div>
        </div>

        <?php if (!empty($recentes)): ?>
        <div class="card p-4">
            <h6 class="fw-semibold mb-3"><i class="bi bi-clock-history me-2 text-accent"></i>Cadastros recentes</h6>
            <div class="d-flex flex-column gap-1">
                <?php foreach ($recentes as $r):
                    $linkRecente = $r['Tipo'] === 'cliente'
                        ? BASE . '/painel/cliente_detalhe.php?id=' . $r['ID']
                        : BASE . '/painel/animal_detalhe.php?id=' . $r['ID'];
                ?>
                    <a href="<?= h($linkRecente) ?>" class="recente-item d-flex align-items-center gap-2 text-decoration-none">
                        <i class="bi <?= $r['Tipo'] === 'cliente' ? 'bi-person' : 'bi-clipboard2-pulse' ?> text-accent"></i>
                        <span class="small text-truncate flex-grow-1"><?= h($r['Nome']) ?></span>
                        <span class="small text-secondary text-nowrap"><?= formatarData($r['MomentoRegistro']) ?></span>
                    </a>
                <?php endforeach ?>
            </div>
        </div>
        <?php endif ?>
    </div>
</div>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
