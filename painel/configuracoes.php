<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin');

$campos = [
    'nome_clinica', 'telefone_clinica', 'email_clinica', 'instagram_clinica',
    'endereco_rua', 'endereco_numero', 'endereco_complemento', 'endereco_bairro', 'endereco_cidade', 'endereco_uf', 'endereco_cep',
    'msg_vacina_semana', 'msg_vacina_dia', 'msg_agendamento_criado', 'msg_cancelamento', 'msg_remarcacao',
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

$paginaTitulo = 'Configurações';
$areaAtual    = 'painel';
require_once __DIR__ . '/../geral/header.php';
?>

<h4 class="fw-bold mb-4"><i class="bi bi-gear me-2 text-accent"></i>Configurações</h4>

<form method="POST">
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
    </div>

    <div class="col-lg-6">
        <div class="card p-4 mb-4">
            <h6 class="fw-semibold mb-2"><i class="bi bi-whatsapp me-2 text-accent"></i>Templates de WhatsApp</h6>
            <p class="small text-secondary mb-3">
                Deixe um template em branco pra usar o texto padrão do sistema.
            </p>

            <div class="mb-3 pb-3 border-bottom">
                <label class="form-label mb-1">Agendamento criado</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_cliente}</code> <code>{nome_animal}</code> <code>{tipo}</code> <code>{titulo}</code> <code>{data}</code> <code>{hora}</code>
                </p>
                <textarea name="msg_agendamento_criado" class="form-control" rows="3" placeholder="Texto padrão do sistema"><?= h($valores['msg_agendamento_criado']) ?></textarea>
            </div>

            <div class="mb-3 pb-3 border-bottom">
                <label class="form-label mb-1">Cancelamento</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_animal}</code> <code>{tipo}</code> <code>{titulo}</code> <code>{data}</code> <code>{hora}</code>
                </p>
                <textarea name="msg_cancelamento" class="form-control" rows="3" placeholder="Texto padrão do sistema"><?= h($valores['msg_cancelamento']) ?></textarea>
            </div>

            <div class="mb-3 pb-3 border-bottom">
                <label class="form-label mb-1">Remarcação</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_cliente}</code> <code>{nome_animal}</code> <code>{tipo}</code> <code>{titulo}</code> <code>{data}</code> <code>{hora}</code>
                </p>
                <textarea name="msg_remarcacao" class="form-control" rows="3" placeholder="Texto padrão do sistema"><?= h($valores['msg_remarcacao']) ?></textarea>
            </div>

            <div class="mb-3 pb-3 border-bottom">
                <label class="form-label mb-1">Aviso de vacina — 7 dias antes do vencimento</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_cliente}</code> <code>{nome_animal}</code> <code>{vacina}</code> <code>{data}</code>
                </p>
                <textarea name="msg_vacina_semana" class="form-control" rows="3"><?= h($valores['msg_vacina_semana']) ?></textarea>
            </div>

            <div class="mb-0">
                <label class="form-label mb-1">Aviso de vacina — no dia do vencimento</label>
                <p class="small text-secondary mb-2">
                    Variáveis: <code>{nome_cliente}</code> <code>{nome_animal}</code> <code>{vacina}</code> <code>{data}</code>
                </p>
                <textarea name="msg_vacina_dia" class="form-control" rows="3"><?= h($valores['msg_vacina_dia']) ?></textarea>
            </div>
        </div>

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
                       id="whatsappModoTeste" value="1" <?= $valores['whatsapp_modo_teste'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="whatsappModoTeste">Modo de teste ligado</label>
            </div>
            <div class="mb-0">
                <label class="form-label">Número de teste</label>
                <input type="tel" name="whatsapp_numero_teste" class="form-control" data-mask="tel"
                       value="<?= h(formatarTelefoneExibicao($valores['whatsapp_numero_teste'])) ?>">
            </div>
        </div>

    </div>
</div>

<div class="d-grid col-lg-6">
    <button type="submit" class="btn btn-accent btn-lg"><i class="bi bi-check2 me-2"></i> Salvar configurações</button>
</div>
</form>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-4 mb-4">
            <h6 class="fw-semibold mb-2"><i class="bi bi-play-circle me-2 text-accent"></i>Demonstração ao vivo</h6>
            <p class="small text-secondary">
                Dispara pro <strong>número de teste</strong> configurado acima uma mensagem de
                exemplo, igual à que um cliente de verdade recebe — pra mostrar o sistema
                funcionando numa reunião, sem precisar de um agendamento real.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="<?= BASE ?>/painel/demo_whatsapp.php">
                    <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                    <input type="hidden" name="cenario" value="agendamento">
                    <button type="submit" class="btn btn-outline-accent">
                        <i class="bi bi-calendar-plus me-1"></i> Agendamento criado
                    </button>
                </form>
                <form method="POST" action="<?= BASE ?>/painel/demo_whatsapp.php">
                    <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                    <input type="hidden" name="cenario" value="cancelamento">
                    <button type="submit" class="btn btn-outline-accent">
                        <i class="bi bi-calendar-x me-1"></i> Cancelamento
                    </button>
                </form>
                <form method="POST" action="<?= BASE ?>/painel/demo_whatsapp.php">
                    <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                    <input type="hidden" name="cenario" value="remarcacao">
                    <button type="submit" class="btn btn-outline-accent">
                        <i class="bi bi-arrow-repeat me-1"></i> Remarcação
                    </button>
                </form>
                <form method="POST" action="<?= BASE ?>/painel/demo_whatsapp.php">
                    <input type="hidden" name="csrf_token" value="<?= gerarTokenCSRF() ?>">
                    <input type="hidden" name="cenario" value="vacina">
                    <button type="submit" class="btn btn-outline-accent">
                        <i class="bi bi-shield-plus me-1"></i> Lembrete de vacina
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../geral/footer.php' ?>
