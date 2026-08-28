<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin', 'veterinario');

$categorias = [
    'cirurgia'     => 'Cirurgia',
    'consulta'     => 'Consulta',
    'exame'        => 'Exame',
    'procedimento' => 'Procedimento',
    'observacao'   => 'Observação',
    'outro'        => 'Outro',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/tipos_procedimento.php', 'Token inválido.', 'danger');
    }
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id        = trim($_POST['id'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $nome      = trim($_POST['nome'] ?? '');
        $duracao   = (int) ($_POST['duracao'] ?? 30);

        if ($nome === '' || !isset($categorias[$categoria])) {
            redirecionarComMensagem(BASE . '/painel/tipos_procedimento.php', 'Categoria e nome são obrigatórios.', 'warning');
        }
        if ($duracao < 5 || $duracao > 480) {
            redirecionarComMensagem(BASE . '/painel/tipos_procedimento.php', 'Duração deve estar entre 5 e 480 minutos.', 'warning');
        }

        try {
            if ($id) {
                $pdo->prepare(
                    'UPDATE TiposProcedimento SET Categoria=:cat, Nome=:nome, DuracaoPadraoMinutos=:dur WHERE IDTipo=:id'
                )->execute([':cat' => $categoria, ':nome' => $nome, ':dur' => $duracao, ':id' => $id]);
                redirecionarComMensagem(BASE . '/painel/tipos_procedimento.php', 'Procedimento atualizado com sucesso!', 'success');
            } else {
                $pdo->prepare(
                    'INSERT INTO TiposProcedimento (IDTipo, Categoria, Nome, DuracaoPadraoMinutos)
                     VALUES (:id, :cat, :nome, :dur)'
                )->execute([':id' => gerarUuid(), ':cat' => $categoria, ':nome' => $nome, ':dur' => $duracao]);
                redirecionarComMensagem(BASE . '/painel/tipos_procedimento.php', 'Procedimento cadastrado com sucesso!', 'success');
            }
        } catch (PDOException $e) {
            error_log('[TiposProcedimento] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/tipos_procedimento.php', 'Erro ao salvar — talvez já exista um procedimento com esse nome nessa categoria.', 'danger');
        }
    }

    if ($acao === 'desativar') {
        $id = trim($_POST['id'] ?? '');
        try {
            $pdo->prepare('UPDATE TiposProcedimento SET Ativo = 0 WHERE IDTipo = :id')->execute([':id' => $id]);
            redirecionarComMensagem(BASE . '/painel/tipos_procedimento.php', 'Procedimento removido do catálogo.', 'success');
        } catch (PDOException $e) {
            error_log('[TiposProcedimento][Desativar] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/tipos_procedimento.php', 'Erro ao remover.', 'danger');
        }
    }
}

try {
    $procedimentos = $pdo->query(
        "SELECT * FROM TiposProcedimento WHERE Ativo = 1 ORDER BY FIELD(Categoria,'cirurgia','consulta','exame','procedimento','observacao','outro'), Ordem ASC, Nome ASC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('[TiposProcedimentoLista] ' . $e->getMessage());
    $procedimentos = [];
}

$paginaTitulo = 'Tipos de Procedimento';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-1">
    <a href="<?= BASE ?>/painel/agenda.php" onclick="voltarInteligente(event)" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0">Tipos de Procedimento</h4>
</div>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <p class="text-secondary small mb-0">Cada procedimento tem uma duração padrão — ao agendar, escolher o procedimento já preenche a duração (mas dá pra ajustar).</p>
    <button class="btn btn-accent btn-sm" onclick="abrirModalProcedimento()">
        <i class="bi bi-plus-lg me-1"></i> Novo procedimento
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($procedimentos)): ?>
            <div class="text-center py-5 text-secondary">
                <i class="bi bi-list-check fs-1 d-block mb-2 opacity-25"></i>
                <p>Nenhum procedimento cadastrado.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:var(--bg-hover);">
                        <tr>
                            <th class="px-4 py-3">Categoria</th>
                            <th>Nome</th>
                            <th>Duração padrão</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedimentos as $p): ?>
                            <tr>
                                <td class="px-4">
                                    <span class="badge" style="background:var(--accent-light);color:var(--accent);"><?= h($categorias[$p['Categoria']] ?? $p['Categoria']) ?></span>
                                </td>
                                <td class="fw-medium"><?= h($p['Nome']) ?></td>
                                <td class="small"><?= (int) $p['DuracaoPadraoMinutos'] ?> min</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-accent"
                                        onclick='abrirModalProcedimento(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" data-confirm="Remover &quot;<?= h($p['Nome']) ?>&quot; do catálogo?">
                                        <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                                        <input type="hidden" name="acao" value="desativar">
                                        <input type="hidden" name="id" value="<?= h($p['IDTipo']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>

<div class="modal fade" id="modalProcedimento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="fId">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="tituloModalProcedimento">Novo procedimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Categoria *</label>
                        <?= campoPicker('fCat', 'categoria', '—', '', 'consulta', 'Consulta', obrigatorio: true, comBusca: false) ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" id="fNome" class="form-control" required maxlength="100" placeholder="Ex: Castração, Consulta de rotina…">
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Duração padrão (minutos) *</label>
                        <input type="number" name="duracao" id="fDuracao" class="form-control" required min="5" max="480" step="5">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-check2 me-1"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var CATEGORIAS_TP = <?= json_encode(array_map(fn($valor, $label) => [
    'id' => $valor, 'nome' => $label,
], array_keys($categorias), $categorias), JSON_UNESCAPED_UNICODE) ?>;

var fCatPk = initPicker({
    pickerId: 'fCatPicker', triggerId: 'fCatTrigger', dropdownId: 'fCatDropdown',
    searchId: 'fCatSearch', listId: 'fCatList', hiddenId: 'inpfCatId', labelId: 'fCatLabel',
    items: CATEGORIAS_TP,
    chave: function (c) { return c.id; },
    renderItem: function (c) { return { title: c.nome }; },
    matches: function (c, q) { return c.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nada encontrado.',
});

function abrirModalProcedimento(dados) {
    document.getElementById('tituloModalProcedimento').textContent = dados ? 'Editar procedimento' : 'Novo procedimento';
    document.getElementById('fId').value        = dados ? dados.IDTipo : '';
    var cat = dados ? dados.Categoria : 'consulta';
    fCatPk.selecionar(CATEGORIAS_TP.filter(function (c) { return c.id === cat; })[0] || CATEGORIAS_TP[0]);
    document.getElementById('fNome').value      = dados ? dados.Nome : '';
    document.getElementById('fDuracao').value   = dados ? dados.DuracaoPadraoMinutos : 30;
    new bootstrap.Modal(document.getElementById('modalProcedimento')).show();
}
</script>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
