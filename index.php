<?php
require __DIR__ . '/funcoes.php';

$ev = $config['evento'];
$max = (int) $config['max_acompanhantes'];
$erros = [];
$v = ['nome' => '', 'telefone' => '', 'vai' => 'sim', 'acompanhantes' => [], 'recado' => ''];
$encerrado = time() > strtotime($config['prazo']);
$whatsapp = 'https://wa.me/' . $config['whatsapp'] . '?text='
    . rawurlencode("Oi {$ev['anfitriao']}! Sobre o {$ev['titulo']}: ");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $encerrado) {
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Campo-armadilha: humanos não veem, robôs preenchem.
    if (!empty($_POST['site'])) {
        header('Location: ?ok=sim');
        exit;
    }

    $v['nome']     = limpar($_POST['nome'] ?? '', 80);
    $v['telefone'] = limpar($_POST['telefone'] ?? '', 30);
    $v['vai']      = ($_POST['vai'] ?? '') === 'nao' ? 'nao' : 'sim';
    $v['recado']   = limpar($_POST['recado'] ?? '', 300);
    $lista = is_array($_POST['acompanhantes'] ?? null) ? $_POST['acompanhantes'] : [];
    foreach ($lista as $a) {
        $a = limpar((string) $a, 80);
        if ($a !== '') {
            $v['acompanhantes'][] = str_replace(';', ',', $a);
        }
    }

    if (mb_strlen($v['nome']) < 2) {
        $erros[] = 'Informe seu nome completo.';
    }
    if ($v['vai'] === 'nao') {
        $v['acompanhantes'] = [];
    }
    if (count($v['acompanhantes']) > $max) {
        $erros[] = "O limite é de {$max} acompanhantes.";
    }

    if (!$erros) {
        $registro = [
            'data'          => date('d/m/Y H:i'),
            'vai'           => $v['vai'] === 'sim',
            'nome'          => $v['nome'],
            'telefone'      => $v['telefone'],
            'acompanhantes' => $v['acompanhantes'],
            'recado'        => $v['recado'],
        ];
        if (gravar_confirmacao($config['arquivo'], $registro)) {
            enviar_email($config, $registro);
            $primeiro = explode(' ', $v['nome'])[0];
            header('Location: ?ok=' . $v['vai'] . '&n=' . rawurlencode($primeiro));
            exit;
        }
        $erros[] = 'Não foi possível salvar agora. Tente novamente em instantes.';
    }
}

