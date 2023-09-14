<?php

function valida_json($input) {
    // Carrega o JSON Schema do arquivo schema.json
    $schema_file = __DIR__ . '/schema.json';
    $schema = file_get_contents($schema_file);

    // Decodifica o JSON Schema
    $schema = json_decode($schema);

    // Decodifica o input JSON
    $input = json_decode($input);

    // Valida o input JSON usando o JSON Schema
    $validator = new JsonSchema\Validator();
    $validator->validate($input, $schema);

    if ($validator->isValid()) {
        // echo "O JSON é válido de acordo com o schema.\n";
    } else {
        // echo "O JSON não é válido de acordo com o schema:\n";
        foreach ($validator->getErrors() as $error) {
            echo sprintf(
                "  - Erro na propriedade '%s': %s\n",
                $error['property'] ? $error['property'] : '- raiz -',
                $error['message']
            );
        }
    }
}


// Função para validar se o domínio é válido e não contém caminhos, arquivos ou consultas
function validarDominio($domain) {
    // Remove espaços em branco no início e no final da string
    $domain = trim($domain);

    // Usa preg_match para verificar se o domínio corresponde à expressão regular fornecida
    if (preg_match("/^((?!-)[A-Za-z0-9-]{1,63}(?<!-)\.)+[A-Za-z]{2,6}$/", $domain)) {
        return true;
    }

    return false;
}

function parseJsonToItems($json) {
    $input = json_decode($json);

    $items = [];

    foreach ($input as $item) {
        $parsedItem = [
            'title' => $item->title,
            'link' => $item->url,
            'description' => $item->provider_name . ' | ' . $item->description,
            'guid' => $item->url // Adiciona a URL como o valor de <guid>
        ];

        $items[] = $parsedItem;
    }

    return $items;
}



?>
