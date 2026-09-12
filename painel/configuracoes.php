<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin');

$diasSemana = [
    'horario_domingo'   => 'Domingo',
    'horario_segunda'   => 'Segunda-feira',
    'horario_terca'     => 'Terça-feira',
    'horario_quarta'    => 'Quarta-feira',
    'horario_quinta'    => 'Quinta-feira',
    'horario_sexta'     => 'Sexta-feira',
    'horario_sabado'    => 'Sábado',
];

// O valor gravado continua sendo um texto único ("07:30 - 18:00",
// "Fechado" ou vazio) — só a TELA passa a usar campo de hora de verdade
// em vez de digitar isso à mão. Decompõe esse texto em abre/fecha/fechado
// pra preencher os 3 campos; a composição inversa (na hora de salvar) está
// logo abaixo, antes do loop que grava cada config.
function decomporHorario(string $valor): array
{
    if ($valor === '') {
        return ['abre' => '', 'fecha' => '', 'fechado' => false];
    }
    if (preg_match('/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $valor, $m)) {
        return ['abre' => $m[1], 'fecha' => $m[2], 'fechado' => false];
    }
    // Qualquer outro texto não vazio (ex.: "Fechado", ou algo digitado à
    // mão antes desse campo virar estruturado, tipo "8h às 18h") não dá
    // pra decompor com segurança em abre/fecha — cai como "Fechado"
    // marcado, e quem administra corrige na hora se não for bem isso.
    return ['abre' => '', 'fecha' => '', 'fechado' => true];
}

$campos = [
    'nome_clinica', 'telefone_clinica', 'email_clinica', 'instagram_clinica',
    'endereco_rua', 'endereco_numero', 'endereco_complemento', 'endereco_bairro', 'endereco_cidade', 'endereco_uf', 'endereco_cep',
    ...array_keys($diasSemana),
    'msg_vacina_semana', 'msg_vacina_dia', 'msg_agendamento_criado', 'msg_cancelamento', 'msg_remarcacao', 'msg_retorno',
    'whatsapp_modo_teste', 'whatsapp_numero_teste',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/configuracoes.php', 'Token inválido.', 'danger');
    }

    $emailClinica = trim($_POST['email_clinica'] ?? '');
    if ($emailClinica !== '' && !filter_var($emailClinica, FILTER_VALIDATE_EMAIL)) {
        redirecionarComMensagem(BASE . '/painel/configuracoes.php', 'E-mail da clínica inválido.', 'warning');
    }

    // O form manda 3 campos por dia (_abre, _fecha, _fechado) — recompõe
    // no texto único de sempre ANTES do loop genérico abaixo, que continua
    // gravando só pela chave "horario_X" original sem precisar saber que
    // isso mudou de forma na tela. "Fechado" marcado vence mesmo que sobre
    // algum horário preenchido (campo desabilitado no HTML não é enviado
    // de qualquer forma, mas não custa checar aqui também); só grava um
    // intervalo quando as DUAS pontas vierem preenchidas — só uma metade
    // preenchida vira "não mostrar esse dia", não um horário incompleto.
    foreach ($diasSemana as $chave => $rotulo) {
        if (!empty($_POST[$chave . '_fechado'])) {
            $_POST[$chave] = 'Fechado';
            continue;
        }
        $abre  = trim($_POST[$chave . '_abre'] ?? '');
        $fecha = trim($_POST[$chave . '_fecha'] ?? '');
        $_POST[$chave] = ($abre !== '' && $fecha !== '') ? "{$abre} - {$fecha}" : '';
    }

    try {
        foreach ($campos as $c) {
            $valor = trim($_POST[$c] ?? '');

            // Telefones sempre passam pelo mesmo normalizador usado em todo
            // o resto do sistema (Usuarios.Telefone) — sem isso, um número
            // salvo só com a máscara ("(11) 99999-8888") funciona por
            // acidente hoje (waNumero() reprocessa na hora de usar), mas
            // fica inconsistente com como tudo mais é guardado.
            if (in_array($c, ['telefone_clinica', 'whatsapp_numero_teste'], true) && $valor !== '') {
                $valor = sanitizarTelefone($valor) ?? $valor;
            }
            // Aceita @handle, link completo ou só o nome de usuário — guarda
            // sempre só o handle puro, pra montar o link igual em qualquer
            // lugar que for exibido.
            if ($c === 'instagram_clinica' && $valor !== '') {
                $valor = preg_replace('#^(https?://)?(www\.)?instagram\.com/#i', '', $valor);
                $valor = ltrim($valor, '@');
                $valor = rtrim($valor, '/');
            }
            if ($c === 'endereco_uf' && $valor !== '') {
                $valor = mb_strtoupper(mb_substr($valor, 0, 2));
            }

            setConfig($pdo, $c, $valor);
        }
        redirecionarComMensagem(BASE . '/painel/configuracoes.php', 'Configurações salvas com sucesso!', 'success');
    } catch (PDOException $e) {
        error_log('[Configuracoes] ' . $e->getMessage());
        redirecionarComMensagem(BASE . '/painel/configuracoes.php', 'Erro ao salvar configurações.', 'danger');
    }
}