$ok = $_GET['ok'] ?? null;
$primeiroNome = limpar($_GET['n'] ?? '', 40);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Confirme presença · <?= h($ev['titulo']) ?></title>
<meta name="description" content="<?= h($ev['data'] . ' • ' . $ev['hora'] . ' • ' . $ev['local']) ?>">
<meta property="og:title" content="<?= h($ev['titulo']) ?> — confirme sua presença">
<meta property="og:description" content="<?= h($ev['data'] . ' • ' . $ev['hora'] . ' • ' . $ev['local']) ?>">
<meta property="og:image" content="<?= h(url_absoluta($ev['convite'])) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alfa+Slab+One&family=Kalam:wght@700&family=Roboto+Slab:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="estilo.css">
</head>
<body>
<main class="pagina">
  <aside class="convite">
    <img src="<?= h($ev['convite']) ?>" alt="Convite: <?= h($ev['titulo'] . ' — ' . $ev['data'] . ', ' . $ev['hora'] . ', ' . $ev['local']) ?>">
  </aside>

  <section class="cartao">
    <?php if ($ok === 'sim' || $ok === 'nao'): ?>
      <div class="obrigado">
        <div class="emoji"><?= $ok === 'sim' ? '🔥' : '💛' ?></div>
        <?php if ($ok === 'sim'): ?>
          <h1>Presença confirmada<?= $primeiroNome ? ', ' . h($primeiroNome) : '' ?>!</h1>
          <p class="script">A grelha já está esquentando!</p>
          <p class="info"><strong><?= h($ev['data']) ?> • <?= h($ev['hora']) ?></strong><br><?= h($ev['local']) ?></p>
          <p class="aviso">🍺 Se for consumir bebida alcoólica, traga a sua!</p>
        <?php else: ?>
          <h1>Que pena<?= $primeiroNome ? ', ' . h($primeiroNome) : '' ?>!</h1>
          <p class="script">Obrigado por avisar — fica pra próxima!</p>
        <?php endif; ?>
        <a class="link" href="./">Enviar outra confirmação</a>
      </div>
    <?php elseif ($encerrado): ?>
      <div class="obrigado">
        <div class="emoji">⏰</div>
        <h1>Confirmações encerradas</h1>
        <p class="script">O prazo era até <?= h($config['prazo_texto']) ?>.</p>
        <p class="info"><strong><?= h($ev['data']) ?> • <?= h($ev['hora']) ?></strong><br><?= h($ev['local']) ?></p>
        <p>Precisa falar comigo sobre a festa?</p>
        <a class="whatsapp" href="<?= h($whatsapp) ?>" target="_blank" rel="noopener">💬 Falar com o <?= h($ev['anfitriao']) ?> no WhatsApp</a>
      </div>
    <?php else: ?>
      <header>
        <p class="sobre">Confirme sua presença no</p>
        <h1><?= titulo_html($ev) ?></h1>
        <p class="quando">📅 <?= h($ev['data']) ?> • <?= h($ev['hora']) ?> &nbsp;·&nbsp; 📍 <?= h($ev['local']) ?></p>
        <p class="prazo">Confirme até <?= h($config['prazo_texto']) ?></p>
      </header>

      <?php if ($erros): ?>
        <div class="erro" role="alert"><?= implode('<br>', array_map('h', $erros)) ?></div>
      <?php endif; ?>

      <form method="post" id="rsvp" novalidate>
        <label class="campo">
          <span>Seu nome completo *</span>
          <input type="text" name="nome" required minlength="2" maxlength="80" autocomplete="name" value="<?= h($v['nome']) ?>">
        </label>

        <fieldset class="escolha">
          <legend>Você vai?</legend>
          <label><input type="radio" name="vai" value="sim" <?= $v['vai'] === 'sim' ? 'checked' : '' ?>><span>🍖 Sim, tô dentro!</span></label>
          <label><input type="radio" name="vai" value="nao" <?= $v['vai'] === 'nao' ? 'checked' : '' ?>><span>😢 Não vou conseguir</span></label>
        </fieldset>

        <div id="blocoAcomp">
          <div class="contador">
            <span>Acompanhantes</span>
            <div class="stepper">
              <button type="button" id="menos" aria-label="Remover acompanhante">−</button>
              <output id="qtd">0</output>
              <button type="button" id="mais" aria-label="Adicionar acompanhante">+</button>
            </div>
          </div>
          <div id="listaAcomp"></div>
          <noscript>
            <?php for ($i = 0; $i < 4; $i++): ?>
              <input class="acomp" type="text" name="acompanhantes[]" maxlength="80" placeholder="Nome do acompanhante <?= $i + 1 ?> (opcional)">
            <?php endfor; ?>
          </noscript>
        </div>

        <label class="campo">
          <span>WhatsApp <small>(opcional)</small></span>
          <input type="tel" name="telefone" maxlength="30" autocomplete="tel" placeholder="(11) 90000-0000" value="<?= h($v['telefone']) ?>">
        </label>

        <label class="campo">
          <span>Recado pro aniversariante <small>(opcional)</small></span>
          <textarea name="recado" rows="2" maxlength="300"><?= h($v['recado']) ?></textarea>
        </label>

        <div class="armadilha" aria-hidden="true">
          <input type="text" name="site" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="enviar">Confirmar</button>
        <p class="rodape">🍺 Se for consumir bebida alcoólica, traga a sua!</p>
      </form>
    <?php endif; ?>
  </section>
</main>

<script>
(function () {
  var form = document.getElementById('rsvp');
  if (!form) return;
  var MAX = <?= $max ?>;
  var iniciais = <?= json_encode(array_values($v['acompanhantes']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  var lista = document.getElementById('listaAcomp');
  var qtd = document.getElementById('qtd');
  var bloco = document.getElementById('blocoAcomp');
  var enviar = form.querySelector('.enviar');

  function render(n) {
    var atuais = Array.prototype.map.call(lista.querySelectorAll('input'), function (i) { return i.value; });
    lista.innerHTML = '';
    for (var i = 0; i < n; i++) {
      var inp = document.createElement('input');
      inp.type = 'text'; inp.name = 'acompanhantes[]'; inp.maxLength = 80; inp.required = true;
      inp.className = 'acomp';
      inp.placeholder = 'Nome do acompanhante ' + (i + 1);
      inp.value = atuais[i] !== undefined ? atuais[i] : (iniciais[i] || '');
      lista.appendChild(inp);
    }
    qtd.textContent = n;
    document.getElementById('menos').disabled = n === 0;
    document.getElementById('mais').disabled = n >= MAX;
  }
  function n() { return lista.querySelectorAll('input').length; }

  document.getElementById('mais').onclick = function () {
    if (n() < MAX) { render(n() + 1); lista.lastChild.focus(); }
  };
  document.getElementById('menos').onclick = function () { if (n() > 0) render(n() - 1); };

  function atualizaVai() {
    var vai = form.querySelector('input[name=vai]:checked').value === 'sim';
    bloco.hidden = !vai;
    lista.querySelectorAll('input').forEach(function (i) { i.disabled = !vai; });
    enviar.textContent = vai ? 'Confirmar presença' : 'Enviar resposta';
  }
  form.querySelectorAll('input[name=vai]').forEach(function (r) { r.onchange = atualizaVai; });

  form.addEventListener('submit', function (e) {
    if (!form.checkValidity()) {
      e.preventDefault();
      var inv = form.querySelector(':invalid');
      inv.classList.add('invalido');
      inv.focus();
      return;
    }
    enviar.disabled = true;
    enviar.textContent = 'Enviando…';
  });
  form.addEventListener('input', function (e) { e.target.classList.remove('invalido'); });

  render(iniciais.length);
  atualizaVai();
})();
</script>
</body>
</html>
