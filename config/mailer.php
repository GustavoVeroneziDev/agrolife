<?php
/**
 * Mailer — envio de e-mail via SMTP ou mail() nativo como fallback.
 * Não contém credenciais; requer config/smtp_keys.php com as constantes SMTP_*.
 */

// conexao.php é gitignored — reforço aqui pro deploy nunca quebrar por defasagem
// entre o git push (auto) e o reenvio manual desse arquivo (FTP)
if (!defined('APP_NOME')) {
    define('APP_NOME', 'Agro Life');
}

function enviarEmail(string $para, string $assunto, string $htmlBody, string $textoBody = ''): bool
{
    if (!$textoBody) {
        $textoBody = wordwrap(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)), 76);
    }

    if (defined('SMTP_HOST') && SMTP_HOST) {
        return _mailerSmtp($para, $assunto, $htmlBody, $textoBody);
    }

    return _mailerNativo($para, $assunto, $htmlBody, $textoBody);
}

function _mailerNativo(string $para, string $assunto, string $html, string $texto): bool
{
    $from = defined('SMTP_FROM')      ? SMTP_FROM      : 'nao-responda@agrolife.local';
    $nome = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NOME;
    $b    = md5(uniqid('vs_', true));

    $hdrs  = "From: =?UTF-8?B?" . base64_encode($nome) . "?= <{$from}>\r\n";
    $hdrs .= "MIME-Version: 1.0\r\n";
    $hdrs .= "Content-Type: multipart/alternative; boundary=\"{$b}\"\r\n";
    $hdrs .= "X-Mailer: " . APP_NOME . "/1.0";

    $body  = "--{$b}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$texto}\r\n\r\n";
    $body .= "--{$b}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n\r\n--{$b}--";

    return mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $body, $hdrs);
}

function _mailerSmtp(string $para, string $assunto, string $html, string $texto): bool
{
    $host   = SMTP_HOST;
    $port   = defined('SMTP_PORT')   ? (int) SMTP_PORT : 465;
    $secure = defined('SMTP_SECURE') ? SMTP_SECURE      : 'ssl';
    $user   = SMTP_USER;
    $pass   = SMTP_PASS;
    $from   = defined('SMTP_FROM')      ? SMTP_FROM      : $user;
    $nome   = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NOME;

    $ctx  = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
    ]);
    $addr = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $sock = @stream_socket_client($addr, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);

    if (!$sock) {
        error_log("[Mailer] Conexão falhou: {$errstr} ({$errno})");
        return false;
    }
    stream_set_timeout($sock, 15);

    $recv = function () use ($sock): string {
        $buf = '';
        while (!feof($sock)) {
            $line = fgets($sock, 512);
            if ($line === false) break;
            $buf .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $buf;
    };

    $cmd = function (string $c) use ($sock, $recv): string {
        fwrite($sock, $c . "\r\n");
        return $recv();
    };

    $recv(); // saudação do servidor

    $cmd('EHLO ' . (gethostname() ?: 'agrolife.local'));

    if ($secure === 'tls') {
        $cmd('STARTTLS');
        stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        $cmd('EHLO ' . (gethostname() ?: 'agrolife.local'));
    }

    $cmd('AUTH LOGIN');
    $cmd(base64_encode($user));
    $authResp = $cmd(base64_encode($pass));

    if (!str_starts_with(ltrim($authResp), '235')) {
        error_log("[Mailer] AUTH LOGIN falhou: {$authResp}");
        fclose($sock);
        return false;
    }

    $cmd("MAIL FROM:<{$from}>");
    $cmd("RCPT TO:<{$para}>");
    $cmd('DATA');

    $b    = md5(uniqid('vs_', true));
    $msg  = "From: =?UTF-8?B?" . base64_encode($nome) . "?= <{$from}>\r\n";
    $msg .= "To: <{$para}>\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($assunto) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: multipart/alternative; boundary=\"{$b}\"\r\n";
    $msg .= "X-Mailer: " . APP_NOME . "/1.0\r\n\r\n";
    $msg .= "--{$b}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$texto}\r\n\r\n";
    $msg .= "--{$b}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n\r\n--{$b}--\r\n";

    // SMTP dot-stuffing: linhas que começam com '.' precisam de um '.' extra
    $msg = preg_replace('/^\./m', '..', $msg);

    fwrite($sock, $msg . "\r\n.\r\n");
    $dataResp = $recv();
    $ok = str_starts_with(ltrim($dataResp), '250');

    if (!$ok) error_log("[Mailer] DATA rejeitado: {$dataResp}");

    $cmd('QUIT');
    fclose($sock);
    return $ok;
}

function emailHtml(string $titulo, string $corpo): string
{
    $t     = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $marca = htmlspecialchars(APP_NOME, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<!DOCTYPE html><html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$t}</title></head>
<body style="margin:0;padding:0;background:#f0faf9;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0faf9;padding:32px 16px;">
<tr><td align="center">
<table width="100%" style="max-width:520px;background:#fff;border-radius:14px;border:1px solid #b9e2dc;overflow:hidden;box-shadow:0 2px 12px rgba(13,36,32,.07);">
  <tr><td style="background:#0d9488;padding:20px 28px;text-align:center;">
    <span style="color:#fff;font-size:20px;font-weight:700;letter-spacing:.02em;">{$marca}</span>
  </td></tr>
  <tr><td style="padding:28px 32px;color:#0d2420;line-height:1.6;">
    {$corpo}
  </td></tr>
  <tr><td style="background:#e6f7f5;padding:14px 32px;text-align:center;font-size:12px;color:#4a7b76;">
    Este e-mail foi gerado automaticamente &mdash; n&atilde;o responda.
  </td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
}
