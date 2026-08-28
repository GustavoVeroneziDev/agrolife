<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin', 'veterinario');

$animalPreId = trim($_GET['animal'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/registrar_vacina.php', 'Token inválido.', 'danger');
    }

    $fkAnimal = trim($_POST['animal'] ?? '');
    $fkTipo   = trim($_POST['tipo']   ?? '');
    $dataAp   = trim($_POST['data_aplicacao'] ?? '');
    $proximaManual = trim($_POST['proxima_data'] ?? '');
    $vet      = trim($_POST['veterinario'] ?? '');
    $lote     = trim($_POST['lote'] ?? '');
    $obs      = trim($_POST['observacoes'] ?? '');

    if ($fkAnimal === '' || $fkTipo === '' || $dataAp === '') {
        redirecionarComMensagem(BASE . '/painel/registrar_vacina.php?animal=' . $fkAnimal, 'Animal, vacina e data de aplicação são obrigatórios.', 'warning');
    }

    try {
        $tipoStmt = $pdo->prepare('SELECT IntervaloMeses FROM TiposVacina WHERE IDTipo = :id LIMIT 1');
        $tipoStmt->execute([':id' => $fkTipo]);
        $tipo = $tipoStmt->fetch();

        $proximaData = null;
        if ($proximaManual !== '') {
            $proximaData = $proximaManual;
        } elseif ($tipo && $tipo['IntervaloMeses']) {
            $dt = new DateTimeImmutable($dataAp);
            $proximaData = $dt->modify('+' . (int) $tipo['IntervaloMeses'] . ' months')->format('Y-m-d');
        }

        $pdo->prepare(
            'INSERT INTO RegistrosVacinas (IDRegistro, FKAnimal, FKTipoVacina, DataAplicacao, ProximaData, FKVeterinario, Lote, Observacoes)
             VALUES (:id, :animal, :tipo, :data, :proxima, :vet, :lote, :obs)'
        )->execute([
            ':id'      => gerarUuid(),
            ':animal'  => $fkAnimal,
            ':tipo'    => $fkTipo,
            ':data'    => $dataAp,
            ':proxima' => $proximaData,
            ':vet'     => $vet ?: null,
            ':lote'    => $lote ?: null,
            ':obs'     => $obs ?: null,
        ]);

        redirecionarComMensagem(BASE . '/painel/animal_detalhe.php?id=' . $fkAnimal, 'Vacina registrada com sucesso!', 'success');
    } catch (PDOException $e) {
        error_log('[RegistrarVacina] ' . $e->getMessage());
        redirecionarComMensagem(BASE . '/painel/registrar_vacina.php?animal=' . $fkAnimal, 'Erro ao registrar vacina.', 'danger');
    }
}

try {
    $animais = $pdo->query(
        "SELECT a.IDAnimal, a.Nome, a.FKEspecie, u.Nome AS NomeDono, e.Icone AS IconeEspecie
         FROM Animais a
         JOIN Usuarios u ON u.IDUsuario = a.FKDono
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         WHERE a.Ativo = 1 ORDER BY a.Nome ASC"
    )->fetchAll();

    $tipos = $pdo->query(
        "SELECT IDTipo, Nome, IntervaloMeses, FKEspecie FROM TiposVacina WHERE Ativo = 1 ORDER BY Nome ASC"
    )->fetchAll();

    $vets = $pdo->query(
        "SELECT IDUsuario, Nome FROM Usuarios WHERE NivelAcesso = 'veterinario' AND Ativo = 1 ORDER BY Nome ASC"
    )->fetchAll();

    $animalPre = null;
    if ($animalPreId) {
        $stmt = $pdo->prepare(
            'SELECT a.IDAnimal, a.Nome, a.FKEspecie, u.Nome AS NomeDono
             FROM Animais a JOIN Usuarios u ON u.IDUsuario = a.FKDono
             WHERE a.IDAnimal = :id AND a.Ativo = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $animalPreId]);
        $animalPre = $stmt->fetch();
    }
} catch (PDOException $e) {
    error_log('[RegistrarVacinaForm] ' . $e->getMessage());
    $animais = $tipos = $vets = [];
    $animalPre = null;
}

