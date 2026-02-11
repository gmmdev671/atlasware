<?php

// app/Config/permissions_map.php

$PERMISSIONS_GROUPS = [
    'RH e Administrativo' => [
        'editarRH' => 'Editar RH',
        'editarRHDoc' => 'Editar Documentos RH',
        'editarRHSit' => 'Editar Situação RH',
        'editarLibFunc' => 'Liberar Funcionários',
        'relAtestado' => 'Relatório de Atestados',
    ],
    'Financeiro e Notas' => [
        'editarnota' => 'Editar Notas',
        'aprovarNF' => 'Aprovar NF (Geral)',
        'aprovarNFZ' => 'Aprovar NF (Z)',
        'cadNF' => 'Cadastrar NF',
        'abrirNF' => 'Abrir NF',
        'aprovarFE' => 'Aprovar FE',
    ],
    'Equipamentos' => [
        'editarEquipamento' => 'Editar Equipamentos',
        'admEquipamento' => 'Admin Equipamentos',
        'equipMaster' => 'Equipamento Master',
        'equipAC' => 'Equipamento AC',
        'cadEquipamento' => 'Cadastrar Equipamento',
        'equipSit' => 'Situação de Equipamento',
    ],
    'Operacional e Engenharia' => [
        'editarss' => 'Editar SS',
        'editarLancamento' => 'Editar Lançamentos',
        'editarCTE' => 'Editar CTE',
        'editarEquipe' => 'Editar Equipe',
        'editarCargo' => 'Editar Cargo',
        'aprovarConc' => 'Aprovar Concreto',
        'aprovarLOC' => 'Aprovar Locação',
    ]
];

$SCOPE_FIELDS = [
    'obra' => 'Obras Permitidas',
    'cidade' => 'Cidades Permitidas',
    'nivel_acesso' => 'Nível de Sistema'
];