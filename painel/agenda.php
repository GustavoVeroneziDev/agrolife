<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin', 'veterinario');

$tiposAgenda = [
    'cirurgia'     => 'Cirurgia',
    'consulta'     => 'Consulta',
    'exame'        => 'Exame',
    'procedimento' => 'Procedimento',
    'observacao'   => 'Observação',
    'outro'        => 'Outro',
];

// Verifica sobreposição de horário pro mesmo veterinário — mesma checagem
// de verdade tanto no cadastro quanto (se precisar) numa futura remarcação.
function agendamentoConflita(PDO $pdo, string $fkVet, string $inicio, string $fim, string $ignorarId = ''): bool
{
    $sql = "SELECT COUNT(*) FROM Agendamentos
            WHERE FKVeterinario = :vet AND Status != 'cancelado'
              AND DataHoraInicio < :fim AND DataHoraFim > :inicio";
    $params = [':vet' => $fkVet, ':inicio' => $inicio, ':fim' => $fim];
    if ($ignorarId !== '') {
        $sql .= ' AND IDAgendamento != :ignorar';
        $params[':ignorar'] = $ignorarId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return ((int) $stmt->fetchColumn()) > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/agenda.php', 'Token inválido.', 'danger');
    }
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'novo_agendamento') {
        $fkAnimal = trim($_POST['animal'] ?? '');
        $fkVet    = trim($_POST['veterinario'] ?? '');
        $tipo     = trim($_POST['tipo'] ?? '');
        $titulo   = trim($_POST['titulo'] ?? '');
        $data     = trim($_POST['data'] ?? '');
        $hora     = trim($_POST['hora'] ?? '');
        $duracao  = (int) ($_POST['duracao'] ?? 30);
        $obs      = trim($_POST['observacoes'] ?? '');

        if ($fkAnimal === '' || $titulo === '' || $data === '' || $hora === '' || !isset($tiposAgenda[$tipo])) {
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Animal, tipo, título, data e hora são obrigatórios.', 'warning');
        }

        $inicio = $data . ' ' . $hora . ':00';
        $ts     = strtotime($inicio);
        if (!$ts) {
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Data/hora inválida.', 'warning');
        }
        $fim = date('Y-m-d H:i:s', $ts + $duracao * 60);

        if ($fkVet !== '' && agendamentoConflita($pdo, $fkVet, $inicio, $fim)) {
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Esse veterinário já tem outro agendamento nesse horário.', 'warning');
        }

        try {
            $pdo->prepare(
                'INSERT INTO Agendamentos (IDAgendamento, FKAnimal, FKVeterinario, Tipo, Titulo, DataHoraInicio, DataHoraFim, Observacoes)
                 VALUES (:id, :animal, :vet, :tipo, :titulo, :inicio, :fim, :obs)'
            )->execute([
                ':id'     => gerarUuid(),
                ':animal' => $fkAnimal,
                ':vet'    => $fkVet ?: null,
                ':tipo'   => $tipo,
                ':titulo' => $titulo,
                ':inicio' => $inicio,
                ':fim'    => $fim,
                ':obs'    => $obs ?: null,
            ]);
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Agendamento criado com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log('[NovoAgendamento] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Erro ao criar agendamento.', 'danger');
        }
    }

    if ($acao === 'concluir') {
        $id      = trim($_POST['id'] ?? '');
        $obsPos  = trim($_POST['observacoes_pos'] ?? '');
        $criarRc = !empty($_POST['criar_clinico']);

        if ($id === '') {
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Agendamento não encontrado.', 'warning');
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM Agendamentos WHERE IDAgendamento = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $ag = $stmt->fetch();
            if (!$ag) {
                redirecionarComMensagem(BASE . '/painel/agenda.php', 'Agendamento não encontrado.', 'warning');
            }

            $fkRegistroClinico = null;
            if ($criarRc) {
                $fkRegistroClinico = gerarUuid();
                $pdo->prepare(
                    'INSERT INTO RegistrosClinicos (IDRegistro, FKAnimal, FKVeterinario, Tipo, Titulo, Anotacoes, DataRegistro)
                     VALUES (:id, :animal, :vet, :tipo, :titulo, :anot, :data)'
                )->execute([
                    ':id'     => $fkRegistroClinico,
                    ':animal' => $ag['FKAnimal'],
                    ':vet'    => $ag['FKVeterinario'],
                    ':tipo'   => $ag['Tipo'],
                    ':titulo' => $ag['Titulo'],
                    ':anot'   => $obsPos ?: null,
                    ':data'   => date('Y-m-d'),
                ]);

                foreach ($_FILES['imagens']['tmp_name'] ?? [] as $i => $tmp) {
                    $arquivo = [
                        'tmp_name' => $tmp,
                        'error'    => $_FILES['imagens']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size'     => $_FILES['imagens']['size'][$i] ?? 0,
                    ];
                    $caminho = salvarImagemEnviada($arquivo, 'clinico');
                    if ($caminho !== null) {
                        $pdo->prepare(
                            'INSERT INTO AnexosClinicos (IDAnexo, FKRegistro, CaminhoArquivo, NomeOriginal)
                             VALUES (:id, :reg, :caminho, :nome)'
                        )->execute([
                            ':id'      => gerarUuid(),
                            ':reg'     => $fkRegistroClinico,
                            ':caminho' => $caminho,
                            ':nome'    => $_FILES['imagens']['name'][$i] ?? null,
                        ]);
                    }
                }
            }

            $pdo->prepare(
                "UPDATE Agendamentos SET Status = 'concluido', ObservacoesPos = :obs, FKRegistroClinico = :rc WHERE IDAgendamento = :id"
            )->execute([':obs' => $obsPos ?: null, ':rc' => $fkRegistroClinico, ':id' => $id]);

            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Agendamento concluído com sucesso!', 'success');
        } catch (PDOException $e) {
            error_log('[ConcluirAgendamento] ' . $e->getMessage());
            redirecionarComMensagem(BASE . '/painel/agenda.php', 'Erro ao concluir agendamento.', 'danger');
        }
    }
}

