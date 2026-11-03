<?php
require __DIR__ . '/funcoes.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$chave = (string) ($_GET['chave'] ?? '');
if ($config['chave_lista'] === 'TROQUE-ESTA-CHAVE') {
    http_response_code(503);
    exit('Configure a "chave_lista" no config.php para liberar esta página.');
}
if (!hash_equals($config['chave_lista'], $chave)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

// Arquivo .txt bruto, para baixar/consultar direto
if (($_GET['formato'] ?? '') === 'txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: inline; filename="confirmacoes.txt"');
    if (is_readable($config['arquivo'])) {
        readfile($config['arquivo']);
    } else {
        echo "Nenhuma confirmação ainda.\n";
    }
    exit;
}

// Remover uma pessoa (botão ao lado do nome)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover'])) {
    remover_confirmacao($config['arquivo'], (string) $_POST['remover']);
    header('Location: ?chave=' . rawurlencode($chave), true, 303);
    exit;
}

$todos = ler_confirmacoes($config['arquivo']);

// Se a mesma pessoa respondeu mais de uma vez, vale a resposta mais recente.
$atual = [];
foreach ($todos as $r) {
    $atual[chave_nome($r['nome'])] = $r;
}
$sim = array_filter($atual, fn($r) => $r['vai']);
$nao = array_filter($atual, fn($r) => !$r['vai']);
uasort($sim, fn($a, $b) => strcoll(chave_nome($a['nome']), chave_nome($b['nome'])));
$totalPessoas = array_sum(array_map(fn($r) => 1 + count($r['acompanhantes']), $sim));
$totalAcomp = $totalPessoas - count($sim);
$ev = $config['evento'];
$qs = 'chave=' . rawurlencode($chave);

function botao_remover(string $nome, string $qs): string
{
    $aviso = h(json_encode("Remover {$nome} da lista? Isso apaga todas as respostas dessa pessoa.", JSON_UNESCAPED_UNICODE));
    return '<form method="post" action="?' . h($qs) . '" onsubmit="return confirm(' . $aviso . ')">'
        . '<input type="hidden" name="remover" value="' . h(chave_nome($nome)) . '">'
        . '<button type="submit" class="remover" title="Remover da lista">Remover</button></form>';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Lista de presença · <?= h($ev['titulo']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Alfa+Slab+One&family=Roboto+Slab:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="estilo.css">
<style>
  .lista { max-width: 900px; margin: 0 auto; padding: 32px 16px; }
  .lista h1 { text-align: center; }
  .numeros { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin: 20px 0 28px; }
  .numeros div { background: var(--papel); border: 1px solid var(--borda); border-radius: 12px; padding: 14px; text-align: center; }
  .numeros b { display: block; font: 400 36px "Alfa Slab One", serif; color: var(--laranja); }
  .numeros div:first-child { background: var(--carvao); color: #fff; }
  .numeros div:first-child b { color: #ffb27a; }
  h2 { font-size: 18px; margin: 28px 0 10px; }
  .tabela { overflow-x: auto; background: var(--papel); border: 1px solid var(--borda); border-radius: 12px; }
  table { width: 100%; border-collapse: collapse; font-size: 15px; }
  th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--borda); vertical-align: top; }
  th { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: var(--suave); }
  tr:last-child td { border-bottom: 0; }
  td.n { text-align: center; font-weight: 800; }
  .recado { color: var(--suave); font-style: italic; }
  .acoes { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 24px; }
  .acoes a { color: var(--laranja); font-weight: 600; }
  .vazio { text-align: center; color: var(--suave); padding: 20px; }
  .nome { display: flex; gap: 10px; align-items: flex-start; justify-content: space-between; }
  .nome form { margin: 0; flex-shrink: 0; }
  .remover { font: 600 12px "Roboto Slab", serif; color: #b3261e; background: none; border: 1px solid currentColor; border-radius: 6px; padding: 3px 8px; cursor: pointer; }
  .remover:hover { background: #b3261e; color: #fff; }
</style>
</head>
<body>
<main class="lista">
  <h1><?= titulo_html($ev, 'color:var(--laranja)') ?></h1>
  <p class="quando" style="text-align:center"><?= h($ev['data']) ?> • <?= h($ev['hora']) ?> · <?= h($ev['local']) ?></p>

  <section class="numeros">
    <div><b><?= $totalPessoas ?></b>pessoas no total</div>
    <div><b><?= count($sim) ?></b>convidados confirmados</div>
    <div><b><?= $totalAcomp ?></b>acompanhantes</div>
    <div><b><?= count($nao) ?></b>não vão</div>
  </section>

  <h2>✅ Confirmados</h2>
  <div class="tabela">
    <?php if (!$sim): ?>
      <p class="vazio">Ninguém confirmou ainda.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>Nome</th><th>Acompanhantes</th><th>Total</th><th>WhatsApp</th><th>Quando</th></tr></thead>
      <tbody>
      <?php foreach ($sim as $r): ?>
        <tr>
          <td><div class="nome"><div><strong><?= h($r['nome']) ?></strong><?php if ($r['recado']): ?><div class="recado">“<?= h($r['recado']) ?>”</div><?php endif; ?></div><?= botao_remover($r['nome'], $qs) ?></div></td>
          <td><?= $r['acompanhantes'] ? h(implode(', ', $r['acompanhantes'])) : '—' ?></td>
          <td class="n"><?= 1 + count($r['acompanhantes']) ?></td>
          <td><?= $r['telefone'] ? h($r['telefone']) : '—' ?></td>
          <td><?= h($r['data']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($nao): ?>
  <h2>❌ Não poderão ir</h2>
  <div class="tabela">
    <table>
      <thead><tr><th>Nome</th><th>Recado</th><th>Quando</th></tr></thead>
      <tbody>
      <?php foreach ($nao as $r): ?>
        <tr>
          <td><div class="nome"><span><?= h($r['nome']) ?></span><?= botao_remover($r['nome'], $qs) ?></div></td>
          <td class="recado"><?= $r['recado'] ? h($r['recado']) : '—' ?></td>
          <td><?= h($r['data']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="acoes">
    <a href="?<?= $qs ?>&amp;formato=txt">Ver arquivo .txt completo (<?= count($todos) ?> respostas, com histórico)</a>
    <a href="?<?= $qs ?>">Atualizar</a>
  </div>
</main>
</body>
</html>
