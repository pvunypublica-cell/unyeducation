<?php

return [

    /*
    | Domínio base dos endereços padrão: ava-{slug}.{dominio_base} (UNY-DOC-004, 5.1).
    */
    'dominio_base' => env('UNY_DOMINIO_BASE', 'faculdadeunypublica.edu.br'),

    'prefixo_host' => 'ava-',

    /*
    | Nomes que nenhum parceiro pode usar como slug (UNY-DOC-004, 5.2):
    | evitam conflito com serviços existentes.
    */
    'slugs_reservados' => [
        'painel', 'server', 'intranet', 'www', 'api', 'admin', 'teste',
        'mail', 'smtp', 'ftp', 'ava', 'app', 'static', 'cdn', 'webhook',
        'webhooks', 'operador', 'suporte', 'status', 'homolog', 'homologacao',
        'staging', 'dev', 'moodle',
    ],

    /*
    | Validade do link mágico, em minutos.
    */
    'link_magico_minutos' => 15,
];