$filtroStatus = trim($_GET['status'] ?? '');
$animalPreId  = trim($_GET['animal'] ?? '');

try {
    $animais = $pdo->query(
        "SELECT a.IDAnimal, a.Nome, a.FKEspecie, u.Nome AS NomeDono, e.Icone AS IconeEspecie
         FROM Animais a
         JOIN Usuarios u ON u.IDUsuario = a.FKDono
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         WHERE a.Ativo = 1 ORDER BY a.Nome ASC"
    )->fetchAll();

    $vets = $pdo->query(
        "SELECT IDUsuario, Nome FROM Usuarios WHERE NivelAcesso = 'veterinario' AND Ativo = 1 ORDER BY Nome ASC"
    )->fetchAll();

    $where  = "WHERE ag.DataHoraInicio >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    $params = [];
    if ($filtroStatus !== '' && isset(['pendente'=>1,'confirmado'=>1,'concluido'=>1,'cancelado'=>1,'faltou'=>1][$filtroStatus])) {
        $where .= ' AND ag.Status = :status';
        $params[':status'] = $filtroStatus;
    } else {
        $where .= " AND ag.Status != 'cancelado'";
    }

    $stmt = $pdo->prepare(
        "SELECT ag.*, a.Nome AS NomeAnimal, e.Icone AS IconeEspecie,
                u.Nome AS NomeDono, v.Nome AS NomeVeterinario
         FROM Agendamentos ag
         JOIN Animais a  ON a.IDAnimal = ag.FKAnimal
         JOIN Especies e ON e.IDEspecie = a.FKEspecie
         JOIN Usuarios u ON u.IDUsuario = a.FKDono
         LEFT JOIN Usuarios v ON v.IDUsuario = ag.FKVeterinario
         {$where}
         ORDER BY ag.DataHoraInicio ASC"
    );
    $stmt->execute($params);
    $agendamentos = $stmt->fetchAll();

    // Agrupa por dia pra organizar a lista visualmente
    $porDia = [];
    foreach ($agendamentos as $ag) {
        $dia = substr($ag['DataHoraInicio'], 0, 10);
        $porDia[$dia][] = $ag;
    }

    $animalPre = null;
    if ($animalPreId) {
        $stmt = $pdo->prepare(
            'SELECT a.IDAnimal, a.Nome, u.Nome AS NomeDono
             FROM Animais a JOIN Usuarios u ON u.IDUsuario = a.FKDono
             WHERE a.IDAnimal = :id AND a.Ativo = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $animalPreId]);
        $animalPre = $stmt->fetch();
    }
} catch (PDOException $e) {
    error_log('[Agenda] ' . $e->getMessage());
    $animais = $vets = $agendamentos = $porDia = [];
    $animalPre = null;
}

