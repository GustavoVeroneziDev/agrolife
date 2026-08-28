<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin', 'veterinario');

try {
    $totalAnimais = (int) $pdo->query("SELECT COUNT(*) FROM Animais WHERE Ativo = 1")->fetchColumn();
    $totalDonos   = (int) $pdo->query("SELECT COUNT(*) FROM Usuarios WHERE NivelAcesso = 'cliente' AND Ativo = 1")->fetchColumn();

    $vencendo = (int) $pdo->query(
        "SELECT COUNT(*) FROM RegistrosVacinas rv
         JOIN Animais a ON a.IDAnimal = rv.FKAnimal
         WHERE rv.ProximaData BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
           AND a.Ativo = 1"
    )->fetchColumn();

    $atrasadas = (int) $pdo->query(
        "SELECT COUNT(*) FROM RegistrosVacinas rv
         JOIN Animais a ON a.IDAnimal = rv.FKAnimal
         WHERE rv.ProximaData < CURDATE() AND a.Ativo = 1"
    )->fetchColumn();

    $proximas = $pdo->query(
        "SELECT rv.IDRegistro, rv.ProximaData, a.IDAnimal, a.Nome AS NomeAnimal,
                u.Nome AS NomeDono, u.Telefone, tv.Nome AS NomeVacina
         FROM RegistrosVacinas rv
         JOIN Animais a   ON a.IDAnimal   = rv.FKAnimal
         JOIN Usuarios u  ON u.IDUsuario  = a.FKDono
         JOIN TiposVacina tv ON tv.IDTipo = rv.FKTipoVacina
         WHERE rv.ProximaData IS NOT NULL AND a.Ativo = 1
         ORDER BY rv.ProximaData ASC
         LIMIT 10"
    )->fetchAll();

    // Lembrete do próprio veterinário: só os agendamentos dele. Admin vê tudo.
    $whereAg  = "WHERE ag.Status IN ('pendente','confirmado') AND ag.DataHoraInicio >= NOW()";
    $paramsAg = [];
    if (($_SESSION['nivel_acesso'] ?? '') === 'veterinario') {
        $whereAg .= ' AND ag.FKVeterinario = :uid';
        $paramsAg[':uid'] = $_SESSION['usuario_id'];
    }
    $stmtAg = $pdo->prepare(
        "SELECT ag.IDAgendamento, ag.Tipo, ag.Titulo, ag.DataHoraInicio, a.Nome AS NomeAnimal, e.Icone AS IconeEspecie
         FROM Agendamentos ag
         JOIN Animais a  ON a.IDAnimal = ag.FKAnimal
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         {$whereAg}
         ORDER BY ag.DataHoraInicio ASC
         LIMIT 6"
    );
    $stmtAg->execute($paramsAg);
    $proximosAgendamentos = $stmtAg->fetchAll();
} catch (PDOException $e) {
    error_log('[PainelDash] ' . $e->getMessage());
    $totalAnimais = $totalDonos = $vencendo = $atrasadas = 0;
    $proximas = [];
    $proximosAgendamentos = [];
}

$paginaTitulo = 'Dashboard';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<h4 class="fw-bold mb-4">Dashboard</h4>

<div class="row g-3 mb-4">
    <?php
    $stats = [
        ['bi-exclamation-triangle-fill', 'var(--cor-perigo)',  'var(--cor-perigo-bg)',  'Vacinas atrasadas', $atrasadas, ''],
        ['bi-clock-fill',                'var(--cor-atencao)', 'var(--cor-atencao-bg)', 'Vencendo em 30 dias', $vencendo, ''],
        ['bi-clipboard2-pulse',          'var(--accent)',      'var(--accent-light)',   'Animais ativos', $totalAnimais, ''],
        ['bi-people',                    'var(--cor-info)',    'var(--cor-info-bg)',    'Donos cadastrados', $totalDonos, ''],
    ];
    foreach ($stats as [$icon, $color, $bg, $label, $valor, $sub]):
    ?>
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="stat-card-top">
                    <span class="stat-card-label"><?= $label ?></span>
                    <div class="stat-icon" style="background:<?= $bg ?>;color:<?= $color ?>">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                </div>
                <div class="stat-card-valor"><?= number_format($valor) ?></div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?php if (!empty($proximosAgendamentos)): ?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between px-4 py-3">
        <span><i class="bi bi-calendar3 me-2 text-accent"></i><?= ($_SESSION['nivel_acesso'] ?? '') === 'veterinario' ? 'Seus próximos agendamentos' : 'Próximos agendamentos' ?></span>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proximosAgendamentos as $ag): ?>
                        <tr>
                            <td class="px-4 small fw-medium"><?= formatarData($ag['DataHoraInicio']) ?> às <?= date('H:i', strtotime($ag['DataHoraInicio'])) ?></td>
                            <td class="small"><?= especieIconeHtml($ag['IconeEspecie']) ?> <?= h($ag['NomeAnimal']) ?></td>
                            <td class="small"><?= h($ag['Titulo']) ?></td>
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
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between px-4 py-3">
                <span><i class="bi bi-calendar-check me-2 text-accent"></i>Próximas vacinas</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($proximas)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-check-circle fs-1 d-block mb-2 opacity-25"></i>
                        <p class="mb-0">Nenhuma vacina agendada.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background:var(--bg-hover);">
                                <tr>
                                    <th class="px-4 py-3">Animal</th>
                                    <th class="d-none d-md-table-cell">Dono</th>
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

    <div class="col-lg-4">
        <div class="card p-4">
            <h6 class="fw-semibold mb-3"><i class="bi bi-lightning me-2 text-accent"></i>Ações rápidas</h6>
            <div class="d-grid gap-2">
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
                    <i class="bi bi-person-plus me-2"></i>Cadastrar dono
                </a>
                <a href="<?= BASE ?>/painel/animais.php" class="btn btn-outline-secondary d-md-none">
                    <i class="bi bi-clipboard2-pulse me-2"></i>Ver todos os animais
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
