<?php
/**
 * Cron: lembrete automático de atendimento agendado — dispara até 1 dia
 * antes do horário marcado (consulta, cirurgia, exame, procedimento...).
 * Executar 1x por dia (ex: 09h, mesmo horário do cron de vacina — a janela
 * de 24h a partir da execução já cobre o resto de hoje + amanhã até esse
 * mesmo horário, sem precisar de um segundo agendamento de cron):
 *   0 9 * * * php /caminho/para/agrolife/cron/whatsapp_agendamentos.php >> /logs/wa_agendamentos.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso restrito ao CLI.');
}

require_once __DIR__ . '/../config/conexao.php';

echo '[' . date('Y-m-d H:i:s') . '] Iniciando lembrete de atendimento...' . PHP_EOL;

try {
    // Janela móvel (NOW() até NOW()+24h), não "amanhã" por data de
    // calendário — de propósito. Se o cron atrasar (host fora do ar,
    // deploy), uma janela fixa por CURDATE() podia lembrar de um
    // atendimento que já aconteceu, ou dizer "amanhã" numa mensagem que na
    // hora do envio já seria mentira. Com NOW() como piso, só entra quem
    // ainda vai acontecer de verdade.
    $sql = "
        SELECT ag.IDAgendamento, ag.DataHoraInicio, ag.Tipo, ag.Titulo,
               a.Nome AS NomeAnimal, u.Nome AS NomeCliente, u.Telefone
        FROM Agendamentos ag
        JOIN Animais a  ON a.IDAnimal  = ag.FKAnimal
        JOIN Usuarios u ON u.IDUsuario = a.FKDono
        WHERE ag.DataHoraInicio BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
          AND ag.Status IN ('pendente', 'confirmado')
          AND ag.NotificacaoLembreteEnviada = 0
          AND a.Ativo = 1
    ";
    $registros = $pdo->query($sql)->fetchAll();

    // Mesmo raciocínio do cron de vacina: sem fallback pro texto padrão,
    // uma instalação nova (ou campo apagado sem querer em Configurações)
    // parava de lembrar atendimento nenhum, silenciosamente.
    $msgTpl = getConfig($pdo, 'msg_lembrete_atendimento', '') ?: templatesWhatsAppPadrao()['msg_lembrete_atendimento'];

    if (!$msgTpl) {
        echo '[AVISO] Template msg_lembrete_atendimento não configurado — nenhuma mensagem enviada.' . PHP_EOL;
        exit(0);
    }

    $enviados = 0;
    $erros    = 0;

    foreach ($registros as $reg) {
        $telNorm = sanitizarTelefone((string) ($reg['Telefone'] ?? ''));
        if (!$telNorm) {
            echo "[SKIP] {$reg['IDAgendamento']} — telefone inválido ou ausente" . PHP_EOL;
            continue;
        }

        $msg = montarMensagemLembreteAtendimento(
            $pdo, $reg['NomeCliente'], $reg['NomeAnimal'], $reg['Tipo'], $reg['Titulo'], $reg['DataHoraInicio']
        );

        $ok = enviarWhatsApp($telNorm, $msg);
        registrarLogWhatsApp($pdo, $telNorm, $msg, 'agendamento_lembrete', $ok ? 'enviado' : 'erro', null, $reg['IDAgendamento']);

        if ($ok) {
            $pdo->prepare('UPDATE Agendamentos SET NotificacaoLembreteEnviada = 1 WHERE IDAgendamento = :id')
                ->execute([':id' => $reg['IDAgendamento']]);
            $enviados++;
            echo "[OK] lembrete → {$reg['NomeAnimal']} ({$reg['NomeCliente']}) — " . formatarDataHora($reg['DataHoraInicio']) . PHP_EOL;
        } else {
            $erros++;
            echo "[ERRO WA] {$reg['IDAgendamento']}" . PHP_EOL;
        }
    }

    echo "Concluído. {$enviados} enviado(s) / {$erros} erro(s)." . PHP_EOL;
} catch (PDOException $e) {
    echo '[ERRO BD] ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
