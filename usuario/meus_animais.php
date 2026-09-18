<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('cliente');

$uid = $_SESSION['usuario_id'];

$tiposAgenda = [
    'cirurgia' => 'Cirurgia', 'consulta' => 'Consulta', 'exame' => 'Exame',
    'procedimento' => 'Procedimento', 'observacao' => 'Observação', 'outro' => 'Outro',
];

$agendamentosHoje = [];
$proximoPorAnimal = [];

try {
    $stmt = $pdo->prepare(
        'SELECT a.*, e.Nome AS NomeEspecie, e.Icone AS IconeEspecie,
                (SELECT MIN(rv.ProximaData) FROM RegistrosVacinas rv
                  WHERE rv.FKAnimal = a.IDAnimal AND rv.ProximaData IS NOT NULL) AS ProximaVacina
         FROM Animais a
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         WHERE a.FKDono = :id AND a.Ativo = 1
         ORDER BY a.Nome ASC'
    );
    $stmt->execute([':id' => $uid]);
    $animais = $stmt->fetchAll();

    // Agendamentos de todos os animais do cliente (menos cancelados) —
    // alimenta tanto o aviso de "hoje" quanto o próximo atendimento
    // mostrado em cada card, pra não deixar isso escondido só na tela de
    // Meus Agendamentos. Inclui concluído/faltou também — sem isso, um
    // agendamento que passou e virou "Faltou" simplesmente sumia do card
    // do animal como se nunca tivesse existido.
    $agStmt = $pdo->prepare(
        "SELECT ag.*, a.Nome AS NomeAnimal, e.Icone AS IconeEspecie, v.Nome AS NomeVeterinario
         FROM Agendamentos ag
         JOIN Animais a  ON a.IDAnimal = ag.FKAnimal
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         LEFT JOIN Usuarios v ON v.IDUsuario = ag.FKVeterinario
         WHERE a.FKDono = :id AND ag.Status != 'cancelado'
         ORDER BY ag.DataHoraInicio ASC"
    );
    $agStmt->execute([':id' => $uid]);
    $agendamentosAtivos = $agStmt->fetchAll();

    $hojeStr = date('Y-m-d');
    $agora   = date('Y-m-d H:i:s');

    $agendamentosHoje = array_values(array_filter(
        $agendamentosAtivos,
        fn($ag) => substr($ag['DataHoraInicio'], 0, 10) === $hojeStr
    ));

    // Próximo agendamento (ainda não iniciado) de cada animal — a lista já
    // vem ordenada por data, então o primeiro que aparece pra cada
    // FKAnimal já é o mais próximo. Se não tiver nenhum futuro, cai pro
    // mais recente já resolvido (concluído/faltou), pra sempre mostrar
    // alguma coisa em vez do card ficar vazio depois que o atendimento passa.
    $ultimoResolvidoPorAnimal = [];
    foreach ($agendamentosAtivos as $ag) {
        if ($ag['DataHoraInicio'] >= $agora) {
            if (!isset($proximoPorAnimal[$ag['FKAnimal']])) {
                $proximoPorAnimal[$ag['FKAnimal']] = $ag;
            }
        } else {
            $ultimoResolvidoPorAnimal[$ag['FKAnimal']] = $ag;
        }
    }
    foreach ($ultimoResolvidoPorAnimal as $idAnimal => $ag) {
        if (!isset($proximoPorAnimal[$idAnimal])) {
            $proximoPorAnimal[$idAnimal] = $ag;
        }
    }
    // Resumo rápido do topo — cruza informação que hoje só aparecia
    // espalhada em cada card de animal (ou nem aparecia, no caso do
    // "pedido aguardando confirmação"). $agendamentosAtivos já vem
    // ordenado por data, então o primeiro pendente/confirmado ainda no
    // futuro já é o "próximo" — sem precisar de outra consulta.
    $proximoGeral = null;
    foreach ($agendamentosAtivos as $ag) {
        if ($ag['DataHoraInicio'] >= $agora && in_array($ag['Status'], ['pendente', 'confirmado'], true)) {
            $proximoGeral = $ag;
            break;
        }
    }

    $pedidosPendentes = array_values(array_filter(
        $agendamentosAtivos,
        fn($ag) => $ag['Status'] === 'pendente'
    ));

    $vacinaMaisProxima = null;
    foreach ($animais as $a) {
        if ($a['ProximaVacina'] && (!$vacinaMaisProxima || $a['ProximaVacina'] < $vacinaMaisProxima['data'])) {
            $vacinaMaisProxima = ['data' => $a['ProximaVacina'], 'animal' => $a['Nome']];
        }
    }
} catch (PDOException $e) {
    error_log('[MeusAnimais] ' . $e->getMessage());
    $animais = [];
    $proximoGeral = $vacinaMaisProxima = null;
    $pedidosPendentes = [];
}

$paginaTitulo = 'Meus Animais';
$areaAtual    = 'cliente';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-clipboard2-pulse me-2 text-accent"></i>Meus Animais</h4>
    <a href="<?= BASE ?>/usuario/agendar.php" class="btn btn-accent btn-sm">
        <i class="bi bi-calendar-plus me-1"></i> Pedir agendamento
    </a>
</div>

