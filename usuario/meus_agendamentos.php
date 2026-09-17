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
         WHERE a.FKDono = :id
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

$paginaTitulo = 'Meus Agendamentos';
$areaAtual    = 'cliente';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-calendar3 me-2 text-accent"></i>Meus Agendamentos</h4>
    <a href="<?= BASE ?>/usuario/agendar.php" class="btn btn-accent btn-sm">
        <i class="bi bi-calendar-plus me-1"></i> Pedir agendamento
    </a>
</div>

<?php if (empty($proximos) && empty($anteriores)): ?>
    <div class="card text-center py-5 text-secondary">
        <i class="bi bi-calendar3 fs-1 d-block mb-2 opacity-25"></i>
        <p class="mb-3">Nenhum agendamento ainda.</p>
        <div>
            <a href="<?= BASE ?>/usuario/agendar.php" class="btn btn-accent">
                <i class="bi bi-calendar-plus me-1"></i> Pedir agendamento
            </a>
        </div>
    </div>
<?php else: ?>
    <h6 class="fw-semibold text-secondary mb-2">Próximos</h6>
    <?php if (empty($proximos)): ?>
        <p class="text-secondary small mb-4">Nenhum agendamento futuro.</p>
    <?php else: ?>
        <div class="mb-4"><?php foreach ($proximos as $ag) renderCardAgendamento($ag, $tiposAgenda, permitirCancelar: true) ?></div>
    <?php endif ?>

    <?php if (!empty($anteriores)): ?>
        <h6 class="fw-semibold text-secondary mb-2">Anteriores</h6>
        <div><?php foreach ($anteriores as $ag) renderCardAgendamento($ag, $tiposAgenda) ?></div>
    <?php endif ?>
<?php endif ?>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
