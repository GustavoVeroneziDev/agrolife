<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('cliente');

$uid = $_SESSION['usuario_id'];

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
} catch (PDOException $e) {
    error_log('[MeusAnimais] ' . $e->getMessage());
    $animais = [];
}

$paginaTitulo = 'Meus Animais';
$areaAtual    = 'cliente';
require_once __DIR__ . '/../geral/header.php';
?>

<h4 class="fw-bold mb-4"><i class="bi bi-clipboard2-pulse me-2 text-accent"></i>Meus Animais</h4>

<?php if (empty($animais)): ?>
    <div class="card text-center py-5 text-secondary">
        <i class="bi bi-emoji-smile fs-1 d-block mb-2 opacity-25"></i>
        <p class="mb-0">Nenhum animal cadastrado ainda.</p>
        <p class="small">Entre em contato com a clínica para cadastrar seu animal.</p>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($animais as $a): ?>
            <div class="col-sm-6 col-lg-4">
                <a href="<?= BASE ?>/usuario/animal_vacinas.php?id=<?= h($a['IDAnimal']) ?>" class="text-decoration-none">
                    <div class="card p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="avatar-circle" style="background:var(--accent-light);color:var(--accent);font-size:1.8rem;">
                                <?= h($a['IconeEspecie'] ?: '🐾') ?>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-bold text-truncate" style="color:var(--text-main);"><?= h($a['Nome']) ?></div>
                                <div class="small text-secondary"><?= h($a['NomeEspecie']) ?><?= $a['Raca'] ? ' · ' . h($a['Raca']) : '' ?></div>
                            </div>
                        </div>
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
