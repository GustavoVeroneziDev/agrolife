<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin');

$campos = [
    'nome_clinica', 'telefone_clinica', 'endereco_clinica',
    'msg_vacina_semana', 'msg_vacina_dia',
    'whatsapp_modo_teste', 'whatsapp_numero_teste',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        redirecionarComMensagem(BASE . '/painel/configuracoes.php', 'Token inválido.', 'danger');
    }
    try {
        foreach ($campos as $c) {
            setConfig($pdo, $c, trim($_POST[$c] ?? ''));
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
            <div class="mb-3">
                <label class="form-label">WhatsApp da clínica</label>
                <input type="tel" name="telefone_clinica" class="form-control" data-mask="tel"
                       value="<?= h(formatarTelefoneExibicao($valores['telefone_clinica'])) ?>">
            </div>
            <div class="mb-0">
                <label class="form-label">Endereço</label>
                <textarea name="endereco_clinica" class="form-control" rows="2"><?= h($valores['endereco_clinica']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card p-4 mb-4">
            <h6 class="fw-semibold mb-2"><i class="bi bi-whatsapp me-2 text-accent"></i>Templates de WhatsApp</h6>
            <p class="small text-secondary">
                Variáveis disponíveis: <code>{nome_cliente}</code> <code>{nome_animal}</code> <code>{vacina}</code> <code>{data}</code>
            </p>
            <div class="mb-3">
                <label class="form-label">Aviso — 7 dias antes do vencimento</label>
                <textarea name="msg_vacina_semana" class="form-control" rows="4"><?= h($valores['msg_vacina_semana']) ?></textarea>
            </div>
            <div class="mb-0">
                <label class="form-label">Aviso — no dia do vencimento</label>
                <textarea name="msg_vacina_dia" class="form-control" rows="4"><?= h($valores['msg_vacina_dia']) ?></textarea>
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
