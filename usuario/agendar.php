<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('cliente');

$uid = $_SESSION['usuario_id'];

// Pedido de Agendamento — o cliente escolhe animal/serviço/data/horário e
// manda um PEDIDO (Status = 'pendente'), não um agendamento já confirmado.
// A equipe revisa e confirma pela Agenda (painel/agenda.php já tem o botão
// "Confirmar" pronto — ver painel/api_agendamento.php) ou cancela se não
// puder atender. Diferente de um agendamento criado direto pela equipe
// (que já nasce 'confirmado' — ver painel/agenda.php), esse SEMPRE nasce
// 'pendente', porque quem escolheu o horário foi o cliente, sem ninguém
// da clínica ter olhado ainda.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/usuario/agendar.php', 'Token inválido.', 'danger');
    }

    $fkAnimal = trim($_POST['animal'] ?? '');
    $fkVet    = trim($_POST['veterinario'] ?? '');
    $tipo     = trim($_POST['tipo'] ?? '');
    $titulo   = trim($_POST['titulo'] ?? '');
    $duracao  = max(15, (int) ($_POST['duracao'] ?? 30));
    $data     = trim($_POST['data'] ?? '');
    $hora     = trim($_POST['hora'] ?? '');
    $obs      = trim($_POST['observacoes'] ?? '');

    $tiposValidos = array_keys(tiposAgendaMap());
    if ($fkAnimal === '' || $titulo === '' || $data === '' || $hora === '' || !in_array($tipo, $tiposValidos, true)) {
        redirecionarComMensagem(BASE . '/usuario/agendar.php', 'Preencha animal, serviço, data e horário.', 'warning');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !preg_match('/^\d{2}:\d{2}$/', $hora)) {
        redirecionarComMensagem(BASE . '/usuario/agendar.php', 'Data ou horário inválido.', 'warning');
    }

    $inicio = $data . ' ' . $hora . ':00';
    $ts     = strtotime($inicio);
    if (!$ts || $ts < strtotime('+55 minutes')) {
        redirecionarComMensagem(BASE . '/usuario/agendar.php', 'Escolha um horário pelo menos 1h à frente.', 'warning');
    }
    $fim = date('Y-m-d H:i:s', $ts + $duracao * 60);

    try {
        // Confere que o animal é mesmo desse cliente antes de aceitar o
        // pedido — sem isso, dava pra agendar em nome de qualquer animal
        // só sabendo o UUID (mesma checagem de posse que já existe pra
        // cancelar, ver processa_agendamento.php).
        $stmt = $pdo->prepare('SELECT a.Nome, u.Nome AS NomeDono, u.Telefone FROM Animais a JOIN Usuarios u ON u.IDUsuario = a.FKDono WHERE a.IDAnimal = :id AND a.FKDono = :uid AND a.Ativo = 1 LIMIT 1');
        $stmt->execute([':id' => $fkAnimal, ':uid' => $uid]);
        $animal = $stmt->fetch();
        if (!$animal) {
            redirecionarComMensagem(BASE . '/usuario/agendar.php', 'Animal não encontrado.', 'warning');
        }
        if (!veterinarioValido($pdo, $fkVet)) {
            redirecionarComMensagem(BASE . '/usuario/agendar.php', 'Veterinário inválido.', 'warning');
        }

        // Reconfere o conflito no exato momento de gravar — a lista de
        // horários que o cliente viu na tela pode ter ficado desatualizada
        // (outro pedido confirmado, ou outro cliente enviando o mesmo
        // horário ao mesmo tempo). Só reconfere se um veterinário
        // específico foi escolhido; "sem preferência" não tem agenda
        // própria pra checar ainda (a equipe resolve isso ao confirmar).
        travarAgendaVet($pdo, $fkVet ?: null);
        if ($fkVet !== '' && agendamentoConflita($pdo, $fkVet, $inicio, $fim)) {
            destravarAgendaVet($pdo, $fkVet);
            redirecionarComMensagem(BASE . '/usuario/agendar.php', 'Esse horário acabou de ficar indisponível — escolha outro.', 'warning');
        }

        $novoId = gerarUuid();
        $pdo->prepare(
            'INSERT INTO Agendamentos (IDAgendamento, FKAnimal, FKVeterinario, Tipo, Titulo, DataHoraInicio, DataHoraFim, Observacoes, Status, CriadoPor)
             VALUES (:id, :animal, :vet, :tipo, :titulo, :inicio, :fim, :obs, \'pendente\', \'cliente\')'
        )->execute([
            ':id'     => $novoId,
            ':animal' => $fkAnimal,
            ':vet'    => $fkVet ?: null,
            ':tipo'   => $tipo,
            ':titulo' => $titulo,
            ':inicio' => $inicio,
            ':fim'    => $fim,
            ':obs'    => $obs ?: null,
        ]);
        destravarAgendaVet($pdo, $fkVet ?: null);
        registrarEventoAgendamento($pdo, $novoId, 'criado', 'Pedido feito pelo cliente — aguardando confirmação.');

        // Avisa a CLÍNICA (não o cliente — ele já está vendo a confirmação
        // na tela) que chegou um pedido novo pra revisar.
        $telClinica = getConfig($pdo, 'telefone_clinica', '');
        if ($telClinica !== '') {
            $msg = "📋 Novo pedido de agendamento!\n{$animal['NomeDono']} — {$animal['Nome']}\n"
                 . (tiposAgendaMap()[$tipo] ?? $tipo) . ": {$titulo}\n"
                 . formatarData($inicio) . ' às ' . date('H:i', $ts)
                 . "\n\nRevise e confirme pela Agenda.";
            enviarWhatsApp(waNumero($telClinica), $msg);
        }

        redirecionarComMensagem(BASE . '/usuario/meus_agendamentos.php', 'Pedido enviado! A clínica vai confirmar em breve.', 'success');
    } catch (PDOException $e) {
        error_log('[Agendar] ' . $e->getMessage());
        destravarAgendaVet($pdo, $fkVet ?: null);
        redirecionarComMensagem(BASE . '/usuario/agendar.php', 'Erro ao enviar o pedido.', 'danger');
    }
}

