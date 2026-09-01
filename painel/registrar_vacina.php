<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin');

$animalPreId = trim($_GET['animal'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/registrar_vacina.php?animal=' . $animalPreId, 'Token inválido.', 'danger');
    }

    $fkAnimal = trim($_POST['animal'] ?? '');
    $fkTipo   = trim($_POST['tipo']   ?? '');
    $dataAp   = trim($_POST['data_aplicacao'] ?? '');
    $proximaManual = trim($_POST['proxima_data'] ?? '');
    $ciclica  = !empty($_POST['ciclica']);
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

        // Cíclica só faz sentido se a vacina tem intervalo de reforço — sem
        // isso não tem por quanto tempo avançar a data sozinha.
        $ciclica = $ciclica && $tipo && $tipo['IntervaloMeses'] && $proximaData;

        $pdo->prepare(
            'INSERT INTO RegistrosVacinas (IDRegistro, FKAnimal, FKTipoVacina, DataAplicacao, ProximaData, Ciclica, FKVeterinario, Lote, Observacoes)
             VALUES (:id, :animal, :tipo, :data, :proxima, :ciclica, :vet, :lote, :obs)'
        )->execute([
            ':id'      => gerarUuid(),
            ':animal'  => $fkAnimal,
            ':tipo'    => $fkTipo,
            ':data'    => $dataAp,
            ':proxima' => $proximaData,
            ':ciclica' => $ciclica ? 1 : 0,
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
        "SELECT IDUsuario, Nome FROM Usuarios WHERE Cargo = 'veterinario' AND Ativo = 1 ORDER BY Nome ASC"
    )->fetchAll();

    // $animais já carrega todo mundo ativo (Nome/NomeDono/FKEspecie inclusos)
    // — acha o pré-selecionado ali em vez de rodar a mesma consulta de novo.
    $animalPre = null;
    if ($animalPreId) {
        foreach ($animais as $a) {
            if ($a['IDAnimal'] === $animalPreId) {
                $animalPre = $a;
                break;
            }
        }
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
    <a href="<?= BASE ?>/painel/animais.php" onclick="voltarInteligente(event)" class="btn btn-sm btn-outline-secondary">
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
                                <?= $animalPre ? h($animalPre['Nome']) . ' — ' . h($animalPre['NomeDono']) : 'Buscar animal ou cliente…' ?>
                            </span>
                            <span class="picker-caret"><i class="bi bi-chevron-down"></i></span>
                        </div>
                        <div class="picker-dropdown d-none" id="animalDropdown">
                            <div class="picker-search-wrap">
                                <i class="bi bi-search picker-search-icon"></i>
                                <input type="text" class="picker-search" id="animalSearch" placeholder="Nome do animal ou do cliente…" autocomplete="off">
                            </div>
                            <div class="picker-list" id="animalList"></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Vacina *</label>
                    <?= campoPicker('rvTipo', 'tipo', 'Selecione a vacina', '', obrigatorio: true, comBusca: false) ?>
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

                <div class="form-check mb-3" id="ciclicaWrap" style="display:none;">
                    <input class="form-check-input" type="checkbox" name="ciclica" id="rvCiclica" value="1">
                    <label class="form-check-label" for="rvCiclica">
                        Repetir automaticamente <span class="text-secondary" id="rvCiclicaTexto"></span>
                    </label>
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

var TIPOS_VACINA = <?= json_encode(array_map(fn($t) => [
    'id' => $t['IDTipo'], 'nome' => $t['Nome'], 'especie' => $t['FKEspecie'] ?? '', 'intervalo' => $t['IntervaloMeses'],
], $tipos), JSON_UNESCAPED_UNICODE) ?>;

function labelVacina(t) {
    return t.nome + (t.intervalo ? ' (reforço em ' + t.intervalo + ' meses)' : ' (dose única)');
}

// Filtra a lista de vacinas pela espécie do animal escolhido — vacina sem
// espécie fixada (FKEspecie null) serve pra qualquer uma.
function vacinasParaEspecie(especie) {
    return TIPOS_VACINA.filter(function (t) { return !especie || !t.especie || t.especie === especie; });
}

function atualizarCiclicaWrap(tipoId) {
    var t = TIPOS_VACINA.find(function (x) { return x.id === tipoId; });
    var wrap = document.getElementById('ciclicaWrap');
    if (t && t.intervalo) {
        wrap.style.display = '';
        document.getElementById('rvCiclicaTexto').textContent = '(a cada ' + t.intervalo + ' meses, sem precisar reaplicar pra gerar a próxima data)';
    } else {
        wrap.style.display = 'none';
        document.getElementById('rvCiclica').checked = false;
    }
}

var rvTipoPk = initPicker({
    pickerId: 'rvTipoPicker', triggerId: 'rvTipoTrigger', dropdownId: 'rvTipoDropdown',
    searchId: 'rvTipoSearch', listId: 'rvTipoList', hiddenId: 'inprvTipoId', labelId: 'rvTipoLabel',
    items: TIPOS_VACINA,
    chave: function (t) { return t.id; },
    renderItem: function (t) { return { title: labelVacina(t) }; },
    matches: function (t, q) { return t.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nenhuma vacina encontrada.',
    onSelect: function (t) { atualizarCiclicaWrap(t.id); },
});

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
    onSelect: function (a) {
        var itens = vacinasParaEspecie(a.especie);
        rvTipoPk.setItems(itens, 'Selecione a vacina');
        // Abre a vacina sozinha pra fluir direto — mesmo padrão do Tipo/
        // Procedimento na agenda. setTimeout pelo mesmo motivo: o "click"
        // nativo que ainda vai disparar fecharia o dropdown na hora.
        if (itens.length) {
            setTimeout(function () { rvTipoPk.abrir(); }, 50);
        }
    },
});

<?php if ($animalPre): ?>
    rvTipoPk.setItems(vacinasParaEspecie(<?= json_encode($animalPre['FKEspecie']) ?>), 'Selecione a vacina');
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