<?php if (empty($animais)): ?>
    <div class="card text-center py-5 text-secondary">
        <i class="bi bi-emoji-smile fs-1 d-block mb-2 opacity-25"></i>
        <p class="mb-0">Nenhum animal cadastrado ainda.</p>
        <p class="small">Entre em contato com a clínica para cadastrar seu animal.</p>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 h-100">
                <div class="d-flex align-items-center gap-2 mb-2 text-secondary small">
                    <i class="bi bi-calendar-event text-accent"></i> Próximo agendamento
                </div>
                <?php if ($proximoGeral): ?>
                    <div class="fw-semibold"><?= especieIconeHtml($proximoGeral['IconeEspecie']) ?> <?= h($proximoGeral['NomeAnimal']) ?></div>
                    <div class="small text-secondary mb-2"><?= h($tiposAgenda[$proximoGeral['Tipo']] ?? $proximoGeral['Tipo']) ?> — <?= h($proximoGeral['Titulo']) ?></div>
                    <div class="small">
                        <i class="bi bi-clock me-1"></i>
                        <?= substr($proximoGeral['DataHoraInicio'], 0, 10) === date('Y-m-d') ? 'Hoje' : formatarData($proximoGeral['DataHoraInicio']) ?>
                        às <?= date('H:i', strtotime($proximoGeral['DataHoraInicio'])) ?>
                        <?= labelStatusAgendamento($proximoGeral['Status']) ?>
                    </div>
                <?php else: ?>
                    <p class="text-secondary small mb-0">Nenhum agendamento marcado.</p>
                <?php endif ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 h-100">
                <div class="d-flex align-items-center gap-2 mb-2 text-secondary small">
                    <i class="bi bi-hourglass-split" style="color:var(--cor-atencao);"></i> Aguardando confirmação
                </div>
                <?php if (!empty($pedidosPendentes)): ?>
                    <div class="fw-semibold mb-2"><?= count($pedidosPendentes) ?> pedido<?= count($pedidosPendentes) === 1 ? '' : 's' ?> em análise</div>
                    <?php foreach (array_slice($pedidosPendentes, 0, 2) as $p): ?>
                        <div class="small text-secondary">
                            <?= h($p['NomeAnimal']) ?> — <?= formatarData($p['DataHoraInicio']) ?> às <?= date('H:i', strtotime($p['DataHoraInicio'])) ?>
                        </div>
                    <?php endforeach ?>
                    <?php if (count($pedidosPendentes) > 2): ?>
                        <div class="small text-secondary">+<?= count($pedidosPendentes) - 2 ?> outro(s)</div>
                    <?php endif ?>
                <?php else: ?>
                    <p class="text-secondary small mb-0">Nenhum pedido pendente.</p>
                <?php endif ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 h-100">
                <div class="d-flex align-items-center gap-2 mb-2 text-secondary small">
                    <i class="bi bi-shield-plus text-accent"></i> Próxima vacina
                </div>
                <?php if ($vacinaMaisProxima): ?>
                    <div class="fw-semibold mb-1"><?= h($vacinaMaisProxima['animal']) ?></div>
                    <div class="small"><?= labelSituacaoVacina($vacinaMaisProxima['data']) ?> <span class="text-secondary"><?= formatarData($vacinaMaisProxima['data']) ?></span></div>
                <?php else: ?>
                    <p class="text-secondary small mb-0">Nenhuma vacina registrada.</p>
                <?php endif ?>
            </div>
        </div>
    </div>

    <?php if (!empty($agendamentosHoje)): ?>
        <h6 class="fw-semibold text-secondary mb-2"><i class="bi bi-calendar-event me-1"></i>Hoje</h6>
        <div class="mb-4">
            <?php foreach ($agendamentosHoje as $ag) renderCardAgendamento($ag, $tiposAgenda) ?>
        </div>
    <?php endif ?>

    <div class="row g-4">
        <?php foreach ($animais as $a): $prox = $proximoPorAnimal[$a['IDAnimal']] ?? null; ?>
            <div class="col-sm-6 col-lg-4">
                <a href="<?= BASE ?>/usuario/animal_vacinas.php?id=<?= h($a['IDAnimal']) ?>" class="text-decoration-none">
                    <div class="card p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="avatar-circle" style="background:var(--accent-light);">
                                <?= especieIconeHtml($a['IconeEspecie'], '1.8rem') ?>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-bold text-truncate" style="color:var(--text-main);"><?= h($a['Nome']) ?></div>
                                <div class="small text-secondary"><?= h($a['NomeEspecie']) ?><?= $a['Raca'] ? ' · ' . h($a['Raca']) : '' ?></div>
                            </div>
                        </div>
                        <?php if ($prox): ?>
                            <div class="d-flex align-items-center gap-1 flex-wrap mb-2">
                                <span class="badge" style="background:var(--accent-light);color:var(--accent);"><?= h($tiposAgenda[$prox['Tipo']] ?? $prox['Tipo']) ?></span>
                                <?= labelStatusAgendamento($prox['Status']) ?>
                            </div>
                            <div class="small fw-medium mb-2" style="color:var(--text-main);">
                                <i class="bi bi-calendar-event me-1 text-accent"></i>
                                <?= substr($prox['DataHoraInicio'], 0, 10) === date('Y-m-d') ? 'Hoje' : formatarData($prox['DataHoraInicio']) ?>
                                às <?= date('H:i', strtotime($prox['DataHoraInicio'])) ?>
                            </div>
                        <?php endif ?>
                        <?php if ($a['ProximaVacina']): ?>
                            <?= labelSituacaoVacina($a['ProximaVacina']) ?>
                            <span class="small text-secondary ms-1"><?= formatarData($a['ProximaVacina']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Sem vacinas registradas</span>
                        <?php endif ?>
                    </div>
                </a>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
