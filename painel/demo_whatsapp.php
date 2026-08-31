<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionarComMensagem(BASE . '/painel/configuracoes.php', 'Método não permitido.', 'danger');
}
if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
    redirecionarComMensagem(BASE . '/painel/configuracoes.php', 'Token inválido.', 'danger');
}

// Sempre manda pro número de teste configurado, nunca pra um telefone
// vindo do formulário — assim o botão de demonstração não corre risco
// de mandar mensagem pra um cliente de verdade, mesmo que o modo de
// teste geral esteja desligado no momento.
$numeroDemo = getConfig($pdo, 'whatsapp_numero_teste', '');
if ($numeroDemo === '') {
    redirecionarComMensagem(BASE . '/painel/configuracoes.php', 'Configure um número de teste antes de usar a demonstração.', 'warning');
}

// Cenário fictício fixo — nome de cliente e de animal reais o bastante
// pra fazer sentido numa demonstração ao vivo, em vez de algo como
// "Cliente Teste 1".
$clienteDemo = 'Ana Paula';
$animalDemo  = 'Spok';
$horarioDemo = date('Y-m-d H:i:s', strtotime('tomorrow 14:00'));

$cenario = $_POST['cenario'] ?? '';

switch ($cenario) {
    case 'agendamento':
        $msg = montarMensagemNovoAgendamento($clienteDemo, $animalDemo, 'cirurgia', 'Castração', $horarioDemo);
        break;

    case 'cancelamento':
        $msg = montarMensagemCancelamento($animalDemo, 'consulta', 'Consulta de rotina', $horarioDemo);
        break;

    case 'vacina':
        $template = getConfig($pdo, 'msg_vacina_dia', '')
            ?: getConfig($pdo, 'msg_vacina_semana', '')
            ?: 'Olá, {nome_cliente}! A vacina {vacina} de {nome_animal} vence em {data}. Vamos agendar o reforço?';
        $msg = str_replace(
            ['{nome_cliente}', '{nome_dono}', '{nome_animal}', '{vacina}', '{data}'],
            [$clienteDemo, $clienteDemo, $animalDemo, 'Antirrábica', date('d/m/Y', strtotime($horarioDemo))],
            $template
        );
        break;

    default:
        redirecionarComMensagem(BASE . '/painel/configuracoes.php', 'Cenário de demonstração inválido.', 'warning');
}

$ok = enviarWhatsApp(waNumero($numeroDemo), $msg);

redirecionarComMensagem(
    BASE . '/painel/configuracoes.php',
    $ok ? 'Mensagem de demonstração enviada!' : 'Falha ao enviar — confira se a instância do WhatsApp está conectada.',
    $ok ? 'success' : 'danger'
);
