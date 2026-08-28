<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin', 'veterinario');

$id = trim($_GET['id'] ?? '');
if (!$id) {
    redirecionarComMensagem(BASE . '/painel/animais.php', 'Animal não encontrado.', 'warning');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/animal_detalhe.php?id=' . $id, 'Token inválido.', 'danger');
    }
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'editar') {
        $nome  = trim($_POST['nome'] ?? '');
        $raca  = trim($_POST['raca'] ?? '');
        $nasc  = trim($_POST['nascimento'] ?? '');
        $sexo  = trim($_POST['sexo'] ?? '');
        $peso  = trim($_POST['peso'] ?? '');
        $chip  = trim($_POST['microchip'] ?? '');
        $obs   = trim($_POST['observacoes'] ?? '');

        if ($nome === '' || $raca === '' || $sexo === '') {
            redirecionarComMensagem(BASE . '/painel/animal_detalhe.php?id=' . $id, 'Nome, raça e sexo são obrigatórios.', 'warning');
        }
        if (!dataNascimentoValida($nasc)) {
            redirecionarComMensagem(BASE . '/painel/animal_detalhe.php?id=' . $id, 'Data de nascimento inválida — não pode ser no futuro nem passar de 100 anos atrás.', 'warning');
        }

        try {
            $pdo->prepare(
                'UPDATE Animais SET Nome=:nome, Raca=:raca, DataNascimento=:nasc, Sexo=:sexo,
                        PesoKg=:peso, Microchip=:chip, Observacoes=:obs
                 WHERE IDAnimal = :id'
            )->execute([
                ':nome' => $nome, ':raca' => $raca, ':nasc' => $nasc ?: null,
                ':sexo' => $sexo, ':peso' => $peso !== '' ? $peso : null,
                ':chip' => $chip ?: null, ':obs' => $obs ?: null, ':id' => $id,
            ]);
            redirecionarComMensagem(BASE . '/painel/animal_detalhe.php?id=' . $id, 'Animal atualizado com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log('[EditarAnimal] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/animal_detalhe.php?id=' . $id, 'Erro ao atualizar.', 'danger');
        }
    }

    if ($acao === 'desativar') {
        try {
            $stmt = $pdo->prepare('SELECT FKDono FROM Animais WHERE IDAnimal = :id');
            $stmt->execute([':id' => $id]);
            $fkDono = $stmt->fetchColumn();
            $pdo->prepare('UPDATE Animais SET Ativo = 0 WHERE IDAnimal = :id')->execute([':id' => $id]);
            redirecionarComMensagem(BASE . '/painel/cliente_detalhe.php?id=' . $fkDono, 'Animal removido.', 'success');
        } catch (PDOException $e) {
            error_log('[DesativarAnimal] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/animal_detalhe.php?id=' . $id, 'Erro ao remover.', 'danger');
        }
    }
}