$valores = [];
foreach ($campos as $c) {
    $valores[$c] = getConfig($pdo, $c, '');
}
// Campo de template vazio mostra o texto padrão real (não um placeholder
// genérico) — dá pra editar em cima dele. Salvando sem mexer, grava esse
// texto explicitamente; apagando tudo e salvando em branco, volta a usar
// o padrão automaticamente (nada muda nesse mecanismo, só o que aparece
// na tela antes de mexer).
foreach (templatesWhatsAppPadrao() as $chave => $texto) {
    if ($valores[$chave] === '') {
        $valores[$chave] = $texto;
    }
}

$paginaTitulo = 'Configurações';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<h4 class="fw-bold mb-4"><i class="bi bi-gear me-2 text-accent"></i>Configurações</h4>

<form method="POST" id="formConfiguracoes">
<input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-4 mb-4">
            <h6 class="fw-semibold mb-3"><i class="bi bi-hospital me-2 text-accent"></i>Dados da clínica</h6>
            <div class="mb-3">
                <label class="form-label">Nome da clínica</label>
                <input type="text" name="nome_clinica" class="form-control" value="<?= h($valores['nome_clinica']) ?>">
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label">WhatsApp</label>
                    <input type="tel" name="telefone_clinica" class="form-control" data-mask="tel"
                           value="<?= h(formatarTelefoneExibicao($valores['telefone_clinica'])) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email_clinica" class="form-control" value="<?= h($valores['email_clinica']) ?>">
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label">Instagram</label>
                <div class="input-group">
                    <span class="input-group-text">@</span>
                    <input type="text" name="instagram_clinica" class="form-control" placeholder="minhaclinica"
                           value="<?= h($valores['instagram_clinica']) ?>">
                </div>
            </div>
        </div>

        <div class="card p-4 mb-4">
            <h6 class="fw-semibold mb-3"><i class="bi bi-geo-alt me-2 text-accent"></i>Endereço</h6>
            <div class="row g-2 mb-2">
                <div class="col-8">
                    <label class="form-label">Rua</label>
                    <input type="text" name="endereco_rua" class="form-control" value="<?= h($valores['endereco_rua']) ?>">
                </div>
                <div class="col-4">
                    <label class="form-label">Número</label>
                    <input type="text" name="endereco_numero" class="form-control" value="<?= h($valores['endereco_numero']) ?>">
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Complemento <span class="text-secondary">(opcional)</span></label>
                <input type="text" name="endereco_complemento" class="form-control" placeholder="Sala, bloco, referência..." value="<?= h($valores['endereco_complemento']) ?>">
            </div>
            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="endereco_bairro" class="form-control" value="<?= h($valores['endereco_bairro']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">CEP</label>
                    <input type="text" name="endereco_cep" class="form-control" placeholder="00000-000" value="<?= h($valores['endereco_cep']) ?>">
                </div>
            </div>
            <div class="row g-2 mb-0">
                <div class="col-9">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="endereco_cidade" class="form-control" value="<?= h($valores['endereco_cidade']) ?>">
                </div>
                <div class="col-3">
                    <label class="form-label">UF</label>
                    <input type="text" name="endereco_uf" class="form-control" maxlength="2" style="text-transform:uppercase;" value="<?= h($valores['endereco_uf']) ?>">
                </div>
            </div>
        </div>

        <div class="card p-4 mb-4">
            <h6 class="fw-semibold mb-3"><i class="bi bi-clock me-2 text-accent"></i>Horário de funcionamento</h6>
            <p class="small text-secondary mb-3">Deixe abre/fecha em branco (e "Fechado" desmarcado) pra não mostrar esse dia.</p>
            <?php foreach ($diasSemana as $chave => $rotulo): $hr = decomporHorario($valores[$chave]); ?>
                <div class="row g-2 mb-2 align-items-center campo-dia-horario" data-dia="<?= h($chave) ?>">
                    <div class="col-4 col-sm-3"><label class="form-label mb-0"><?= h($rotulo) ?></label></div>
                    <div class="col-3 col-sm-3">
                        <input type="time" name="<?= h($chave) ?>_abre" class="form-control form-control-sm campo-horario-hora" value="<?= h($hr['abre']) ?>" <?= $hr['fechado'] ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-3 col-sm-3">
                        <input type="time" name="<?= h($chave) ?>_fecha" class="form-control form-control-sm campo-horario-hora" value="<?= h($hr['fecha']) ?>" <?= $hr['fechado'] ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-2 col-sm-3 form-check d-flex align-items-center mb-0">
                        <input class="form-check-input me-1 campo-horario-fechado" type="checkbox" name="<?= h($chave) ?>_fechado" id="chk<?= h($chave) ?>" value="1" <?= $hr['fechado'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="chk<?= h($chave) ?>">Fechado</label>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card p-4 mb-4">
            <h6 class="fw-semibold mb-2"><i class="bi bi-whatsapp me-2 text-accent"></i>Templates de WhatsApp</h6>
            <p class="small text-secondary mb-3">
                Cada campo já vem com o texto padrão do sistema — edite à vontade.
                Apagando tudo e salvando em branco, volta a usar o padrão automaticamente.
            </p>

            <div class="mb-3 pb-3 border-bottom">
                <label class="form-label mb-1">Agendamento criado</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_cliente}</code> <code>{nome_animal}</code> <code>{tipo}</code> <code>{titulo}</code> <code>{data}</code> <code>{hora}</code>
                </p>
                <textarea name="msg_agendamento_criado" class="form-control" rows="3"><?= h($valores['msg_agendamento_criado']) ?></textarea>
            </div>

            <div class="mb-3 pb-3 border-bottom">
                <label class="form-label mb-1">Cancelamento</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_animal}</code> <code>{tipo}</code> <code>{titulo}</code> <code>{data}</code> <code>{hora}</code>
                </p>
                <textarea name="msg_cancelamento" class="form-control" rows="3"><?= h($valores['msg_cancelamento']) ?></textarea>
            </div>

            <div class="mb-3 pb-3 border-bottom">
                <label class="form-label mb-1">Remarcação</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_cliente}</code> <code>{nome_animal}</code> <code>{tipo}</code> <code>{titulo}</code> <code>{data}</code> <code>{hora}</code>
                </p>
                <textarea name="msg_remarcacao" class="form-control" rows="3"><?= h($valores['msg_remarcacao']) ?></textarea>
            </div>

            <div class="mb-3 pb-3 border-bottom">
                <label class="form-label mb-1">Retorno</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_cliente}</code> <code>{nome_animal}</code> <code>{tipo}</code> <code>{titulo}</code> <code>{data}</code> <code>{hora}</code>
                </p>
                <textarea name="msg_retorno" class="form-control" rows="3"><?= h($valores['msg_retorno']) ?></textarea>
            </div>

            <div class="mb-3 pb-3 border-bottom">
                <label class="form-label mb-1">Aviso de vacina — 7 dias antes do vencimento</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_dono}</code> <code>{nome_animal}</code> <code>{vacina}</code> <code>{data}</code>
                </p>
                <textarea name="msg_vacina_semana" class="form-control" rows="3"><?= h($valores['msg_vacina_semana']) ?></textarea>
            </div>

            <div class="mb-0">
                <label class="form-label mb-1">Aviso de vacina — no dia do vencimento</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_dono}</code> <code>{nome_animal}</code> <code>{vacina}</code> <code>{data}</code>
                </p>
                <textarea name="msg_vacina_dia" class="form-control" rows="3"><?= h($valores['msg_vacina_dia']) ?></textarea>
            </div>
        </div>

    </div>
