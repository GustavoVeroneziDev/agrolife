<?php
/**
 * Cron: notificações de vacinas — 7 dias antes do vencimento + no dia.
 * Executar 1x por dia (ex: 09h):
 *   0 9 * * * php /caminho/para/agrolife/cron/whatsapp_vacinas.php >> /logs/wa_vacinas.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso restrito ao CLI.');
}

require_once __DIR__ . '/../config/conexao.php';

echo '[' . date('Y-m-d H:i:s') . '] Iniciando notificações de vacina...' . PHP_EOL;

function processarLote(PDO $pdo, string $sql, string $tipoConfig, string $tipoLog, string $flagCampo): array
{
    $stmt = $pdo->query($sql);
    $registros = $stmt->fetchAll();

    $msgTpl = getConfig($pdo, $tipoConfig, '');
    $enviados = 0;
    $erros    = 0;

    if (!$msgTpl) {
        echo "[AVISO] Template {$tipoConfig} não configurado — nenhuma mensagem enviada." . PHP_EOL;
        return [0, 0];
    }

    foreach ($registros as $reg) {
        $telNorm = sanitizarTelefone((string) ($reg['Telefone'] ?? ''));
        if (!$telNorm) {
            echo "[SKIP] {$reg['IDRegistro']} — telefone inválido ou ausente" . PHP_EOL;
            continue;
        }

        $msg = str_replace(
            ['{nome_cliente}', '{nome_dono}', '{nome_animal}', '{vacina}', '{data}'],
            [$reg['NomeDono'], $reg['NomeDono'], $reg['NomeAnimal'], $reg['NomeVacina'], date('d/m/Y', strtotime($reg['ProximaData']))],
            $msgTpl
        );

        $ok = enviarWhatsApp($telNorm, $msg);
        registrarLogWhatsApp($pdo, $telNorm, $msg, $tipoLog, $ok ? 'enviado' : 'erro', $reg['IDRegistro']);

        if ($ok) {
            $pdo->prepare("UPDATE RegistrosVacinas SET {$flagCampo} = 1 WHERE IDRegistro = :id")
                ->execute([':id' => $reg['IDRegistro']]);
            $enviados++;
            echo "[OK] {$tipoLog} → {$reg['NomeAnimal']} ({$reg['NomeDono']})" . PHP_EOL;
        } else {
            $erros++;
            echo "[ERRO WA] {$reg['IDRegistro']}" . PHP_EOL;
        }
    }

    return [$enviados, $erros];
}

try {
    // Vacinas cíclicas (ex: antirrábica anual) renovam a própria data sozinhas
    // quando vencem — sem isso, cada ciclo dependia do vet reaplicar de
    // verdade só pra ProximaData ser recalculada de novo. Roda em loop
    // (limitado) porque, se o cron ficou parado um tempo, uma vacina pode
    // estar atrasada por mais de um ciclo inteiro.
    $sqlAvancar = "
        UPDATE RegistrosVacinas rv
        JOIN TiposVacina tv ON tv.IDTipo = rv.FKTipoVacina
        SET rv.ProximaData = DATE_ADD(rv.ProximaData, INTERVAL tv.IntervaloMeses MONTH),
            rv.NotificacaoSemanaEnviada = 0,
            rv.NotificacaoDiaEnviada = 0
        WHERE rv.Ciclica = 1
          AND rv.ProximaData < CURDATE()
          AND tv.IntervaloMeses IS NOT NULL AND tv.IntervaloMeses > 0
    ";
    $totalAvancadas = 0;
    $iteracoes = 0;
    do {
        $afetadas = $pdo->exec($sqlAvancar);
        $totalAvancadas += $afetadas;
        $iteracoes++;
    } while ($afetadas > 0 && $iteracoes < 60);
    if ($totalAvancadas > 0) {
        echo "[CICLICA] {$totalAvancadas} renovação(ões) de vacina cíclica avançada(s) automaticamente." . PHP_EOL;
    }

    $sqlSemana = "
        SELECT rv.IDRegistro, rv.ProximaData, a.Nome AS NomeAnimal,
               u.Nome AS NomeDono, u.Telefone, tv.Nome AS NomeVacina
        FROM RegistrosVacinas rv
        JOIN Animais a  ON a.IDAnimal  = rv.FKAnimal
        JOIN Usuarios u ON u.IDUsuario = a.FKDono
        JOIN TiposVacina tv ON tv.IDTipo = rv.FKTipoVacina
        WHERE rv.ProximaData = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND rv.NotificacaoSemanaEnviada = 0
          AND a.Ativo = 1
    ";
    [$envSemana, $errSemana] = processarLote($pdo, $sqlSemana, 'msg_vacina_semana', 'vacina_semana', 'NotificacaoSemanaEnviada');

    $sqlDia = "
        SELECT rv.IDRegistro, rv.ProximaData, a.Nome AS NomeAnimal,
               u.Nome AS NomeDono, u.Telefone, tv.Nome AS NomeVacina
        FROM RegistrosVacinas rv
        JOIN Animais a  ON a.IDAnimal  = rv.FKAnimal
        JOIN Usuarios u ON u.IDUsuario = a.FKDono
        JOIN TiposVacina tv ON tv.IDTipo = rv.FKTipoVacina
        WHERE rv.ProximaData = CURDATE()
          AND rv.NotificacaoDiaEnviada = 0
          AND a.Ativo = 1
    ";
    [$envDia, $errDia] = processarLote($pdo, $sqlDia, 'msg_vacina_dia', 'vacina_dia', 'NotificacaoDiaEnviada');
} catch (PDOException $e) {
    echo '[ERRO BD] ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "Concluído. 7-dias: {$envSemana} enviados / {$errSemana} erros | Dia: {$envDia} enviados / {$errDia} erros" . PHP_EOL;
