<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin');

$busca   = trim($_GET['q'] ?? '');
$especieF = trim($_GET['especie'] ?? '');
$pag     = max(1, (int) ($_GET['pag'] ?? 1));
$por     = 24;
$off     = ($pag - 1) * $por;

try {
    $where  = 'WHERE a.Ativo = 1';
    $params = [];
    if ($busca !== '') {
        $where .= ' AND (a.Nome LIKE :q OR u.Nome LIKE :q OR a.Raca LIKE :q)';
        $params[':q'] = '%' . $busca . '%';
    }
    if ($especieF !== '') {
        $where .= ' AND a.FKEspecie = :esp';
        $params[':esp'] = $especieF;
    }

    $cntStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM Animais a JOIN Usuarios u ON u.IDUsuario = a.FKDono {$where}"
    );
    $cntStmt->execute($params);
    $total = (int) $cntStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT a.*, e.Nome AS NomeEspecie, e.Icone AS IconeEspecie, u.Nome AS NomeDono,
                (SELECT MIN(rv.ProximaData) FROM RegistrosVacinas rv
                  WHERE rv.FKAnimal = a.IDAnimal AND rv.ProximaData IS NOT NULL) AS ProximaVacina
         FROM Animais a
         JOIN Especies e  ON e.IDEspecie = a.FKEspecie
         JOIN Usuarios u  ON u.IDUsuario = a.FKDono
         {$where}
         ORDER BY a.Nome ASC
         LIMIT :lim OFFSET :off"
    );
    $params[':lim'] = $por;
    $params[':off'] = $off;
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, in_array($k, [':lim', ':off']) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $animais = $stmt->fetchAll();

    $especies = $pdo->query('SELECT * FROM Especies ORDER BY Nome ASC')->fetchAll();
} catch (PDOException $e) {
    error_log('[Animais] ' . $e->getMessage());
    $animais  = [];
    $especies = [];
    $total    = 0;
}

$totalPag = max(1, (int) ceil($total / $por));

$paginaTitulo = 'Animais';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h4 class="fw-bold mb-0">Animais <span class="text-secondary small">(<?= number_format($total) ?>)</span></h4>
    <a href="<?= BASE ?>/painel/registrar_vacina.php" class="btn btn-accent btn-sm">
        <i class="bi bi-shield-plus me-1"></i> Registrar vacina
    </a>
</div>

<form class="row g-2 mb-4" method="GET">
    <div class="col-sm-7">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control" placeholder="Buscar por nome do animal, dono ou raça..."
                value="<?= h($busca) ?>">
        </div>
    </div>
    <div class="col-sm-3">
        <select name="especie" class="form-select" onchange="this.form.submit()">
            <option value="">Todas as espécies</option>
            <?php foreach ($especies as $e): ?>
                <option value="<?= h($e['IDEspecie']) ?>" <?= $especieF === $e['IDEspecie'] ? 'selected' : '' ?>>
                    <?= h($e['Icone']) ?> <?= h($e['Nome']) ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-sm-2 d-grid">
        <button class="btn btn-accent" type="submit">Buscar</button>
    </div>
</form>

<?php if (empty($animais)): ?>
    <div class="card text-center py-5 text-secondary">
        <i class="bi bi-emoji-smile fs-1 d-block mb-2 opacity-25"></i>
        <p class="mb-0">Nenhum animal encontrado.</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($animais as $a): ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <a href="<?= BASE ?>/painel/animal_detalhe.php?id=<?= h($a['IDAnimal']) ?>" class="text-decoration-none">
                    <div class="card p-3 h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span style="font-size:1.6rem;"><?= h($a['IconeEspecie']) ?></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-bold text-truncate" style="color:var(--text-main);"><?= h($a['Nome']) ?></div>
                                <div class="small text-secondary text-truncate"><?= h($a['NomeDono']) ?></div>
                            </div>
                        </div>
                        <?php if ($a['ProximaVacina']): ?>
                            <?= labelSituacaoVacina($a['ProximaVacina']) ?>
                        <?php else: ?>
                            <span class="badge bg-secondary">Sem registro</span>
                        <?php endif ?>
                    </div>
                </a>
            </div>
        <?php endforeach ?>
    </div>

    <?php if ($totalPag > 1): ?>
        <div class="d-flex justify-content-center py-4">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $totalPag; $p++): ?>
                        <li class="page-item <?= $p === $pag ? 'active' : '' ?>">
                            <a class="page-link" href="?pag=<?= $p ?>&q=<?= urlencode($busca) ?>&especie=<?= urlencode($especieF) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor ?>
                </ul>
            </nav>
        </div>
    <?php endif ?>
<?php endif ?>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