</div>

<div class="d-grid col-lg-6">
    <button type="submit" class="btn btn-accent btn-lg"><i class="bi bi-check2 me-2"></i> Salvar configurações</button>
</div>
</form>

<?php
    // O switch e o número ficam fisicamente fora do <form> principal (pra
    // caber no MESMO card que os botões de demonstração — que postam pra um
    // endpoint diferente, e form dentro de form não existe em HTML), mas
    // continuam salvando junto com "Salvar configurações" através do
    // atributo form="formConfiguracoes", que liga um campo a um form em
    // outro lugar da página.
    $modoTesteLigado = $valores['whatsapp_modo_teste'] === '1';
    $temNumeroTeste  = $valores['whatsapp_numero_teste'] !== '';
    $demoHabilitado  = $modoTesteLigado && $temNumeroTeste;
?>
<div class="row g-4">
    <div class="col-lg-6 offset-lg-6">
        <div class="card p-4 mb-4">
            <h6 class="fw-semibold mb-2"><i class="bi bi-bug me-2 text-accent"></i>Modo de teste do WhatsApp</h6>
            <p class="small text-secondary">
                Enquanto ligado, <strong>toda</strong> mensagem de WhatsApp do sistema (agendamento criado,
                cancelamento, lembrete de vacina...) é redirecionada pra este número, não importa
                pra quem o sistema mandaria de verdade. Use pra validar os avisos sem risco de
                mensagem cair em cliente de verdade.
            </p>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_modo_teste"
                       id="whatsappModoTeste" value="1" form="formConfiguracoes" <?= $modoTesteLigado ? 'checked' : '' ?>>
                <label class="form-check-label" for="whatsappModoTeste">Modo de teste ligado</label>
            </div>
            <div class="mb-0">
                <label class="form-label">Número de teste</label>
                <input type="tel" name="whatsapp_numero_teste" id="whatsappNumeroTeste" class="form-control" data-mask="tel"
                       form="formConfiguracoes" value="<?= h(formatarTelefoneExibicao($valores['whatsapp_numero_teste'])) ?>">
                <div class="form-text">Esses dois campos salvam junto com "Salvar configurações" acima.</div>
            </div>

            <hr class="my-4">

            <h6 class="fw-semibold mb-2"><i class="bi bi-play-circle me-2 text-accent"></i>Demonstração ao vivo</h6>
            <p class="small text-secondary">
                Dispara pro <strong>número de teste</strong> acima uma mensagem de exemplo, igual à
                que um cliente de verdade recebe — pra mostrar o sistema funcionando numa reunião,
                sem precisar de um agendamento real.
            </p>
            <p class="small mb-3" id="demoAviso" style="color:var(--cor-perigo);<?= $demoHabilitado ? 'display:none;' : '' ?>">
                <i class="bi bi-exclamation-circle me-1"></i>
                <span id="demoAvisoTexto"><?= !$modoTesteLigado ? 'Ligue o "Modo de teste" acima pra habilitar.' : 'Configure um número de teste acima pra habilitar.' ?></span>
            </p>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="<?= BASE ?>/painel/demo_whatsapp.php">
                    <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                    <input type="hidden" name="cenario" value="agendamento">
                    <button type="submit" class="btn btn-outline-accent btn-demo-whatsapp" <?= $demoHabilitado ? '' : 'disabled' ?>>
                        <i class="bi bi-calendar-plus me-1"></i> Agendamento criado
                    </button>
                </form>
                <form method="POST" action="<?= BASE ?>/painel/demo_whatsapp.php">
                    <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                    <input type="hidden" name="cenario" value="cancelamento">
                    <button type="submit" class="btn btn-outline-accent btn-demo-whatsapp" <?= $demoHabilitado ? '' : 'disabled' ?>>
                        <i class="bi bi-calendar-x me-1"></i> Cancelamento
                    </button>
                </form>
                <form method="POST" action="<?= BASE ?>/painel/demo_whatsapp.php">
                    <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                    <input type="hidden" name="cenario" value="remarcacao">
                    <button type="submit" class="btn btn-outline-accent btn-demo-whatsapp" <?= $demoHabilitado ? '' : 'disabled' ?>>
                        <i class="bi bi-arrow-repeat me-1"></i> Remarcação
                    </button>
                </form>
                <form method="POST" action="<?= BASE ?>/painel/demo_whatsapp.php">
                    <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                    <input type="hidden" name="cenario" value="retorno">
                    <button type="submit" class="btn btn-outline-accent btn-demo-whatsapp" <?= $demoHabilitado ? '' : 'disabled' ?>>
                        <i class="bi bi-arrow-return-right me-1"></i> Retorno
                    </button>
                </form>
                <form method="POST" action="<?= BASE ?>/painel/demo_whatsapp.php">
                    <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                    <input type="hidden" name="cenario" value="vacina">
                    <button type="submit" class="btn btn-outline-accent btn-demo-whatsapp" <?= $demoHabilitado ? '' : 'disabled' ?>>
                        <i class="bi bi-shield-plus me-1"></i> Lembrete de vacina
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Salvar aqui é um form comum (POST + redirect de volta pra essa mesma
// página) — sem isso, a página inteira recarregava do topo, e numa tela
// longa com vários cards a pessoa perdia de vista o campo que acabou de
// editar. A restauração já existe globalmente (footer.php); só faltava
// gravar a posição antes de sair da página.
document.getElementById('formConfiguracoes').addEventListener('submit', function () {
    try { sessionStorage.setItem('vsScrollY', String(window.scrollY)); } catch (e) {}
});

// Marcar "Fechado" desabilita os dois campos de hora daquele dia — evita
// mandar um horário preenchido junto com "Fechado" marcado (o servidor já
// prioriza "Fechado" de qualquer forma, isso aqui é só deixar claro na
// tela que os campos não valem enquanto marcado). Um listener genérico
// (não um por dia) — sete dias, mesma lógica.
document.querySelectorAll('.campo-dia-horario').forEach(function (linha) {
    var chk    = linha.querySelector('.campo-horario-fechado');
    var campos = linha.querySelectorAll('.campo-horario-hora');
    chk.addEventListener('change', function () {
        campos.forEach(function (c) { c.disabled = chk.checked; });
    });
});

// Os botões de demonstração só fazem sentido com o modo de teste ligado E
// um número configurado (é pra lá que toda mensagem de demo vai) — sem
// isso, clicar só voltava com um aviso de erro. Desabilita direto na tela,
// e já reage a mexer no switch ou no número, sem precisar salvar e
// recarregar pra destravar.
(function () {
    var campoSwitch = document.getElementById('whatsappModoTeste');
    var campoNumero = document.getElementById('whatsappNumeroTeste');
    var aviso       = document.getElementById('demoAviso');
    var avisoTexto  = document.getElementById('demoAvisoTexto');
    var botoes      = document.querySelectorAll('.btn-demo-whatsapp');

    function atualizar() {
        var ligado = campoSwitch.checked;
        var temNumero = campoNumero.value.trim() !== '';
        var habilitado = ligado && temNumero;

        botoes.forEach(function (b) { b.disabled = !habilitado; });
        aviso.style.display = habilitado ? 'none' : '';
        if (!habilitado) {
            avisoTexto.textContent = !ligado
                ? 'Ligue o "Modo de teste" acima pra habilitar.'
                : 'Configure um número de teste acima pra habilitar.';
        }
    }

    campoSwitch.addEventListener('change', atualizar);
    campoNumero.addEventListener('input', atualizar);
})();
</script>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
