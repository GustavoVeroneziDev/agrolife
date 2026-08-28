<?php

date_default_timezone_set('America/Sao_Paulo');

function gerarUuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function sanitizarTelefone(string $tel): ?string
{
    $tel = preg_replace('/\D/', '', $tel);
    $tel = ltrim($tel, '0');

    if (strlen($tel) === 13 && str_starts_with($tel, '55')) {
        return $tel;
    }
    if (strlen($tel) === 11) {
        return '55' . $tel;
    }
    if (strlen($tel) === 10) {
        return '55' . substr($tel, 0, 2) . '9' . substr($tel, 2);
    }

    return null;
}

function waNumero(string $tel): string
{
    return sanitizarTelefone($tel) ?? preg_replace('/\D/', '', $tel);
}

function waLink(string $tel, string $mensagem = ''): string
{
    $num = waNumero($tel);
    if (!$num) return '#';
    $url = 'https://wa.me/' . $num;
    if ($mensagem !== '') {
        $url .= '?text=' . urlencode($mensagem);
    }
    return $url;
}

function enviarWhatsApp(string $numero, string $mensagem): bool
{
    if (!defined('EVOLUTION_URL') || !defined('EVOLUTION_INSTANCE') || !defined('EVOLUTION_KEY')) {
        error_log('[WhatsApp] Evolution API não configurada.');
        return false;
    }

    $url     = rtrim(EVOLUTION_URL, '/') . '/message/sendText/' . EVOLUTION_INSTANCE;
    $payload = json_encode(['number' => $numero, 'text' => $mensagem], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=UTF-8',
            'apikey: ' . EVOLUTION_KEY,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $dec    = json_decode($response, true);
        $status = $dec['status'] ?? ($dec[0]['status'] ?? 'sem-status');
        error_log("[WhatsApp] Enviado para {$numero} — Evolution status={$status}");
        return true;
    }

    error_log("[WhatsApp] HTTP {$httpCode} para {$numero}: " . substr((string) $response, 0, 500));
    return false;
}

function registrarLogWhatsApp(
    PDO $pdo,
    string $numero,
    string $mensagem,
    string $tipo,
    string $status,
    ?string $fkRegistroVacina = null
): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO LogsWhatsApp (IDLog, FKRegistroVacina, Numero, Mensagem, TipoMensagem, StatusEnvio)
             VALUES (:id, :fkr, :num, :msg, :tipo, :status)'
        );
        $stmt->execute([
            ':id'     => gerarUuid(),
            ':fkr'    => $fkRegistroVacina,
            ':num'    => $numero,
            ':msg'    => $mensagem,
            ':tipo'   => $tipo,
            ':status' => $status,
        ]);
    } catch (PDOException $e) {
        error_log('[LogWA] ' . $e->getMessage());
    }
}

function redirecionarComMensagem(string $url, string $msg, string $tipo): never
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_msg']  = $msg;
    $_SESSION['flash_tipo'] = $tipo;
    header('Location: ' . $url);
    exit;
}

