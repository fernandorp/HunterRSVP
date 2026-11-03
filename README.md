# HunterRSVP

Site para confirmação de presença (RSVP) em PHP puro, sem banco de dados.
Usado no 40tão do Fernando.

## Arquivos
- `index.php` — formulário (nome, vai/não vai, acompanhantes, WhatsApp e recado opcionais).
  Depois do `prazo` o formulário fecha e mostra um botão para chamar o aniversariante no WhatsApp.
- `lista.php` — lista exclusiva: `lista.php?chave=SUA_CHAVE` (e `&formato=txt` para o .txt bruto).
  Cada pessoa tem um botão **Remover** (apaga todas as respostas dela).
- `config.exemplo.php` — modelo de configuração (e-mail, SMTP, chave da lista, prazo, WhatsApp, dados do evento)
- `funcoes.php` — gravação/leitura do .txt e envio do e-mail de aviso (SMTP, com `mail()` como reserva)
- `dados/confirmacoes.txt` — criado automaticamente; acesso direto bloqueado pelo `.htaccess`

## Como publicar
1. Copie `config.exemplo.php` para `config.php` e preencha:
   - `chave_lista`: uma sequência longa e aleatória (a lista não abre com o valor padrão);
   - `email_remetente`: uma caixa de e-mail que **exista** no domínio da hospedagem,
     e `smtp.senha` com a senha dela (vazia = usa `mail()`, que costuma cair no spam);
   - `prazo`, `whatsapp` e os dados do `evento`.
2. Envie a pasta inteira por FTP/SSH. O `config.php` não vai para o Git.
3. Garanta que a pasta `dados/` tenha permissão de escrita (755 ou 775).
4. Faça uma confirmação de teste e confira o e-mail. Falhas de envio ficam em `dados/email.log`.

Se a mesma pessoa responder de novo, a lista considera a resposta mais recente;
o .txt guarda todo o histórico.