$paginaTitulo = 'Registrar Vacina';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= BASE ?>/painel/animais.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0"><i class="bi bi-shield-plus me-2 text-accent"></i>Registrar Vacina</h4>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card p-4">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">

                <div class="mb-3">
                    <label class="form-label">Animal *</label>
                    <input type="hidden" name="animal" id="inpAnimalId" required value="<?= $animalPre ? h($animalPre['IDAnimal']) : '' ?>">
                    <div class="picker" id="animalPicker">
                        <div class="picker-trigger" id="animalTrigger" tabindex="0">
                            <span id="animalLabel" class="<?= $animalPre ? 'picker-selected' : 'picker-placeholder' ?>">
                                <?= $animalPre ? h($animalPre['Nome']) . ' — ' . h($animalPre['NomeDono']) : 'Buscar animal ou dono…' ?>
                            </span>
                            <span class="picker-caret"><i class="bi bi-chevron-down"></i></span>
                        </div>
                        <div class="picker-dropdown d-none" id="animalDropdown">
                            <div class="picker-search-wrap">
                                <i class="bi bi-search picker-search-icon"></i>
                                <input type="text" class="picker-search" id="animalSearch" placeholder="Nome do animal ou do dono…" autocomplete="off">
                            </div>
                            <div class="picker-list" id="animalList"></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Vacina *</label>
                    <select name="tipo" id="selTipo" class="form-select" required>
                        <option value="">Selecione a vacina</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= h($t['IDTipo']) ?>" data-especie="<?= h($t['FKEspecie'] ?? '') ?>">
                                <?= h($t['Nome']) ?><?= $t['IntervaloMeses'] ? ' (reforço em ' . $t['IntervaloMeses'] . ' meses)' : ' (dose única)' ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Data de aplicação *</label>
                        <input type="date" name="data_aplicacao" class="form-control" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Próxima dose <span class="text-secondary">(opcional)</span></label>
                        <input type="date" name="proxima_data" class="form-control">
                        <div class="form-text">Deixe em branco para calcular automaticamente pelo intervalo da vacina.</div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Veterinário</label>
                        <?= campoPicker('vetResp', 'veterinario', 'Selecione…', 'Buscar veterinário…') ?>
                        <?php if (empty($vets)): ?>
                            <div class="form-text">Nenhum veterinário cadastrado — <a href="<?= BASE ?>/painel/equipe.php">cadastre um primeiro</a>.</div>
                        <?php endif ?>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Lote</label>
                        <input type="text" name="lote" class="form-control">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Observações</label>
                    <textarea name="observacoes" class="form-control" rows="2"></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-accent btn-lg">
                        <i class="bi bi-check2 me-2"></i> Registrar vacina
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var ANIMAIS = <?= json_encode(array_map(fn($a) => [
    'id' => $a['IDAnimal'], 'nome' => $a['Nome'], 'dono' => $a['NomeDono'],
    'especie' => $a['FKEspecie'], 'icone' => $a['IconeEspecie'],
], $animais), JSON_UNESCAPED_UNICODE) ?>;
var VETS = <?= json_encode(array_map(fn($v) => [
    'id' => $v['IDUsuario'], 'nome' => $v['Nome'],
], $vets), JSON_UNESCAPED_UNICODE) ?>;

// Filtra o select de vacinas pela espécie do animal escolhido
var selTipo    = document.getElementById('selTipo');
var opcoesTipo = Array.from(selTipo.options);

function filtrarVacinasPorEspecie(especie) {
    opcoesTipo.forEach(function (o) {
        if (!o.value) return;
        var esp = o.dataset.especie;
        o.hidden = !!(especie && esp && esp !== especie);
    });
    if (selTipo.selectedOptions[0] && selTipo.selectedOptions[0].hidden) selTipo.value = '';
}

var animalPicker = initPicker({
    pickerId: 'animalPicker', triggerId: 'animalTrigger', dropdownId: 'animalDropdown',
    searchId: 'animalSearch', listId: 'animalList', hiddenId: 'inpAnimalId', labelId: 'animalLabel',
    items: ANIMAIS,
    chave: function (a) { return a.id; },
    renderItem: function (a) { return { title: a.nome, icon: a.icone, sub: a.dono }; },
    matches: function (a, q) {
        return a.nome.toLowerCase().indexOf(q) !== -1 || a.dono.toLowerCase().indexOf(q) !== -1;
    },
    vazioMsg: 'Nenhum animal encontrado.',
    onSelect: function (a) { filtrarVacinasPorEspecie(a.especie); },
});

<?php if ($animalPre): ?>
    filtrarVacinasPorEspecie(<?= json_encode($animalPre['FKEspecie']) ?>);
<?php endif ?>

initPicker({
    pickerId: 'vetRespPicker', triggerId: 'vetRespTrigger', dropdownId: 'vetRespDropdown',
    searchId: 'vetRespSearch', listId: 'vetRespList', hiddenId: 'inpvetRespId', labelId: 'vetRespLabel',
    items: VETS,
    chave: function (v) { return v.id; },
    renderItem: function (v) { return { title: v.nome }; },
    matches: function (v, q) { return v.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nenhum veterinário encontrado.',
});
</script>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
