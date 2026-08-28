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

try {
    $stmt = $pdo->prepare(
        "SELECT ag.*, a.Nome AS NomeAnimal, e.Icone AS IconeEspecie, v.Nome AS NomeVeterinario
         FROM Agendamentos ag
         JOIN Animais a  ON a.IDAnimal = ag.FKAnimal
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         LEFT JOIN Usuarios v ON v.IDUsuario = ag.FKVeterinario
         WHERE a.FKDono = :id AND ag.Status != 'cancelado'
         ORDER BY ag.DataHoraInicio ASC"
    );
    $stmt->execute([':id' => $uid]);
    $todos = $stmt->fetchAll();

    $hoje = date('Y-m-d H:i:s');
    $proximos = array_values(array_filter($todos, fn($a) => $a['DataHoraInicio'] >= $hoje));
    $anteriores = array_reverse(array_values(array_filter($todos, fn($a) => $a['DataHoraInicio'] < $hoje)));
} catch (PDOException $e) {
    error_log('[MeusAgendamentos] ' . $e->getMessage());
    $proximos = $anteriores = [];
}

function renderCardAgendamento(array $ag, array $tiposAgenda): void
{
    ?>
    <div class="card p-3 mb-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="text-center" style="min-width:64px;">
                    <div class="fw-bold small"><?= formatarData($ag['DataHoraInicio']) ?></div>
                    <div class="small text-secondary"><?= date('H:i', strtotime($ag['DataHoraInicio'])) ?></div>
                </div>
                <div>
                    <div>
                        <span class="badge" style="background:var(--accent-light);color:var(--accent);"><?= h($tiposAgenda[$ag['Tipo']] ?? $ag['Tipo']) ?></span>
                        <?= labelStatusAgendamento($ag['Status']) ?>
                    </div>
                    <div class="fw-medium mt-1"><?= h($ag['IconeEspecie']) ?> <?= h($ag['NomeAnimal']) ?> — <?= h($ag['Titulo']) ?></div>
                    <?php if ($ag['NomeVeterinario']): ?>
                        <div class="small text-secondary">Com <?= h($ag['NomeVeterinario']) ?></div>
                    <?php endif ?>
                </div>
            </div>
        </div>
        <?php if ($ag['Status'] === 'concluido' && $ag['ObservacoesPos']): ?>
            <div class="small mt-2 pt-2 border-top" style="border-color:var(--card-border-color) !important;">
                <strong>Observações:</strong> <?= nl2br(h($ag['ObservacoesPos'])) ?>
            </div>
        <?php endif ?>
    </div>
    <?php
}

$paginaTitulo = 'Meus Agendamentos';
$areaAtual    = 'cliente';
require_once __DIR__ . '/../geral/header.php';
?>

<h4 class="fw-bold mb-4"><i class="bi bi-calendar3 me-2 text-accent"></i>Meus Agendamentos</h4>

<?php if (empty($proximos) && empty($anteriores)): ?>
    <div class="card text-center py-5 text-secondary">
        <i class="bi bi-calendar3 fs-1 d-block mb-2 opacity-25"></i>
        <p class="mb-0">Nenhum agendamento ainda.</p>
        <p class="small">Entre em contato com a clínica para agendar.</p>
    </div>
<?php else: ?>
    <h6 class="fw-semibold text-secondary mb-2">Próximos</h6>
    <?php if (empty($proximos)): ?>
        <p class="text-secondary small mb-4">Nenhum agendamento futuro.</p>
    <?php else: ?>
        <div class="mb-4"><?php foreach ($proximos as $ag) renderCardAgendamento($ag, $tiposAgenda) ?></div>
    <?php endif ?>

    <?php if (!empty($anteriores)): ?>
        <h6 class="fw-semibold text-secondary mb-2">Anteriores</h6>
        <div><?php foreach ($anteriores as $ag) renderCardAgendamento($ag, $tiposAgenda) ?></div>
    <?php endif ?>
<?php endif ?>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
