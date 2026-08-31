<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
exigirLogin('cliente');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionarComMensagem(BASE . '/usuario/meus_agendamentos.php', 'Método não permitido.', 'danger');
}
if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
    redirecionarComMensagem(BASE . '/usuario/meus_agendamentos.php', 'Token inválido.', 'danger');
}

$uid  = $_SESSION['usuario_id'];
$acao = $_POST['acao'] ?? '';
$id   = trim($_POST['id'] ?? '');

if ($acao !== 'cancelar' || $id === '') {
    redirecionarComMensagem(BASE . '/usuario/meus_agendamentos.php', 'Ação inválida.', 'warning');
}

$tiposAgenda = tiposAgendaMap();

try {
    // Confere que o agendamento é de um animal desse cliente antes de
    // deixar cancelar — sem isso, dava pra cancelar o ID de qualquer
    // agendamento só sabendo o UUID.
    $stmt = $pdo->prepare(
        "SELECT ag.IDAgendamento, ag.Status, ag.Tipo, ag.Titulo, ag.DataHoraInicio,
                a.Nome AS NomeAnimal, u.Nome AS NomeDono
         FROM Agendamentos ag
         JOIN Animais a  ON a.IDAnimal = ag.FKAnimal
         JOIN Usuarios u ON u.IDUsuario = a.FKDono
         WHERE ag.IDAgendamento = :id AND a.FKDono = :uid
         LIMIT 1"
    );
    $stmt->execute([':id' => $id, ':uid' => $uid]);
    $ag = $stmt->fetch();

    if (!$ag) {
        redirecionarComMensagem(BASE . '/usuario/meus_agendamentos.php', 'Agendamento não encontrado.', 'warning');
    }
    if (!in_array($ag['Status'], ['pendente', 'confirmado'], true)) {
        redirecionarComMensagem(BASE . '/usuario/meus_agendamentos.php', 'Esse agendamento não pode mais ser cancelado.', 'warning');
    }
    if ($ag['DataHoraInicio'] <= date('Y-m-d H:i:s')) {
        redirecionarComMensagem(BASE . '/usuario/meus_agendamentos.php', 'Esse agendamento já passou e não pode mais ser cancelado por aqui — fale com a clínica.', 'warning');
    }

    $pdo->prepare("UPDATE Agendamentos SET Status = 'cancelado' WHERE IDAgendamento = :id")
        ->execute([':id' => $id]);

    // Avisa a clínica pelo WhatsApp cadastrado nas configurações — se não
    // tiver número configurado, enviarWhatsApp() só falha em silêncio.
    $telClinica = getConfig($pdo, 'telefone_clinica', '');
    if ($telClinica !== '') {
        $msg = "O cliente {$ag['NomeDono']} cancelou o agendamento de {$ag['NomeAnimal']}: "
             . ($tiposAgenda[$ag['Tipo']] ?? $ag['Tipo']) . ' — ' . $ag['Titulo'] . ' em '
             . formatarData($ag['DataHoraInicio']) . ' às ' . date('H:i', strtotime($ag['DataHoraInicio'])) . '.';
        enviarWhatsApp(waNumero($telClinica), $msg);
    }

    redirecionarComMensagem(BASE . '/usuario/meus_agendamentos.php', 'Agendamento cancelado.', 'success');
} catch (PDOException $e) {
    error_log('[ProcessaAgendamento] ' . $e->getMessage());
    redirecionarComMensagem(BASE . '/usuario/meus_agendamentos.php', 'Erro ao cancelar agendamento.', 'danger');
}
