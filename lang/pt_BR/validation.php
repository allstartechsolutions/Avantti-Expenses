<?php

/*
|--------------------------------------------------------------------------
| Mensagens de validação — pt_BR
|--------------------------------------------------------------------------
|
| O framework só traz o conjunto em inglês (Illuminate/Translation/lang/en).
| Sem este arquivo, um usuário brasileiro recebe "The title field is required."
| em todos os formulários do sistema.
|
| O FileLoader mescla [framework/lang, lang/] com array_replace_recursive, e
| não existe pt_BR no framework — portanto este arquivo precisa estar completo.
| Ver docs/pt-br-translation-audit.md.
|
| `attributes` traduz o :attribute interpolado nas mensagens. Sem ele o Laravel
| usa o nome da coluna ("job site id"). O $validationAttributes declarado em um
| componente Livewire tem precedência sobre este mapa.
|
*/

return [

    'accepted' => 'O campo :attribute deve ser aceito.',
    'accepted_if' => 'O campo :attribute deve ser aceito quando :other for :value.',
    'active_url' => 'O campo :attribute deve conter uma URL válida.',
    'after' => 'O campo :attribute deve conter uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute deve conter uma data posterior ou igual a :date.',
    'alpha' => 'O campo :attribute deve conter apenas letras.',
    'alpha_dash' => 'O campo :attribute deve conter apenas letras, números, hífens e sublinhados.',
    'alpha_num' => 'O campo :attribute deve conter apenas letras e números.',
    'any_of' => 'O campo :attribute é inválido.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'ascii' => 'O campo :attribute deve conter apenas caracteres alfanuméricos e símbolos de um byte.',
    'before' => 'O campo :attribute deve conter uma data anterior a :date.',
    'before_or_equal' => 'O campo :attribute deve conter uma data anterior ou igual a :date.',

    'between' => [
        'array' => 'O campo :attribute deve conter entre :min e :max itens.',
        'file' => 'O arquivo :attribute deve ter entre :min e :max kilobytes.',
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
    ],

    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'can' => 'O campo :attribute contém um valor não autorizado.',
    'confirmed' => 'A confirmação do campo :attribute não confere.',
    'contains' => 'O campo :attribute não contém um valor obrigatório.',
    'current_password' => 'A senha informada está incorreta.',
    'date' => 'O campo :attribute deve conter uma data válida.',
    'date_equals' => 'O campo :attribute deve conter uma data igual a :date.',
    'date_format' => 'O campo :attribute deve corresponder ao formato :format.',
    'decimal' => 'O campo :attribute deve ter :decimal casas decimais.',
    'declined' => 'O campo :attribute deve ser recusado.',
    'declined_if' => 'O campo :attribute deve ser recusado quando :other for :value.',
    'different' => 'Os campos :attribute e :other devem ser diferentes.',
    'digits' => 'O campo :attribute deve ter :digits dígitos.',
    'digits_between' => 'O campo :attribute deve ter entre :min e :max dígitos.',
    'dimensions' => 'O campo :attribute tem dimensões de imagem inválidas.',
    'distinct' => 'O campo :attribute contém um valor duplicado.',
    'doesnt_contain' => 'O campo :attribute não deve conter nenhum dos seguintes: :values.',
    'doesnt_end_with' => 'O campo :attribute não deve terminar com um dos seguintes: :values.',
    'doesnt_start_with' => 'O campo :attribute não deve começar com um dos seguintes: :values.',
    'email' => 'O campo :attribute deve conter um endereço de e-mail válido.',
    'encoding' => 'O campo :attribute deve usar a codificação :encoding.',
    'ends_with' => 'O campo :attribute deve terminar com um dos seguintes: :values.',
    'enum' => 'O valor selecionado para :attribute é inválido.',
    'exists' => 'O valor selecionado para :attribute é inválido.',
    'extensions' => 'O campo :attribute deve ter uma das seguintes extensões: :values.',
    'file' => 'O campo :attribute deve conter um arquivo.',
    'filled' => 'O campo :attribute é obrigatório.',

    'gt' => [
        'array' => 'O campo :attribute deve conter mais de :value itens.',
        'file' => 'O arquivo :attribute deve ser maior que :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser maior que :value.',
        'string' => 'O campo :attribute deve ter mais de :value caracteres.',
    ],

    'gte' => [
        'array' => 'O campo :attribute deve conter :value itens ou mais.',
        'file' => 'O arquivo :attribute deve ser maior ou igual a :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser maior ou igual a :value.',
        'string' => 'O campo :attribute deve ter :value caracteres ou mais.',
    ],

    'hex_color' => 'O campo :attribute deve conter uma cor hexadecimal válida.',
    'image' => 'O campo :attribute deve conter uma imagem.',
    'in' => 'O valor selecionado para :attribute é inválido.',
    'in_array' => 'O campo :attribute deve existir em :other.',
    'in_array_keys' => 'O campo :attribute deve conter pelo menos uma das seguintes chaves: :values.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'ip' => 'O campo :attribute deve conter um endereço IP válido.',
    'ipv4' => 'O campo :attribute deve conter um endereço IPv4 válido.',
    'ipv6' => 'O campo :attribute deve conter um endereço IPv6 válido.',
    'json' => 'O campo :attribute deve conter um JSON válido.',
    'list' => 'O campo :attribute deve ser uma lista.',
    'lowercase' => 'O campo :attribute deve estar em minúsculas.',

    'lt' => [
        'array' => 'O campo :attribute deve conter menos de :value itens.',
        'file' => 'O arquivo :attribute deve ser menor que :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser menor que :value.',
        'string' => 'O campo :attribute deve ter menos de :value caracteres.',
    ],

    'lte' => [
        'array' => 'O campo :attribute não deve conter mais de :value itens.',
        'file' => 'O arquivo :attribute deve ser menor ou igual a :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser menor ou igual a :value.',
        'string' => 'O campo :attribute deve ter :value caracteres ou menos.',
    ],

    'mac_address' => 'O campo :attribute deve conter um endereço MAC válido.',

    'max' => [
        'array' => 'O campo :attribute não deve conter mais de :max itens.',
        'file' => 'O arquivo :attribute não deve ter mais de :max kilobytes.',
        'numeric' => 'O campo :attribute não deve ser maior que :max.',
        'string' => 'O campo :attribute não deve ter mais de :max caracteres.',
    ],

    'max_digits' => 'O campo :attribute não deve ter mais de :max dígitos.',
    'mimes' => 'O campo :attribute deve conter um arquivo do tipo: :values.',
    'mimetypes' => 'O campo :attribute deve conter um arquivo do tipo: :values.',

    'min' => [
        'array' => 'O campo :attribute deve conter pelo menos :min itens.',
        'file' => 'O arquivo :attribute deve ter no mínimo :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],

    'min_digits' => 'O campo :attribute deve ter pelo menos :min dígitos.',
    'missing' => 'O campo :attribute deve estar ausente.',
    'missing_if' => 'O campo :attribute deve estar ausente quando :other for :value.',
    'missing_unless' => 'O campo :attribute deve estar ausente, a menos que :other seja :value.',
    'missing_with' => 'O campo :attribute deve estar ausente quando :values estiver presente.',
    'missing_with_all' => 'O campo :attribute deve estar ausente quando :values estiverem presentes.',
    'multiple_of' => 'O campo :attribute deve ser um múltiplo de :value.',
    'not_in' => 'O valor selecionado para :attribute é inválido.',
    'not_regex' => 'O formato do campo :attribute é inválido.',
    'numeric' => 'O campo :attribute deve ser um número.',

    'password' => [
        'letters' => 'A senha deve conter pelo menos uma letra.',
        'mixed' => 'A senha deve conter pelo menos uma letra maiúscula e uma minúscula.',
        'numbers' => 'A senha deve conter pelo menos um número.',
        'symbols' => 'A senha deve conter pelo menos um símbolo.',
        'uncompromised' => 'A senha informada apareceu em um vazamento de dados. Escolha outra senha.',
    ],

    'present' => 'O campo :attribute deve estar presente.',
    'present_if' => 'O campo :attribute deve estar presente quando :other for :value.',
    'present_unless' => 'O campo :attribute deve estar presente, a menos que :other seja :value.',
    'present_with' => 'O campo :attribute deve estar presente quando :values estiver presente.',
    'present_with_all' => 'O campo :attribute deve estar presente quando :values estiverem presentes.',
    'prohibited' => 'O campo :attribute não é permitido.',
    'prohibited_if' => 'O campo :attribute não é permitido quando :other for :value.',
    'prohibited_if_accepted' => 'O campo :attribute não é permitido quando :other for aceito.',
    'prohibited_if_declined' => 'O campo :attribute não é permitido quando :other for recusado.',
    'prohibited_unless' => 'O campo :attribute não é permitido, a menos que :other esteja entre :values.',
    'prohibits' => 'O campo :attribute impede que :other esteja presente.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_array_keys' => 'O campo :attribute deve conter entradas para: :values.',
    'required_if' => 'O campo :attribute é obrigatório quando :other for :value.',
    'required_if_accepted' => 'O campo :attribute é obrigatório quando :other for aceito.',
    'required_if_declined' => 'O campo :attribute é obrigatório quando :other for recusado.',
    'required_unless' => 'O campo :attribute é obrigatório, a menos que :other esteja entre :values.',
    'required_with' => 'O campo :attribute é obrigatório quando :values estiver presente.',
    'required_with_all' => 'O campo :attribute é obrigatório quando :values estiverem presentes.',
    'required_without' => 'O campo :attribute é obrigatório quando :values não estiver presente.',
    'required_without_all' => 'O campo :attribute é obrigatório quando nenhum de :values estiver presente.',
    'same' => 'Os campos :attribute e :other devem ser iguais.',

    'size' => [
        'array' => 'O campo :attribute deve conter :size itens.',
        'file' => 'O arquivo :attribute deve ter :size kilobytes.',
        'numeric' => 'O campo :attribute deve ser :size.',
        'string' => 'O campo :attribute deve ter :size caracteres.',
    ],

    'starts_with' => 'O campo :attribute deve começar com um dos seguintes: :values.',
    'string' => 'O campo :attribute deve conter texto.',
    'timezone' => 'O campo :attribute deve conter um fuso horário válido.',
    'ulid' => 'O campo :attribute deve conter um ULID válido.',
    'unique' => 'Este :attribute já está em uso.',
    'uploaded' => 'Falha no envio do arquivo :attribute.',
    'uppercase' => 'O campo :attribute deve estar em maiúsculas.',
    'url' => 'O campo :attribute deve conter uma URL válida.',
    'uuid' => 'O campo :attribute deve conter um UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensagens personalizadas por campo
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'mensagem personalizada',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nomes dos campos
    |--------------------------------------------------------------------------
    |
    | Sem este mapa o :attribute vira o nome da coluna ("job site id").
    | Os rótulos abaixo saíram dos rules() e dos $validationAttributes reais
    | do sistema — ver docs/pt-br-translation-audit.md.
    |
    */

    'attributes' => [

        // Identificação
        'name' => 'nome',
        'title' => 'título',
        'code' => 'código',
        'description' => 'descrição',
        'notes' => 'observações',
        'body' => 'conteúdo',
        'type' => 'tipo',
        'status' => 'situação',
        'sku' => 'SKU',

        // Pessoas e contato
        'email' => 'e-mail',
        'contact_email' => 'e-mail de contato',
        'employee_email' => 'e-mail',
        'phone' => 'telefone',
        'employee_phone' => 'telefone',
        'website' => 'site',
        'contact_name' => 'nome do contato',
        'contact_person' => 'pessoa de contato',
        'company_name' => 'razão social',
        'employee_name' => 'nome',
        'employee_title' => 'cargo',
        'employee_notes' => 'observações',
        'password' => 'senha',
        'password_confirmation' => 'confirmação da senha',
        'role_id' => 'perfil',

        // Endereço (formato BR)
        'street' => 'logradouro',
        'address_2' => 'complemento',
        'neighborhood' => 'bairro',
        'city' => 'cidade',
        'state' => 'estado',
        'postal_code' => 'CEP',
        'latitude' => 'latitude',
        'longitude' => 'longitude',

        // Projetos e locais
        'project_id' => 'projeto',
        'project_name' => 'nome do projeto',
        'job_site_id' => 'local',
        'job_site_name' => 'nome do local',
        'job_amount' => 'valor do projeto',
        'project_manager_id' => 'gerente do projeto',
        'supervisor_id' => 'supervisor',
        'client_id' => 'cliente',
        'report_date' => 'data do relatório',

        // Fornecedores e catálogo
        'supplier_id' => 'fornecedor preferencial',
        'vendor_id' => 'fornecedor',
        'category_id' => 'categoria',
        'parent_id' => 'categoria superior',
        'applicable_types' => 'tipos aplicáveis',
        'current_cost' => 'custo',
        'purchase_unit' => 'unidade de compra',
        'usage_unit' => 'unidade de uso',
        'units_per_purchase' => 'unidades por compra',
        'billing_type' => 'tipo de cobrança',
        'tax_rate_id' => 'alíquota',
        'rate' => 'alíquota',

        // Dinheiro e prazos
        'amount' => 'valor',
        'amount_source' => 'origem do valor',
        'budgeted_amount' => 'valor orçado',
        'due_date' => 'vencimento',
        'expiration_date' => 'data de validade',
        'payment_method' => 'forma de pagamento',

        // Documentos e arquivos
        'document_type_id' => 'tipo de documento',
        'document_file' => 'arquivo do documento',
        'document_notes' => 'observações',
        'source_template_id' => 'modelo',

        // Ordenação e sinalizadores
        'sort_order' => 'ordem',
        'display_order' => 'ordem de exibição',
        'is_active' => 'ativo',
        'is_default' => 'padrão',

    ],

];
