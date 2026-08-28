<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin', 'veterinario');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/tipos_vacina.php', 'Token inválido.', 'danger');
    }
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id       = trim($_POST['id'] ?? '');
        $nome     = trim($_POST['nome'] ?? '');
        $desc     = trim($_POST['descricao'] ?? '');
        $intervalo = trim($_POST['intervalo'] ?? '');
        $especie  = trim($_POST['especie'] ?? '');

        if ($nome === '') {
            redirecionarComMensagem(BASE . '/painel/tipos_vacina.php', 'Nome é obrigatório.', 'warning');
        }

        try {
            if ($id) {
                $pdo->prepare(
                    'UPDATE TiposVacina SET Nome=:nome, Descricao=:desc, IntervaloMeses=:int, FKEspecie=:esp WHERE IDTipo=:id'
                )->execute([
                    ':nome' => $nome, ':desc' => $desc ?: null,
                    ':int'  => $intervalo !== '' ? $intervalo : null,
                    ':esp'  => $especie ?: null, ':id' => $id,
                ]);
                redirecionarComMensagem(BASE . '/painel/tipos_vacina.php', 'Vacina atualizada com sucesso!', 'success');
            } else {
                $pdo->prepare(
                    'INSERT INTO TiposVacina (IDTipo, Nome, Descricao, IntervaloMeses, FKEspecie)
                     VALUES (:id, :nome, :desc, :int, :esp)'
                )->execute([
                    ':id' => gerarUuid(), ':nome' => $nome, ':desc' => $desc ?: null,
                    ':int' => $intervalo !== '' ? $intervalo : null, ':esp' => $especie ?: null,
                ]);
                redirecionarComMensagem(BASE . '/painel/tipos_vacina.php', 'Vacina cadastrada com sucesso!', 'success');
            }
        } catch (PDOException $e) {
            error_log('[TiposVacina] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/tipos_vacina.php', 'Erro ao salvar.', 'danger');
        }
    }

    if ($acao === 'desativar') {
        $id = trim($_POST['id'] ?? '');
        try {
            $pdo->prepare('UPDATE TiposVacina SET Ativo = 0 WHERE IDTipo = :id')->execute([':id' => $id]);
            redirecionarComMensagem(BASE . '/painel/tipos_vacina.php', 'Vacina removida do catálogo.', 'success');
        } catch (PDOException $e) {
            error_log('[TiposVacina][Desativar] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/tipos_vacina.php', 'Erro ao remover.', 'danger');
        }
    }
}

try {
    $tipos = $pdo->query(
        'SELECT tv.*, e.Nome AS NomeEspecie, e.Icone AS IconeEspecie
         FROM TiposVacina tv
         LEFT JOIN Especies e ON e.IDEspecie = tv.FKEspecie
         WHERE tv.Ativo = 1
         ORDER BY tv.Nome ASC'
    )->fetchAll();
    $especies = $pdo->query('SELECT * FROM Especies ORDER BY Ordem ASC')->fetchAll();
} catch (PDOException $e) {
    error_log('[TiposVacinaLista] ' . $e->getMessage());
    $tipos = $especies = [];
}

$paginaTitulo = 'Tipos de Vacina';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h4 class="fw-bold mb-0">Catálogo de Vacinas</h4>
    <button class="btn btn-accent btn-sm" onclick="abrirModalVacina()">
        <i class="bi bi-plus-lg me-1"></i> Nova vacina
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($tipos)): ?>
            <div class="text-center py-5 text-secondary">
                <i class="bi bi-shield-plus fs-1 d-block mb-2 opacity-25"></i>
                <p>Nenhuma vacina cadastrada.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:var(--bg-hover);">
                        <tr>
                            <th class="px-4 py-3">Vacina</th>
                            <th class="d-none d-md-table-cell">Espécie</th>
                            <th>Reforço</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tipos as $t): ?>
                            <tr>
                                <td class="px-4">
                                    <div class="fw-medium"><?= h($t['Nome']) ?></div>
                                    <?php if ($t['Descricao']): ?><div class="small text-secondary"><?= h($t['Descricao']) ?></div><?php endif ?>
                                </td>
                                <td class="d-none d-md-table-cell small">
                                    <?= $t['NomeEspecie'] ? especieIconeHtml($t['IconeEspecie']) . ' ' . h($t['NomeEspecie']) : 'Todas' ?>
                                </td>
                                <td class="small"><?= $t['IntervaloMeses'] ? $t['IntervaloMeses'] . ' meses' : 'Dose única' ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-accent"
                                        onclick='abrirModalVacina(<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" data-confirm="Remover a vacina &quot;<?= h($t['Nome']) ?>&quot; do catálogo?">
                                        <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                                        <input type="hidden" name="acao" value="desativar">
                                        <input type="hidden" name="id" value="<?= h($t['IDTipo']) ?>">
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

<div class="modal fade" id="modalVacina" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="fId">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="tituloModalVacina">Nova vacina</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" id="fNome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" id="fDescricao" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Intervalo de reforço (meses)</label>
                            <input type="number" name="intervalo" id="fIntervalo" class="form-control" min="0" placeholder="Deixe em branco p/ dose única">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Espécie</label>
                            <select name="especie" id="fEspecie" class="form-select">
                                <option value="">Todas as espécies</option>
                                <?php foreach ($especies as $e): ?>
                                    <option value="<?= h($e['IDEspecie']) ?>"><?= h($e['Nome']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
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
function abrirModalVacina(dados) {
    document.getElementById('tituloModalVacina').textContent = dados ? 'Editar vacina' : 'Nova vacina';
    document.getElementById('fId').value        = dados ? dados.IDTipo : '';
    document.getElementById('fNome').value      = dados ? dados.Nome : '';
    document.getElementById('fDescricao').value = dados ? (dados.Descricao || '') : '';
    document.getElementById('fIntervalo').value = dados ? (dados.IntervaloMeses || '') : '';
    document.getElementById('fEspecie').value   = dados ? (dados.FKEspecie || '') : '';
    new bootstrap.Modal(document.getElementById('modalVacina')).show();
}
</script>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