try {
    $animais = $pdo->prepare(
        "SELECT a.IDAnimal, a.Nome, a.FKEspecie, e.Icone AS IconeEspecie
         FROM Animais a JOIN Especies e ON e.IDEspecie = a.FKEspecie
         WHERE a.FKDono = :uid AND a.Ativo = 1 ORDER BY a.Nome ASC"
    );
    $animais->execute([':uid' => $uid]);
    $animais = $animais->fetchAll();

    $catalogo = catalogoAgendamento($pdo);
    $vets     = listarVeterinariosAtivos($pdo);
} catch (PDOException $e) {
    error_log('[AgendarLista] ' . $e->getMessage());
    $animais = $catalogo = $vets = [];
}

$tiposAgenda = tiposAgendaMap();

$paginaTitulo = 'Pedido de Agendamento';
$areaAtual    = 'cliente';
require_once __DIR__ . '/../geral/header.php';
?>

<h4 class="fw-bold mb-1"><i class="bi bi-calendar-plus me-2 text-accent"></i>Pedido de Agendamento</h4>
<p class="text-secondary small mb-4">Escolha o animal, o que precisa e um horário — a clínica confirma em seguida.</p>

<?php if (empty($animais)): ?>
    <div class="card text-center py-5 text-secondary">
        <i class="bi bi-emoji-smile fs-1 d-block mb-2 opacity-25"></i>
        <p class="mb-0">Você ainda não tem nenhum animal cadastrado.</p>
        <p class="small">Fale com a clínica para cadastrar seu animal antes de pedir um agendamento.</p>
    </div>