try {
    $stmt = $pdo->prepare(
        'SELECT a.*, e.Nome AS NomeEspecie, e.Icone AS IconeEspecie, e.IDEspecie,
                u.Nome AS NomeDono, u.Telefone AS TelefoneDono, u.IDUsuario AS IDDono
         FROM Animais a
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         JOIN Usuarios u ON u.IDUsuario = a.FKDono
         WHERE a.IDAnimal = :id AND a.Ativo = 1
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $animal = $stmt->fetch();
    if (!$animal) {
        redirecionarComMensagem(BASE . '/painel/animais.php', 'Animal não encontrado.', 'warning');
    }

    $historico = $pdo->prepare(
        'SELECT rv.*, tv.Nome AS NomeVacina
         FROM RegistrosVacinas rv
         JOIN TiposVacina tv ON tv.IDTipo = rv.FKTipoVacina
         WHERE rv.FKAnimal = :id
         ORDER BY rv.DataAplicacao DESC'
    );
    $historico->execute([':id' => $id]);
    $historico = $historico->fetchAll();

    $racas = $pdo->prepare('SELECT Nome FROM Racas WHERE FKEspecie = :esp ORDER BY Ordem ASC');
    $racas->execute([':esp' => $animal['FKEspecie']]);
    $racas = $racas->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('[AnimalDetalhe] ' . $e->getMessage());
    $historico = [];
    $racas     = [];
}

$paginaTitulo = h($animal['Nome']);
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= BASE ?>/painel/cliente_detalhe.php?id=<?= h($animal['IDDono']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0"><?= h($animal['IconeEspecie']) ?> <?= h($animal['Nome']) ?></h4>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h5 class="fw-bold mb-0"><?= h($animal['Nome']) ?></h5>
                    <p class="small text-secondary mb-0"><?= h($animal['NomeEspecie']) ?><?= $animal['Raca'] ? ' · ' . h($animal['Raca']) : '' ?></p>
                </div>
                <button class="btn btn-sm btn-outline-accent" data-bs-toggle="modal" data-bs-target="#modalEditarAnimal">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <dl class="mb-3">
                <dt class="small text-secondary">Dono</dt>
                <dd><a href="<?= BASE ?>/painel/cliente_detalhe.php?id=<?= h($animal['IDDono']) ?>"><?= h($animal['NomeDono']) ?></a></dd>
                <?php if ($animal['DataNascimento']): ?>
                    <dt class="small text-secondary">Idade</dt>
                    <dd><?= h(formatarIdade($animal['DataNascimento'])) ?> (<?= formatarData($animal['DataNascimento']) ?>)</dd>
                <?php endif ?>
                <?php if ($animal['Sexo']): ?>
                    <dt class="small text-secondary">Sexo</dt>
                    <dd><?= formatarSexo($animal['Sexo']) ?></dd>
                <?php endif ?>
                <?php if ($animal['PesoKg']): ?>
                    <dt class="small text-secondary">Peso</dt>
                    <dd><?= h(number_format((float) $animal['PesoKg'], 3, ',', '.')) ?> kg</dd>
                <?php endif ?>
                <?php if ($animal['Microchip']): ?>
                    <dt class="small text-secondary">Microchip</dt>
                    <dd><?= h($animal['Microchip']) ?></dd>
                <?php endif ?>
                <?php if ($animal['Observacoes']): ?>
                    <dt class="small text-secondary">Observações</dt>
                    <dd><?= nl2br(h($animal['Observacoes'])) ?></dd>
                <?php endif ?>
            </dl>
            <a href="<?= BASE ?>/painel/registrar_vacina.php?animal=<?= h($animal['IDAnimal']) ?>" class="btn btn-accent w-100 mb-2">
                <i class="bi bi-shield-plus me-1"></i> Registrar vacina
            </a>
            <form method="POST" data-confirm="Remover <?= h($animal['Nome']) ?>? Esta ação não pode ser desfeita.">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="desativar">
                <button type="submit" class="btn btn-outline-danger w-100">
                    <i class="bi bi-trash me-1"></i> Remover animal
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header px-4 py-3">
                <i class="bi bi-clipboard2-pulse me-2 text-accent"></i>Histórico de vacinação
            </div>
            <div class="card-body p-0">
                <?php if (empty($historico)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-shield-plus fs-1 d-block mb-2 opacity-25"></i>
                        <p class="mb-0">Nenhuma vacina registrada.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tabelaVacinas">
                            <thead style="background:var(--bg-hover);">
                                <tr>
                                    <th class="px-4 py-3">Vacina</th>
                                    <th>Aplicada</th>
                                    <th>Próxima</th>
                                    <th>Situação</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historico as $reg): ?>
                                    <tr data-id="<?= h($reg['IDRegistro']) ?>">
                                        <td class="px-4 fw-medium"><?= h($reg['NomeVacina']) ?></td>
                                        <td class="small"><?= formatarData($reg['DataAplicacao']) ?></td>
                                        <td class="small"><?= $reg['ProximaData'] ? formatarData($reg['ProximaData']) : '—' ?></td>
                                        <td><?= labelSituacaoVacina($reg['ProximaData']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger btn-excluir-vacina"
                                                data-id="<?= h($reg['IDRegistro']) ?>"
                                                data-confirm="Excluir este registro de vacina?">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
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

<div class="modal fade" id="modalEditarAnimal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="editar">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Editar animal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome do animal *</label>
                        <input type="text" name="nome" class="form-control" required value="<?= h($animal['Nome']) ?>">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Raça *</label>
                            <?= campoPicker('eaRaca', 'raca', 'Selecione…', 'Buscar raça…', $animal['Raca'] ?? '', $animal['Raca'] ?? '', obrigatorio: true) ?>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Sexo *</label>
                            <?php
                                $iconeSexoAtual = match ($animal['Sexo'] ?? '') {
                                    'macho' => 'bi-gender-male',
                                    'femea' => 'bi-gender-female',
                                    default => '',
                                };
                                $textoSexoAtual = match ($animal['Sexo'] ?? '') {
                                    'macho' => 'Macho',
                                    'femea' => 'Fêmea',
                                    'indeterminado' => 'Indeterminado',
                                    default => '',
                                };
                            ?>
                            <?= campoPicker('eaSexo', 'sexo', '—', '', $animal['Sexo'] ?? '', $textoSexoAtual, obrigatorio: true, comBusca: false, iconeInicial: $iconeSexoAtual) ?>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Nascimento</label>
                            <input type="date" name="nascimento" class="form-control" data-validar="nascimento" min="<?= date('Y-m-d', strtotime('-100 years')) ?>" max="<?= date('Y-m-d') ?>" value="<?= h($animal['DataNascimento']) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Peso (kg)</label>
                            <input type="text" id="eaPesoVisivel" class="form-control" data-mask="peso" data-target="eaPesoReal" placeholder="0,000" inputmode="numeric" value="<?= $animal['PesoKg'] ? h(number_format((float) $animal['PesoKg'], 3, ',', '')) : '' ?>">
                            <input type="hidden" name="peso" id="eaPesoReal" value="<?= h($animal['PesoKg']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Microchip</label>
                        <input type="text" name="microchip" class="form-control" value="<?= h($animal['Microchip']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="3"><?= h($animal['Observacoes']) ?></textarea>
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
var EA_RACAS = <?= json_encode($racas, JSON_UNESCAPED_UNICODE) ?>.map(function (n) { return { nome: n }; });

initPicker({
    pickerId: 'eaRacaPicker', triggerId: 'eaRacaTrigger', dropdownId: 'eaRacaDropdown',
    searchId: 'eaRacaSearch', listId: 'eaRacaList', hiddenId: 'inpeaRacaId', labelId: 'eaRacaLabel',
    items: EA_RACAS,
    chave: function (r) { return r.nome; },
    renderItem: function (r) { return { title: r.nome }; },
    matches: function (r, q) { return r.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nenhuma raça encontrada.',
});

initPicker({
    pickerId: 'eaSexoPicker', triggerId: 'eaSexoTrigger', dropdownId: 'eaSexoDropdown',
    searchId: 'eaSexoSearch', listId: 'eaSexoList', hiddenId: 'inpeaSexoId', labelId: 'eaSexoLabel',
    items: [
        { id: 'macho', label: 'Macho', icon: 'bi-gender-male' },
        { id: 'femea', label: 'Fêmea', icon: 'bi-gender-female' },
        { id: 'indeterminado', label: 'Indeterminado', icon: '' },
    ],
    chave: function (s) { return s.id; },
    renderItem: function (s) { return { title: s.label, icon: s.icon }; },
    matches: function (s, q) { return s.label.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nada encontrado.',
});

document.querySelectorAll('.btn-excluir-vacina').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        vsConfirm(btn.dataset.confirm, function () {
            fetch(BASE + '/painel/api_vacina.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: 'excluir', id: btn.dataset.id, csrf_token: '<?= gerarTokenCSRF() ?>' }),
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    document.querySelector('tr[data-id="' + btn.dataset.id + '"]')?.remove();
                    vsToast('Registro excluído.', 'success');
                } else {
                    vsToast(d.msg || 'Erro ao excluir.', 'danger');
                }
            })
            .catch(function () { vsToast('Falha na conexão.', 'danger'); });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