$paginaTitulo = 'Agenda';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h4 class="fw-bold mb-0">Agenda</h4>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" style="width:auto;" onchange="location.href='?status='+this.value">
            <option value="">Próximos (sem cancelados)</option>
            <option value="pendente" <?= $filtroStatus === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
            <option value="confirmado" <?= $filtroStatus === 'confirmado' ? 'selected' : '' ?>>Confirmados</option>
            <option value="concluido" <?= $filtroStatus === 'concluido' ? 'selected' : '' ?>>Concluídos</option>
            <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
            <option value="faltou" <?= $filtroStatus === 'faltou' ? 'selected' : '' ?>>Faltas</option>
        </select>
        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoAgendamento">
            <i class="bi bi-calendar-plus me-1"></i> Novo agendamento
        </button>
    </div>
</div>

<?php if (empty($agendamentos)): ?>
    <div class="card text-center py-5 text-secondary">
        <i class="bi bi-calendar3 fs-1 d-block mb-2 opacity-25"></i>
        <p class="mb-0">Nenhum agendamento encontrado.</p>
    </div>
<?php else: ?>
    <?php foreach ($porDia as $dia => $lista): ?>
        <h6 class="fw-semibold text-secondary mt-4 mb-2">
            <?= h(formatarData($dia)) ?>
            <?php if ($dia === date('Y-m-d')): ?>
                <span class="badge" style="background:var(--accent-light);color:var(--accent);">Hoje</span>
            <?php endif ?>
        </h6>
        <div class="d-flex flex-column gap-2 mb-2">
            <?php foreach ($lista as $ag): ?>
                <div class="card p-3" data-id-agendamento="<?= h($ag['IDAgendamento']) ?>">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-center" style="min-width:52px;">
                                <div class="fw-bold"><?= date('H:i', strtotime($ag['DataHoraInicio'])) ?></div>
                                <div class="small text-secondary"><?= date('H:i', strtotime($ag['DataHoraFim'])) ?></div>
                            </div>
                            <div>
                                <div>
                                    <span class="badge" style="background:var(--accent-light);color:var(--accent);"><?= h($tiposAgenda[$ag['Tipo']] ?? $ag['Tipo']) ?></span>
                                    <?= labelStatusAgendamento($ag['Status']) ?>
                                </div>
                                <div class="fw-medium mt-1">
                                    <?= h($ag['IconeEspecie']) ?> <?= h($ag['NomeAnimal']) ?>
                                    <span class="text-secondary fw-normal">— <?= h($ag['NomeDono']) ?></span>
                                </div>
                                <div class="small text-secondary">
                                    <?= h($ag['Titulo']) ?><?= $ag['NomeVeterinario'] ? ' · ' . h($ag['NomeVeterinario']) : ' · sem veterinário definido' ?>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($ag['Status'] === 'pendente'): ?>
                                <button class="btn btn-sm btn-outline-info btn-acao-agendamento" data-acao="confirmar" data-id="<?= h($ag['IDAgendamento']) ?>">Confirmar</button>
                                <button class="btn btn-sm btn-outline-danger btn-acao-agendamento" data-acao="cancelar" data-id="<?= h($ag['IDAgendamento']) ?>" data-confirm="Cancelar esse agendamento?">Cancelar</button>
                            <?php elseif ($ag['Status'] === 'confirmado'): ?>
                                <button class="btn btn-sm btn-accent btn-concluir"
                                    data-id="<?= h($ag['IDAgendamento']) ?>" data-tipo="<?= h($ag['Tipo']) ?>" data-titulo="<?= h($ag['Titulo']) ?>">
                                    Concluir
                                </button>
                                <button class="btn btn-sm btn-outline-warning btn-acao-agendamento" data-acao="marcar_falta" data-id="<?= h($ag['IDAgendamento']) ?>" data-confirm="Marcar falta nesse agendamento?">Faltou</button>
                                <button class="btn btn-sm btn-outline-danger btn-acao-agendamento" data-acao="cancelar" data-id="<?= h($ag['IDAgendamento']) ?>" data-confirm="Cancelar esse agendamento?">Cancelar</button>
                            <?php elseif (in_array($ag['Status'], ['concluido', 'cancelado', 'faltou'], true)): ?>
                                <button class="btn btn-sm btn-outline-secondary btn-acao-agendamento" data-acao="reabrir" data-id="<?= h($ag['IDAgendamento']) ?>" data-confirm="Reabrir esse agendamento?">Reabrir</button>
                            <?php endif ?>
                        </div>
                    </div>
                    <?php if ($ag['Status'] === 'concluido' && $ag['ObservacoesPos']): ?>
                        <div class="small mt-2 pt-2 border-top" style="border-color:var(--card-border-color) !important;">
                            <strong>Pós-consulta:</strong> <?= nl2br(h($ag['ObservacoesPos'])) ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
