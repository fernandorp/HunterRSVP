<?php
// Funções compartilhadas entre o formulário e a lista.

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['fuso']);

/** Remove quebras de linha, o separador "|" e espaços extras; limita o tamanho. */
function limpar(string $texto, int $max = 120): string
{
    $texto = str_replace(['|', "\r", "\n", "\t"], ['/', ' ', ' ', ' '], $texto);
    $texto = preg_replace('/\s+/u', ' ', $texto);
    return mb_substr(trim($texto), 0, $max);
}

function h(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Grava uma confirmação como uma linha legível no .txt. */
function gravar_confirmacao(string $arquivo, array $r): bool
{
    $novo = !file_exists($arquivo);
    $linha = implode(' | ', [
        $r['data'],
        $r['vai'] ? 'SIM' : 'NAO',
        $r['nome'],
        $r['telefone'] !== '' ? $r['telefone'] : '-',
        count($r['acompanhantes']),
        $r['acompanhantes'] ? implode('; ', $r['acompanhantes']) : '-',
        $r['recado'] !== '' ? $r['recado'] : '-',
    ]) . PHP_EOL;

    if ($novo) {
        $linha = "# data | presença | nome | telefone | nº acompanhantes | acompanhantes | recado" . PHP_EOL . $linha;
    }
    return file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX) !== false;
}

/** Lê todas as linhas do .txt. Retorna lista de registros (mais antigo primeiro). */
function ler_confirmacoes(string $arquivo): array
{
    if (!is_readable($arquivo)) {
        return [];
    }
    $registros = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '#')) {
            continue;
        }
        $p = explode(' | ', $linha);
        if (count($p) < 7) {
            continue;
        }
        $registros[] = [
            'data'          => $p[0],
            'vai'           => $p[1] === 'SIM',
            'nome'          => $p[2],
            'telefone'      => $p[3] === '-' ? '' : $p[3],
            'acompanhantes' => $p[5] === '-' ? [] : explode('; ', $p[5]),
            'recado'        => $p[6] === '-' ? '' : $p[6],
        ];
    }
    return $registros;
}

/** Apaga todas as respostas de uma pessoa (pela chave_nome). Retorna quantas linhas saíram. */
function remover_confirmacao(string $arquivo, string $chave): int
{
    $fp = @fopen($arquivo, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        return 0;
    }
    $manter = [];
    $removidas = 0;
    while (($linha = fgets($fp)) !== false) {
        $p = explode(' | ', $linha);
        if (!str_starts_with($linha, '#') && count($p) >= 7 && chave_nome($p[2]) === $chave) {
            $removidas++;
            continue;
        }
        $manter[] = $linha;
    }
    if ($removidas) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, implode('', $manter));
        fflush($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    return $removidas;
}

/** Chave para identificar a mesma pessoa (ignora acentos, maiúsculas e espaços). */
function chave_nome(string $nome): string
{
    $s = mb_strtolower(trim($nome), 'UTF-8');
    $s = strtr($s, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    return preg_replace('/\s+/', ' ', $s);
}

/** E-mail de aviso para o aniversariante. */
function enviar_email(array $config, array $r): bool
{
    $ev = $config['evento'];
    $status = $r['vai'] ? 'CONFIRMOU presença' : 'NÃO poderá ir';
    $assunto = ($r['vai'] ? '✅ ' : '❌ ') . "{$r['nome']} — RSVP {$ev['titulo']}";

    $corpo  = "{$r['nome']} {$status}.\n\n";
    $corpo .= "Telefone: " . ($r['telefone'] ?: '-') . "\n";
    if ($r['vai']) {
        $total = 1 + count($r['acompanhantes']);
        $corpo .= "Acompanhantes (" . count($r['acompanhantes']) . "): " . ($r['acompanhantes'] ? implode(', ', $r['acompanhantes']) : 'nenhum') . "\n";
        $corpo .= "Total de pessoas nesta confirmação: {$total}\n";
    }
    if ($r['recado'] !== '') {
        $corpo .= "\nRecado: {$r['recado']}\n";
    }
    $corpo .= "\nRecebido em: {$r['data']}\n";
    $corpo .= "{$ev['data']} • {$ev['hora']} • {$ev['local']}\n";

    $headers = implode("\r\n", [
        'From: =?UTF-8?B?' . base64_encode('RSVP 40tão') . "?= <{$config['email_remetente']}>",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);

    $assunto = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
    $erro = '';

    $ok = false;
    if (!empty($config['smtp']['senha'])) {
        $ok = enviar_smtp($config, $assunto, $corpo, $headers, $erro);
    }
    if (!$ok) {
        // Sem SMTP configurado (ou se ele falhar), tenta mail() como reserva.
        // "-f" define o envelope sender; a Hostinger rejeita/descarta sem ele.
        $ok = @mail($config['email_destino'], $assunto, $corpo, $headers, '-f' . $config['email_remetente']);
        $erro = trim($erro . ($ok ? ' (enviado via mail())' : ' | mail(): ' . (error_get_last()['message'] ?? 'retornou false')));
    }

    // Registra falhas em dados/email.log (bloqueado pelo .htaccess) para diagnóstico.
    if ($erro !== '') {
        @file_put_contents(
            dirname($config['arquivo']) . '/email.log',
            date('d/m/Y H:i') . " | {$r['nome']} | {$erro}" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    return $ok;
}

/** Envio SMTP mínimo (SSL implícito + AUTH LOGIN). Preenche $erro em caso de falha. */
function enviar_smtp(array $config, string $assunto, string $corpo, string $headers, string &$erro): bool
{
    $s = $config['smtp'];
    $de = $config['email_remetente'];
    $para = $config['email_destino'];

    $fp = @stream_socket_client("ssl://{$s['host']}:{$s['porta']}", $errno, $errstr, 15);
    if (!$fp) {
        $erro = "conexão SMTP: {$errstr}";
        return false;
    }
    stream_set_timeout($fp, 15);

    $ler = function () use ($fp): string {
        $resp = '';
        while (($linha = fgets($fp, 515)) !== false) {
            $resp .= $linha;
            if (isset($linha[3]) && $linha[3] === ' ') {
                break;
            }
        }
        return $resp;
    };
    $cmd = function (?string $linha, string $esperado) use ($fp, $ler, &$erro): bool {
        if ($linha !== null) {
            fwrite($fp, $linha . "\r\n");
        }
        $resp = $ler();
        if (strncmp($resp, $esperado, 3) !== 0) {
            $erro = 'SMTP: ' . trim($resp);
            return false;
        }
        return true;
    };

    // Linhas começando com "." precisam ser duplicadas (dot-stuffing).
    $corpo = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $corpo));
    $mensagem = "To: <{$para}>\r\nSubject: {$assunto}\r\nDate: " . date('r') . "\r\n{$headers}\r\n\r\n{$corpo}\r\n.";

    $ok = $cmd(null, '220')
        && $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250')
        && $cmd('AUTH LOGIN', '334')
        && $cmd(base64_encode($de), '334')
        && $cmd(base64_encode($s['senha']), '235')
        && $cmd("MAIL FROM:<{$de}>", '250')
        && $cmd("RCPT TO:<{$para}>", '250')
        && $cmd('DATA', '354')
        && $cmd($mensagem, '250');

    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}
