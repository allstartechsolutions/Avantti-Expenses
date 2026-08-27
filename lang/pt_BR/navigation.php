<?php

/**
 * As palavras dos menus. Ver lang/en/navigation.php.
 *
 * Vocabulário fixado aqui, seguindo o glossário do pt_BR.json:
 *   Job Site → Local, Project → Projeto, Purchase Orders → Ordens de Compra,
 *   Requisitions → Solicitações de Compra, RFI → SI (Solicitação de Informação).
 *
 * Dois nomes de grupo foram decididos e não devem ser "corrigidos" sem motivo:
 *   - Nenhum grupo usa a palavra *Solicitações*: ela já pertence às Solicitações
 *     de Compra, e um grupo com esse nome contendo SI confundiria as duas.
 *   - *Obra* é o trabalho em si (diários e tarefas), e não se confunde com
 *     *Locais*, que continua sendo uma aba própria.
 */

return [

    /*
    |---------------------------------------------------------------------------
    | Os grupos da barra de abas de projeto / local
    |---------------------------------------------------------------------------
    */

    'groups' => [
        'financial' => 'Financeiro',
        'procurement' => 'Suprimentos',
        'collaboration' => 'Colaboração',
        'field' => 'Obra',
    ],

    /*
    |---------------------------------------------------------------------------
    | As abas
    |---------------------------------------------------------------------------
    */

    'tabs' => [
        'overview' => 'Visão Geral',
        'jobsites' => 'Locais',
        'budget' => 'Orçamento',
        'expenses' => 'Despesas',
        'income' => 'Entradas',
        'report' => 'Relatório',
        'requisitions' => 'Solicitações de Compra',
        'quotations' => 'Cotações',
        'purchase-orders' => 'Ordens de Compra',
        'contracts' => 'Contratos',
        'change-orders' => 'Ordens de Alteração',
        'documents' => 'Documentos',
        'rfis' => 'Solicitações de Informação (SI)',
        'approvals' => 'Aprovações',
        'daily-reports' => 'Relatórios Diários',
        'tasks' => 'Tarefas',
        'team' => 'Equipe',
    ],

    /*
    |---------------------------------------------------------------------------
    | A própria barra
    |---------------------------------------------------------------------------
    */

    'sections' => 'Seções',
    'open_section' => 'Abrir o menu :section',

];
