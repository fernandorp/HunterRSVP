<?php
// ============================================================
//  Configuração do RSVP — 40tão do Fernando
//  Copie para config.php e edite os valores antes de subir para a hospedagem.
// ============================================================

return [
    // Para onde vai o aviso de cada confirmação
    'email_destino'   => 'seu-email@gmail.com',

    // Remetente do e-mail. Use um endereço do MESMO domínio da hospedagem
    // (ex.: nao-responder@seudominio.com.br), senão o Gmail tende a mandar pro spam.
    'email_remetente' => 'nao-responder@seudominio.com.br',

    // Envio via SMTP da Hostinger (mais confiável que mail()). Usuário = a caixa do remetente.
    // Deixe 'senha' vazia para voltar a usar mail().
    'smtp' => [
        'host'  => 'smtp.hostinger.com',
        'porta' => 465,
        'senha' => '',
    ],

    // Chave do link exclusivo da lista: https://seusite.com/lista.php?chave=ESTA_CHAVE
    // TROQUE por uma sequência aleatória longa. A lista não abre enquanto for o valor padrão.
    'chave_lista'     => 'TROQUE-ESTA-CHAVE',

    // Arquivo onde as confirmações são gravadas (a pasta dados/ é bloqueada pelo .htaccess)
    'arquivo'         => __DIR__ . '/dados/confirmacoes.txt',

    // Limite de acompanhantes por convidado
    'max_acompanhantes' => 10,

    // Fuso horário dos registros
    'fuso'            => 'America/Sao_Paulo',

    // Depois deste momento o formulário fecha (horário do fuso acima)
    'prazo'           => '2026-11-12 23:59:59',
    'prazo_texto'     => '12 de novembro',

    // WhatsApp do aniversariante (com DDI+DDD, só números) para o botão de contato
    'whatsapp'        => '5511999999999',

    // Dados do evento (aparecem no formulário e no e-mail)
    'evento' => [
        'titulo' => '40tão do Fernando',
        'data'   => '20 de novembro',
        'hora'   => '13h',
        'local'  => 'Quinta dos Ventos — Churrasqueira',
    ],
];
