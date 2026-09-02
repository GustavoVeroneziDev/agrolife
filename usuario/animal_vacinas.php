<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('cliente');

$uid = $_SESSION['usuario_id'];
$id  = trim($_GET['id'] ?? '');
if (!$id) {
    redirecionarComMensagem(BASE . '/usuario/meus_animais.php', 'Animal não encontrado.', 'warning');
}

try {
    $stmt = $pdo->prepare(
        'SELECT a.*, e.Nome AS NomeEspecie, e.Icone AS IconeEspecie
         FROM Animais a
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         WHERE a.IDAnimal = :id AND a.FKDono = :uid
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':uid' => $uid]);
    $animal = $stmt->fetch();
    if (!$animal) {
        redirecionarComMensagem(BASE . '/usuario/meus_animais.php', 'Animal não encontrado.', 'warning');
    }

    $historico = $pdo->prepare(
        'SELECT rv.*, tv.Nome AS NomeVacina
         FROM RegistrosVacinas rv
         JOIN TiposVacina tv ON tv.IDTipo = rv.FKTipoVacina
         WHERE rv.FKAnimal = :id
         ORDER BY COALESCE(rv.DataAplicacao, rv.ProximaData) DESC'
    );
    $historico->execute([':id' => $id]);
    $historico = $historico->fetchAll();
} catch (PDOException $e) {
    error_log('[AnimalVacinas] ' . $e->getMessage());
    $historico = [];
}

$paginaTitulo = h($animal['Nome']);
$areaAtual    = 'cliente';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= BASE ?>/usuario/meus_animais.php" onclick="voltarInteligente(event)" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0"><?= h($animal['Nome']) ?></h4>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card p-4 text-center">
            <div><?= especieIconeHtml($animal['IconeEspecie'], '3.5rem') ?></div>
            <h5 class="fw-bold mt-2 mb-0"><?= h($animal['Nome']) ?></h5>
            <p class="text-secondary small mb-3"><?= h($animal['NomeEspecie']) ?><?= $animal['Raca'] ? ' · ' . h($animal['Raca']) : '' ?></p>

            <dl class="mb-0 text-start">
                <?php if ($animal['DataNascimento']): ?>
                    <dt class="small text-secondary">Idade</dt>
                    <dd><?= h(formatarIdade($animal['DataNascimento'])) ?></dd>
                <?php endif ?>
                <?php if ($animal['Sexo']): ?>
                    <dt class="small text-secondary">Sexo</dt>
                    <dd><?= formatarSexo($animal['Sexo']) ?></dd>
                <?php endif ?>
                <?php if ($animal['PesoKg']): ?>
                    <dt class="small text-secondary">Peso</dt>
                    <dd><?= h(number_format((float) $animal['PesoKg'], 3, ',', '.')) ?> kg</dd>
                <?php endif ?>
            </dl>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header px-4 py-3">
                <i class="bi bi-clipboard2-pulse me-2 text-accent"></i>Carteirinha de vacinação
            </div>
            <div class="card-body p-0">
                <?php if (empty($historico)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-shield-plus fs-1 d-block mb-2 opacity-25"></i>
                        <p class="mb-0">Nenhuma vacina registrada ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background:var(--bg-hover);">
                                <tr>
                                    <th class="px-4 py-3">Vacina</th>
                                    <th>Aplicada em</th>
                                    <th>Próxima dose</th>
                                    <th>Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historico as $reg): ?>
                                    <tr>
                                        <td class="px-4 fw-medium"><?= h($reg['NomeVacina']) ?></td>
                                        <td><?= labelAplicacaoVacina($reg['DataAplicacao'], $reg['ProximaData']) ?></td>
                                        <td><?= $reg['ProximaData'] ? formatarData($reg['ProximaData']) : '—' ?></td>
                                        <td><?= labelSituacaoVacina($reg['ProximaData']) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