<?php endif ?>

<div class="modal fade" id="modalNovoAgendamento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="novo_agendamento">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Novo agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
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
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Tipo *</label>
                            <select name="tipo" class="form-select" required>
                                <?php foreach ($tiposAgenda as $valor => $label): ?>
                                    <option value="<?= h($valor) ?>" <?= $valor === 'consulta' ? 'selected' : '' ?>><?= h($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Duração</label>
                            <select name="duracao" class="form-select">
                                <option value="15">15 min</option>
                                <option value="30" selected>30 min</option>
                                <option value="45">45 min</option>
                                <option value="60">1 hora</option>
                                <option value="90">1h30</option>
                                <option value="120">2 horas</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ex: Consulta de rotina, Castração…" required maxlength="150">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Data *</label>
                            <input type="date" name="data" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Hora *</label>
                            <input type="time" name="hora" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Veterinário responsável</label>
                        <?= campoPicker('vetResp', 'veterinario', 'Selecione…', 'Buscar veterinário…') ?>
                        <?php if (empty($vets)): ?>
                            <div class="form-text">Nenhum veterinário cadastrado — <a href="<?= BASE ?>/painel/equipe.php">cadastre um primeiro</a>.</div>
                        <?php endif ?>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-calendar-plus me-1"></i> Agendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConcluir" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                <input type="hidden" name="acao" value="concluir">
                <input type="hidden" name="id" id="concluirId">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Concluir: <span id="concluirTitulo"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Observações pós-consulta</label>
                        <textarea name="observacoes_pos" class="form-control" rows="3" placeholder="Como foi, conduta, retorno…"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="criar_clinico" id="concluirCriarClinico" value="1" checked>
                        <label class="form-check-label" for="concluirCriarClinico">
                            Criar registro no histórico clínico do animal com essas observações
                        </label>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Imagens <span class="text-secondary">(opcional, vai junto no registro clínico)</span></label>
                        <input type="file" name="imagens[]" class="form-control" accept="image/png,image/jpeg,image/webp" capture="environment" multiple>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-check2 me-1"></i> Concluir</button>
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

initPicker({
    pickerId: 'animalPicker', triggerId: 'animalTrigger', dropdownId: 'animalDropdown',
    searchId: 'animalSearch', listId: 'animalList', hiddenId: 'inpAnimalId', labelId: 'animalLabel',
    items: ANIMAIS,
    chave: function (a) { return a.id; },
    renderItem: function (a) { return { title: (a.icone ? a.icone + ' ' : '') + a.nome, sub: a.dono }; },
    matches: function (a, q) {
        return a.nome.toLowerCase().indexOf(q) !== -1 || a.dono.toLowerCase().indexOf(q) !== -1;
    },
    vazioMsg: 'Nenhum animal encontrado.',
});

initPicker({
    pickerId: 'vetRespPicker', triggerId: 'vetRespTrigger', dropdownId: 'vetRespDropdown',
    searchId: 'vetRespSearch', listId: 'vetRespList', hiddenId: 'inpvetRespId', labelId: 'vetRespLabel',
    items: VETS,
    chave: function (v) { return v.id; },
    renderItem: function (v) { return { title: v.nome }; },
    matches: function (v, q) { return v.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nenhum veterinário encontrado.',
});

document.querySelectorAll('.btn-concluir').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('concluirId').value = btn.dataset.id;
        document.getElementById('concluirTitulo').textContent = btn.dataset.titulo;
        new bootstrap.Modal(document.getElementById('modalConcluir')).show();
    });
});

document.querySelectorAll('.btn-acao-agendamento').forEach(function (btn) {
    btn.addEventListener('click', function () {
        function executar() {
            fetch(BASE + '/painel/api_agendamento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: btn.dataset.acao, id: btn.dataset.id, csrf_token: '<?= gerarTokenCSRF() ?>' }),
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    location.reload();
                } else {
                    vsToast(d.msg || 'Erro ao atualizar.', 'danger');
                }
            })
            .catch(function () { vsToast('Falha na conexão.', 'danger'); });
        }
        if (btn.dataset.confirm) {
            vsConfirm(btn.dataset.confirm, executar);
        } else {
            executar();
        }
    });
});
</script>

<?php if (($_GET['acao'] ?? '') === 'novo' || $animalPre): ?>
<script>new bootstrap.Modal(document.getElementById('modalNovoAgendamento')).show();</script>
<?php endif ?>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