function gerarTokenCSRF(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarTokenCSRF(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function estaLogado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

function exigirLogin(string ...$niveis): void
{
    if (!estaLogado()) {
        global $pdo;
        if (isset($pdo)) tentarLoginLembrado($pdo);
    }
    if (!estaLogado()) {
        redirecionarComMensagem(BASE . '/usuario/login.php', 'Faça login para continuar.', 'warning');
    }
    if ($niveis && !in_array($_SESSION['nivel_acesso'] ?? '', $niveis, true)) {
        redirecionarComMensagem(BASE . '/index.php', 'Acesso não permitido.', 'danger');
    }
}

function criarTokenLembrarMe(PDO $pdo, string $idUsuario, int $dias = 30): void
{
    try {
        $pdo->prepare('DELETE FROM TokensLembrarMe WHERE FKUsuario = :id AND Expira < NOW()')
            ->execute([':id' => $idUsuario]);
    } catch (PDOException) {}

    $idToken    = gerarUuid();
    $tokenPlain = bin2hex(random_bytes(32));
    $tokenHash  = hash('sha256', $tokenPlain);
    $expira     = date('Y-m-d H:i:s', strtotime("+{$dias} days"));

    try {
        $pdo->prepare(
            'INSERT INTO TokensLembrarMe (IDToken, FKUsuario, TokenHash, Expira)
             VALUES (:id, :fku, :hash, :expira)'
        )->execute([':id' => $idToken, ':fku' => $idUsuario, ':hash' => $tokenHash, ':expira' => $expira]);
    } catch (PDOException $e) {
        error_log('[LembrarMe] Erro ao salvar token: ' . $e->getMessage());
        return;
    }

    $path = (defined('BASE') && BASE !== '') ? BASE . '/' : '/';
    setcookie('vs_lembrar', $idToken . ':' . $tokenPlain, [
        'expires'  => strtotime("+{$dias} days"),
        'path'     => $path,
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function tentarLoginLembrado(PDO $pdo): void
{
    if (estaLogado() || empty($_COOKIE['vs_lembrar'])) return;

    $partes = explode(':', $_COOKIE['vs_lembrar'], 2);
    if (count($partes) !== 2) {
        _limparCookieLembrarMe();
        return;
    }
    [$idToken, $tokenPlain] = $partes;

    try {
        $stmt = $pdo->prepare(
            'SELECT t.IDToken, t.FKUsuario, t.TokenHash,
                    u.Nome, u.NivelAcesso, u.Ativo
             FROM TokensLembrarMe t
             JOIN Usuarios u ON u.IDUsuario = t.FKUsuario
             WHERE t.IDToken = :id AND t.Expira > NOW()
             LIMIT 1'
        );
        $stmt->execute([':id' => $idToken]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[LembrarMe] ' . $e->getMessage());
        return;
    }

    if (!$row || !$row['Ativo']) {
        _limparCookieLembrarMe();
        return;
    }

    if (!hash_equals($row['TokenHash'], hash('sha256', $tokenPlain))) {
        // Hash inválido — possível roubo de cookie; invalida todos os tokens do usuário
        try {
            $pdo->prepare('DELETE FROM TokensLembrarMe WHERE FKUsuario = :id')
                ->execute([':id' => $row['FKUsuario']]);
        } catch (PDOException) {}
        _limparCookieLembrarMe();
        error_log('[LembrarMe] Token inválido para usuário ' . $row['FKUsuario'] . ' — todos os tokens apagados.');
        return;
    }

    // Rotação só quando o token está vencendo (< 15 dias restantes)
    $deveRotar = strtotime($row['Expira']) < strtotime('+15 days');
    if ($deveRotar) {
        try {
            $pdo->prepare('DELETE FROM TokensLembrarMe WHERE IDToken = :id')
                ->execute([':id' => $idToken]);
        } catch (PDOException) {}
        criarTokenLembrarMe($pdo, $row['FKUsuario']);
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id']   = $row['FKUsuario'];
    $_SESSION['usuario_nome'] = $row['Nome'];
    $_SESSION['nivel_acesso'] = $row['NivelAcesso'];
}

function _limparCookieLembrarMe(): void
{
    $path = (defined('BASE') && BASE !== '') ? BASE . '/' : '/';
    setcookie('vs_lembrar', '', [
        'expires'  => time() - 3600,
        'path'     => $path,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['vs_lembrar']);
}

function h(mixed $str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

function flashMsg(): void
{
    if (!empty($_SESSION['flash_msg'])) {
        $tipo = $_SESSION['flash_tipo'] ?? 'info';

        if ($tipo === 'success') {
            // Sucesso não precisa de banner que fica na tela — um toast
            // rápido no canto já confirma e some sozinho, sem atrapalhar.
            $msgJs = json_encode($_SESSION['flash_msg'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS);
            echo "<script>document.addEventListener('DOMContentLoaded', function () { vsToast({$msgJs}, 'success'); });</script>";
        } else {
            $tipoSafe = h($tipo);
            $msg      = h($_SESSION['flash_msg']);
            echo "<div class=\"alert alert-{$tipoSafe} alert-dismissible fade show mb-3\" role=\"alert\">"
                . "<i class=\"bi bi-info-circle me-2\"></i>{$msg}"
                . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
                . '</div>';
        }
        unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
    }
}

function getConfig(PDO $pdo, string $chave, string $padrao = ''): string
{
    static $cache = [];
    if (array_key_exists($chave, $cache)) return $cache[$chave];
    try {
        $stmt = $pdo->prepare('SELECT Valor FROM ConfiguracoesSistema WHERE Chave = :chave LIMIT 1');
        $stmt->execute([':chave' => $chave]);
        $row = $stmt->fetch();
        return $cache[$chave] = $row ? (string) $row['Valor'] : $padrao;
    } catch (PDOException) {
        return $padrao;
    }
}

function setConfig(PDO $pdo, string $chave, string $valor): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO ConfiguracoesSistema (IDConfig, Chave, Valor)
         VALUES (:id, :chave, :valor)
         ON DUPLICATE KEY UPDATE Valor = :valor2, AtualizadoEm = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':id'     => gerarUuid(),
        ':chave'  => $chave,
        ':valor'  => $valor,
        ':valor2' => $valor,
    ]);
}

function formatarData(string $date): string
{
    return date('d/m/Y', strtotime($date));
}

function formatarDataHora(string $datetime): string
{
    return date('d/m/Y \à\s H:i', strtotime($datetime));
}

function formatarTelefoneExibicao(?string $tel): string
{
    if (!$tel) return '';
    $d = preg_replace('/\D/', '', $tel);
    if (strlen($d) === 13 && str_starts_with($d, '55')) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    }
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    }
    return $tel;
}

/**
 * Valida data de nascimento de animal: formato real, não pode ser no
 * futuro, não pode passar de 100 anos atrás. Campo vazio é válido
 * (nascimento é opcional) — quem exige preenchido valida isso à parte.
 */
function dataNascimentoValida(string $data): bool
{
    if ($data === '') return true;

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $data);
    if (!$dt || $dt->format('Y-m-d') !== $data) return false;

    // Compara só a data (string 'Y-m-d'), nunca DateTime — createFromFormat()
    // preenche a hora com o horário atual (não meia-noite), então comparar
    // objetos DateTime aqui rejeitava até a data de hoje por causa da hora.
    $hoje         = date('Y-m-d');
    $limiteAntigo = date('Y-m-d', strtotime('-100 years'));

    return $data <= $hoje && $data >= $limiteAntigo;
}

function formatarIdade(?string $dataNascimento): string
{
    if (!$dataNascimento) return '';
    $nasc  = new DateTimeImmutable($dataNascimento);
    $agora = new DateTimeImmutable();
    if ($nasc > $agora) return '';
    $diff  = $nasc->diff($agora);

    if ($diff->y >= 1) {
        $txt = $diff->y . ($diff->y === 1 ? ' ano' : ' anos');
        if ($diff->m > 0) $txt .= ' e ' . $diff->m . ($diff->m === 1 ? ' mês' : ' meses');
        return $txt;
    }
    if ($diff->m >= 1) {
        return $diff->m . ($diff->m === 1 ? ' mês' : ' meses');
    }
    return $diff->d . ($diff->d === 1 ? ' dia' : ' dias');
}

function formatarSexo(?string $sexo): string
{
    return match ($sexo) {
        'macho' => '<i class="bi bi-gender-male me-1"></i>Macho',
        'femea' => '<i class="bi bi-gender-female me-1"></i>Fêmea',
        'indeterminado' => 'Indeterminado',
        default => '',
    };
}

/**
 * Situação de vacinação a partir da ProximaData de um registro.
 * @return array{0:string,1:string,2:string} [label, cor-bootstrap, ícone]
 */
function situacaoVacina(?string $proximaData): array
{
    if (!$proximaData) return ['Dose única', 'secondary', 'bi-check2-circle'];

    $dias = (int) floor((strtotime($proximaData) - strtotime(date('Y-m-d'))) / 86400);

    if ($dias < 0)  return ['Atrasada', 'danger', 'bi-exclamation-triangle-fill'];
    if ($dias === 0) return ['Vence hoje', 'warning', 'bi-alarm-fill'];
    if ($dias <= 30) return ["Vence em {$dias} dia" . ($dias === 1 ? '' : 's'), 'warning', 'bi-clock-fill'];
    return ['Em dia', 'success', 'bi-check-circle-fill'];
}

function labelSituacaoVacina(?string $proximaData): string
{
    [$label, $cor] = situacaoVacina($proximaData);
    return '<span class="badge bg-' . $cor . '">' . h($label) . '</span>';
}

/**
 * Gera o HTML de um campo picker (dropdown com busca) — ver initPicker() em
 * geral/header.php. $valorInicial/$labelInicial preenchem o campo já
 * selecionado (uso em telas de edição); deixe ambos vazios para um campo novo.
 */
function campoPicker(
    string $prefixo,
    string $nomeCampo,
    string $placeholder,
    string $placeholderBusca,
    string $valorInicial = '',
    string $labelInicial = '',
    bool $obrigatorio = false,
    bool $comBusca = true,
    string $iconeInicial = ''
): string {
    $req      = $obrigatorio ? ' required' : '';
    $temValor = $valorInicial !== '';
    $labelTxt = $temValor ? $labelInicial : $placeholder;
    $labelCls = $temValor ? 'picker-selected' : 'picker-placeholder';
    // $iconeInicial é sempre uma classe fixa escrita no PHP chamador (ex: "bi-gender-male"),
    // nunca dado de usuário — por isso entra como HTML confiável, sem passar por h().
    $iconeHtml = $iconeInicial !== '' ? '<i class="bi ' . $iconeInicial . ' me-1"></i>' : '';

    $busca = $comBusca ? '
            <div class="picker-search-wrap">
                <i class="bi bi-search picker-search-icon"></i>
                <input type="text" class="picker-search" id="' . $prefixo . 'Search" placeholder="' . h($placeholderBusca) . '" autocomplete="off">
            </div>' : '';

    return '
    <input type="hidden" name="' . h($nomeCampo) . '" id="inp' . $prefixo . 'Id" value="' . h($valorInicial) . '"' . $req . '>
    <div class="picker" id="' . $prefixo . 'Picker">
        <div class="picker-trigger" id="' . $prefixo . 'Trigger" tabindex="0">
            <span id="' . $prefixo . 'Label" class="' . $labelCls . '">' . $iconeHtml . h($labelTxt) . '</span>
            <span class="picker-caret"><i class="bi bi-chevron-down"></i></span>
        </div>
        <div class="picker-dropdown d-none" id="' . $prefixo . 'Dropdown">' . $busca . '
            <div class="picker-list" id="' . $prefixo . 'List"></div>
        </div>
    </div>';
}