<?php else: ?>
<div class="card card-form-sequencial p-4">
    <form method="POST" id="formAgendar">
        <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
        <input type="hidden" name="tipo" id="inpTipo">
        <input type="hidden" name="titulo" id="inpTitulo">
        <input type="hidden" name="duracao" id="inpDuracao" value="30">
        <input type="hidden" name="hora" id="inpHora">

        <div class="mb-3 campo-sequencial" id="passo1">
            <label class="form-label"><span class="badge-passo">1</span> Animal *</label>
            <?= campoPicker('agAnimal', 'animal', 'Selecione…', 'Buscar animal…', obrigatorio: true, comBusca: false) ?>
        </div>

        <div class="mb-3 campo-sequencial" id="passo2" hidden>
            <label class="form-label"><span class="badge-passo">2</span> O que você precisa? *</label>
            <?= campoPicker('agServico', 'servico_ref', 'Selecione…', 'Buscar…', obrigatorio: true) ?>
            <div id="blocoOutroServico" class="mt-2" hidden>
                <input type="text" class="form-control" id="inpOutroTitulo" placeholder="Descreva o que você precisa" maxlength="150">
            </div>
        </div>

        <div class="mb-3 campo-sequencial" id="passo3" hidden>
            <label class="form-label"><span class="badge-passo">3</span> Veterinário <span class="text-secondary">(opcional)</span></label>
            <?= campoPicker('agVet', 'veterinario', 'Sem preferência', '', comBusca: false) ?>
        </div>

        <div class="mb-3 campo-sequencial" id="passo4" hidden>
            <label class="form-label"><span class="badge-passo">4</span> Data *</label>
            <input type="date" class="form-control" name="data" id="inpData" required
                min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>">
        </div>

        <div class="mb-3 campo-sequencial" id="passo5" hidden>
            <label class="form-label"><span class="badge-passo">5</span> Horário *</label>
            <div id="blocoHorarios" class="d-flex flex-wrap gap-2">
                <span class="text-secondary small">Escolha uma data pra ver os horários livres.</span>
            </div>
        </div>

        <div class="mb-4 campo-sequencial" id="passo6" hidden>
            <label class="form-label"><span class="badge-passo">6</span> Observações <span class="text-secondary">(opcional)</span></label>
            <textarea name="observacoes" class="form-control" rows="2" maxlength="500" placeholder="Algo que a clínica precise saber antes de confirmar…"></textarea>
        </div>

        <button type="submit" class="btn btn-accent w-100 campo-sequencial" id="btnEnviarPedido" hidden disabled>
            <i class="bi bi-send-fill me-1"></i> Enviar pedido
        </button>
    </form>
</div>

<script>
var ANIMAIS = <?= json_encode(array_map(fn($a) => [
    'id' => $a['IDAnimal'], 'nome' => $a['Nome'], 'icone' => $a['IconeEspecie'], 'especie' => $a['FKEspecie'],
], $animais), JSON_UNESCAPED_UNICODE) ?>;

var CATALOGO = <?= json_encode(array_map(fn($c) => [
    'id' => $c['IDTipo'], 'categoria' => $c['Categoria'], 'nome' => $c['Nome'],
    'duracao' => (int) $c['DuracaoPadraoMinutos'], 'especie' => $c['FKEspecie'],
], $catalogo), JSON_UNESCAPED_UNICODE) ?>;
CATALOGO.push({ id: '__outro__', categoria: 'outro', nome: 'Outro (não está na lista)', duracao: 30, especie: null });

// Filtra o catálogo pela espécie do animal escolhido — vacina de gato não
// pode aparecer pra quem tem um cachorro. "Outro" e qualquer item sem
// espécie fixada (Procedimento em geral) servem pra qualquer bicho. Mesma
// ideia de vacinasParaEspecie() em painel/registrar_vacina.php.
function catalogoParaEspecie(especie) {
    return CATALOGO.filter(function (c) { return !especie || !c.especie || c.especie === especie; });
}

var VETS = <?= json_encode(array_map(fn($v) => ['id' => $v['IDUsuario'], 'nome' => $v['Nome']], $vets), JSON_UNESCAPED_UNICODE) ?>;

