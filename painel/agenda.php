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

// Vista salva em cookie — igual padrão da referência (Belos Cílios): lembra a
// última visão escolhida entre visitas, só re-grava quando vem explícita na URL.
if (isset($_GET['vista'])) {
    $vista = $_GET['vista'] === 'mes' ? 'mes' : 'semana';
    setcookie('agenda_vista', $vista, time() + 60 * 60 * 24 * 365, '/');
} else {
    $vista = ($_COOKIE['agenda_vista'] ?? '') === 'mes' ? 'mes' : 'semana';
}

$filtroStatus = trim($_GET['status'] ?? '');
$animalPreId  = trim($_GET['animal'] ?? '');

$mesFiltro = trim($_GET['mes'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $mesFiltro)) {
    $mesFiltro = date('Y-m');
}

$statusValidos = ['pendente' => 1, 'confirmado' => 1, 'concluido' => 1, 'cancelado' => 1, 'faltou' => 1];

// Se veio de um clique num dia da vista mensal, pula pra semana que contém esse dia
$semanaOffset = (int) ($_GET['semana'] ?? 0);
if ($vista === 'semana' && !empty($_GET['dia']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia'])) {
    $segAlvo  = strtotime('monday this week', strtotime($_GET['dia']));
    $segAtual = strtotime('monday this week');
    $semanaOffset = (int) round(($segAlvo - $segAtual) / (7 * 86400));
}

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

    $porDia   = [];
    $mesGrade = [];

    if ($vista === 'semana') {
        // ── Vista semanal: sempre os 7 dias, de segunda a domingo ──
        $inicioPeriodo = strtotime("monday this week +{$semanaOffset} week");
        $fimPeriodo    = strtotime("sunday this week +{$semanaOffset} week");
        $iniSQL        = date('Y-m-d', $inicioPeriodo);
        $fimSQL        = date('Y-m-d', $fimPeriodo);
        $fimSQLNext    = date('Y-m-d', strtotime($fimSQL . ' +1 day'));

        $where  = 'WHERE ag.DataHoraInicio >= :ini AND ag.DataHoraInicio < :fim';
        $params = [':ini' => $iniSQL, ':fim' => $fimSQLNext];
        if (isset($statusValidos[$filtroStatus])) {
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

        foreach ($agendamentos as $ag) {
            $dia = substr($ag['DataHoraInicio'], 0, 10);
            $porDia[$dia][] = $ag;
        }
    } else {
        // ── Vista mensal: grade de calendário com contagem por dia ──
        $inicioMes = $mesFiltro . '-01';
        $fimMes    = date('Y-m-d', strtotime('+1 month', strtotime($inicioMes)));

        $stmt = $pdo->prepare(
            "SELECT DATE(DataHoraInicio) AS Dia, COUNT(*) AS Total,
                    SUM(Status = 'cancelado') AS Cancelados
             FROM Agendamentos
             WHERE DataHoraInicio >= :inicio AND DataHoraInicio < :fim
             GROUP BY DATE(DataHoraInicio)"
        );
        $stmt->execute([':inicio' => $inicioMes, ':fim' => $fimMes]);
        $contagemPorDia = [];
        foreach ($stmt->fetchAll() as $row) {
            $contagemPorDia[$row['Dia']] = $row;
        }

        $primeiroDiaSemana = (int) date('w', strtotime($inicioMes)); // 0 = domingo
        $diasNoMes         = (int) date('t', strtotime($inicioMes));

        $celulas = array_fill(0, $primeiroDiaSemana, null);
        for ($d = 1; $d <= $diasNoMes; $d++) {
            $dataStr   = sprintf('%s-%02d', $mesFiltro, $d);
            $celulas[] = ['data' => $dataStr, 'dia' => $d, 'info' => $contagemPorDia[$dataStr] ?? null];
        }
        while (count($celulas) % 7 !== 0) {
            $celulas[] = null;
        }
        $mesGrade = array_chunk($celulas, 7);
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
    $animais = $vets = $agendamentos = $porDia = $mesGrade = [];
    $animalPre = null;
    // Garante que a vista semanal sempre tem um período pra renderizar,
    // mesmo se a query tiver falhado antes de calculá-lo.
    $inicioPeriodo = $inicioPeriodo ?? strtotime("monday this week +{$semanaOffset} week");
    $fimPeriodo    = $fimPeriodo    ?? strtotime("sunday this week +{$semanaOffset} week");
}

$paginaTitulo = 'Agenda';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<?php
    $mesesPt = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    if ($vista === 'mes') {
        $periodoAnteriorHref = '?vista=mes&mes=' . date('Y-m', strtotime($mesFiltro . '-01 -1 month'));
        $periodoProximoHref  = '?vista=mes&mes=' . date('Y-m', strtotime($mesFiltro . '-01 +1 month'));
        $periodoLabel        = $mesesPt[(int) date('n', strtotime($mesFiltro . '-01'))] . ' de ' . date('Y', strtotime($mesFiltro . '-01'));
        $noPeriodoAtual       = $mesFiltro === date('Y-m');
        $hojeHref             = '?vista=mes&mes=' . date('Y-m');
    } else {
        $periodoAnteriorHref = '?vista=semana&semana=' . ($semanaOffset - 1);
        $periodoProximoHref  = '?vista=semana&semana=' . ($semanaOffset + 1);
        $periodoLabel        = date('d/m', $inicioPeriodo) . ' – ' . date('d/m', $fimPeriodo);
        $noPeriodoAtual       = $semanaOffset === 0;
        $hojeHref             = '?vista=semana&semana=0';
    }
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <h4 class="fw-bold mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-calendar-week" style="color:var(--accent);"></i> Agenda
    </h4>

    <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="vista-switch" role="group" aria-label="Visão da agenda">
            <a href="?vista=semana" class="vista-btn <?= $vista === 'semana' ? 'ativo' : '' ?>">
                <i class="bi bi-list-ul me-1"></i> Semana
            </a>
            <a href="?vista=mes&mes=<?= h($mesFiltro) ?>" class="vista-btn <?= $vista === 'mes' ? 'ativo' : '' ?>">
                <i class="bi bi-grid-3x3-gap me-1"></i> Mês
            </a>
        </div>

        <div class="nav-periodo">
            <a href="<?= $periodoAnteriorHref ?>" class="nav-btn" title="Anterior"><i class="bi bi-chevron-left"></i></a>
            <span class="nav-label"><?= h($periodoLabel) ?></span>
            <a href="<?= $periodoProximoHref ?>" class="nav-btn" title="Próximo"><i class="bi bi-chevron-right"></i></a>
        </div>

        <?php if (!$noPeriodoAtual): ?>
            <a href="<?= $hojeHref ?>" class="btn btn-outline-accent btn-sm">Hoje</a>
        <?php endif ?>

        <?php if ($vista === 'semana'): ?>
            <select class="form-select form-select-sm" style="width:auto;" onchange="location.href='?vista=semana&semana=<?= $semanaOffset ?>&status='+this.value">
                <option value="">Sem cancelados</option>
                <option value="pendente" <?= $filtroStatus === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                <option value="confirmado" <?= $filtroStatus === 'confirmado' ? 'selected' : '' ?>>Confirmados</option>
                <option value="concluido" <?= $filtroStatus === 'concluido' ? 'selected' : '' ?>>Concluídos</option>
                <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
                <option value="faltou" <?= $filtroStatus === 'faltou' ? 'selected' : '' ?>>Faltas</option>
            </select>
        <?php endif ?>

        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoAgendamento">
            <i class="bi bi-calendar-plus me-1"></i> Novo agendamento
        </button>
    </div>
</div>

<?php if ($vista === 'mes'): ?>
    <?php $nomesDias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']; ?>
    <div class="card p-2 mb-4">
        <div class="calendario-grade">
            <?php foreach ($nomesDias as $nd): ?>
                <div class="calendario-cabecalho"><?= $nd ?></div>
            <?php endforeach ?>
            <?php foreach ($mesGrade as $semana): foreach ($semana as $cel): ?>
                <?php if ($cel === null): ?>
                    <div class="calendario-dia calendario-dia-vazio"></div>
                <?php else: ?>
                    <a href="?vista=semana&dia=<?= $cel['data'] ?>"
                       class="calendario-dia text-decoration-none <?= $cel['data'] === date('Y-m-d') ? 'calendario-dia-hoje' : '' ?>">
                        <span class="calendario-dia-numero"><?= $cel['dia'] ?></span>
                        <?php if ($cel['info'] && (int) $cel['info']['Total'] > (int) $cel['info']['Cancelados']): ?>
                            <span class="calendario-dia-badge"><?= (int) $cel['info']['Total'] - (int) $cel['info']['Cancelados'] ?></span>
                        <?php endif ?>
                    </a>
                <?php endif ?>
            <?php endforeach; endforeach ?>
        </div>
    </div>
<?php else: ?>
    <?php
        $diasSemanaPt = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
        for ($d = 0; $d < 7; $d++):
            $ts    = strtotime("+{$d} days", $inicioPeriodo);
            $dia   = date('Y-m-d', $ts);
            $lista = $porDia[$dia] ?? [];
            $eHoje = $dia === date('Y-m-d');
    ?>
        <div class="card mb-3<?= $eHoje ? ' agenda-dia-hoje' : '' ?>">
            <div class="card-header d-flex flex-wrap align-items-center gap-2 px-3 py-2<?= $eHoje ? ' agenda-dia-hoje-header' : '' ?>">
                <span class="fw-semibold<?= $eHoje ? ' text-accent' : '' ?>"><?= $diasSemanaPt[$d] ?></span>
                <span class="text-secondary small"><?= date('d/m', $ts) ?></span>
                <?php if ($eHoje): ?><span class="badge" style="background:var(--accent);">Hoje</span><?php endif ?>
                <span class="badge bg-secondary ms-auto"><?= count($lista) ?> ag.</span>
            </div>
            <?php if (empty($lista)): ?>
                <div class="text-center py-3 text-secondary small">Sem agendamentos</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($lista as $ag): ?>
                        <li class="list-group-item px-3 py-2" data-id-agendamento="<?= h($ag['IDAgendamento']) ?>">
                            <div class="d-flex align-items-center gap-2 gap-md-3 flex-wrap">
                                <span class="fw-bold text-accent" style="min-width:42px;"><?= date('H:i', strtotime($ag['DataHoraInicio'])) ?></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <span class="badge" style="background:var(--accent-light);color:var(--accent);"><?= h($tiposAgenda[$ag['Tipo']] ?? $ag['Tipo']) ?></span>
                                        <?= labelStatusAgendamento($ag['Status']) ?>
                                        <span class="fw-medium"><?= h($ag['IconeEspecie']) ?> <?= h($ag['NomeAnimal']) ?></span>
                                        <span class="text-secondary small">— <?= h($ag['NomeDono']) ?></span>
                                    </div>
                                    <span class="text-secondary small d-block">
                                        <?= h($ag['Titulo']) ?><?= $ag['NomeVeterinario'] ? ' · ' . h($ag['NomeVeterinario']) : ' · sem veterinário definido' ?>
                                    </span>
                                    <?php if ($ag['Status'] === 'concluido' && $ag['ObservacoesPos']): ?>
                                        <span class="text-secondary small d-block mt-1"><strong>Pós-consulta:</strong> <?= nl2br(h($ag['ObservacoesPos'])) ?></span>
                                    <?php endif ?>
                                </div>
                                <div class="d-flex gap-1 flex-wrap flex-shrink-0">
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
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    <?php endfor ?>
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
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
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