// Seleção sequencial (ver PADROES_DESENVOLVIMENTO.md 20.7): só mostra o
// próximo campo depois do atual estar respondido, em vez da tela inteira
// de uma vez — cada escolha aqui restringe/depende da anterior (animal →
// o que precisa → [vet/data] → horário), então faz sentido pedir uma coisa
// de cada vez. Passo 3 (veterinário) e 4 (data) revelam juntos porque vet
// é opcional — não faz sentido travar a data esperando uma escolha que a
// pessoa pode legitimamente pular.
function revelarPasso(id) {
    var el = document.getElementById(id);
    if (el.hidden) {
        el.hidden = false;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

initPicker({
    pickerId: 'agAnimalPicker', triggerId: 'agAnimalTrigger', dropdownId: 'agAnimalDropdown',
    searchId: 'agAnimalSearch', listId: 'agAnimalList', hiddenId: 'inpagAnimalId', labelId: 'agAnimalLabel',
    items: ANIMAIS,
    chave: function (a) { return a.id; },
    renderItem: function (a) { return { title: a.nome, icon: a.icone }; },
    matches: function (a, q) { return a.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nenhum animal encontrado.',
    onSelect: function (a) {
        agServicoPk.setItems(catalogoParaEspecie(a.especie), 'Selecione…');
        revelarPasso('passo2');
        setTimeout(function () { agServicoPk.abrir(); }, 50);
    },
});

initPicker({
    pickerId: 'agVetPicker', triggerId: 'agVetTrigger', dropdownId: 'agVetDropdown',
    searchId: 'agVetSearch', listId: 'agVetList', hiddenId: 'inpagVetId', labelId: 'agVetLabel',
    items: VETS,
    chave: function (v) { return v.id; },
    renderItem: function (v) { return { title: v.nome }; },
    matches: function (v, q) { return v.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nenhum veterinário encontrado.',
    onSelect: function () { buscarHorarios(); },
});

var inpTipo    = document.getElementById('inpTipo');
var inpTitulo  = document.getElementById('inpTitulo');
var inpDuracao = document.getElementById('inpDuracao');
var blocoOutro = document.getElementById('blocoOutroServico');
var inpOutroTitulo = document.getElementById('inpOutroTitulo');

var agServicoPk = initPicker({
    pickerId: 'agServicoPicker', triggerId: 'agServicoTrigger', dropdownId: 'agServicoDropdown',
    searchId: 'agServicoSearch', listId: 'agServicoList', hiddenId: 'inpagServicoId', labelId: 'agServicoLabel',
    items: CATALOGO,
    chave: function (c) { return c.id; },
    renderItem: function (c) { return { title: c.nome }; },
    matches: function (c, q) { return c.nome.toLowerCase().indexOf(q) !== -1; },
    vazioMsg: 'Nada encontrado.',
    onSelect: function (c) {
        inpTipo.value    = c.categoria;
        inpDuracao.value = c.duracao;
        var ehOutro = c.id === '__outro__';
        blocoOutro.hidden = !ehOutro;
        inpTitulo.value = ehOutro ? '' : c.nome;
        if (ehOutro) { inpOutroTitulo.focus(); }
        revelarPasso('passo3');
        revelarPasso('passo4');
        buscarHorarios();
    },
});
inpOutroTitulo.addEventListener('input', function () {
    inpTitulo.value = inpOutroTitulo.value;
    atualizarBotaoEnviar();
});

var inpData        = document.getElementById('inpData');
var blocoHorarios  = document.getElementById('blocoHorarios');
var inpHora        = document.getElementById('inpHora');
var btnEnviar       = document.getElementById('btnEnviarPedido');

function atualizarBotaoEnviar() {
    var ok = inpTipo.value && inpTitulo.value.trim() && inpData.value && inpHora.value;
    btnEnviar.disabled = !ok;
}

function buscarHorarios() {
    inpHora.value = '';
    atualizarBotaoEnviar();
    if (!inpData.value || !inpDuracao.value) {
        blocoHorarios.innerHTML = '<span class="text-secondary small">Escolha uma data pra ver os horários livres.</span>';
        return;
    }
    revelarPasso('passo5');
    blocoHorarios.innerHTML = '<span class="text-secondary small"><i class="bi bi-hourglass-split me-1"></i>Buscando horários…</span>';

    var vetId = document.getElementById('inpagVetId').value || '';
    var url = '<?= BASE ?>/usuario/api_horarios_disponiveis.php?data=' + encodeURIComponent(inpData.value)
        + '&duracao=' + encodeURIComponent(inpDuracao.value) + '&veterinario=' + encodeURIComponent(vetId);

    fetch(url).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.horarios || !d.horarios.length) {
            blocoHorarios.innerHTML = '<span class="text-secondary small">Nenhum horário livre nesse dia — tente outra data.</span>';
            return;
        }
        blocoHorarios.innerHTML = '';
        d.horarios.forEach(function (h) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-accent';
            btn.textContent = h;
            btn.addEventListener('click', function () {
                blocoHorarios.querySelectorAll('.btn').forEach(function (b) { b.classList.remove('btn-accent'); b.classList.add('btn-outline-accent'); });
                btn.classList.remove('btn-outline-accent');
                btn.classList.add('btn-accent');
                inpHora.value = h;
                revelarPasso('passo6');
                revelarPasso('btnEnviarPedido');
                atualizarBotaoEnviar();
            });
            blocoHorarios.appendChild(btn);
        });
    }).catch(function () {
        blocoHorarios.innerHTML = '<span class="text-danger small">Falha ao buscar horários — tente de novo.</span>';
    });
}

inpData.addEventListener('change', buscarHorarios);
</script>
<?php endif ?>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
