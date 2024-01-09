<?php

use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;
use andreskrey\Readability\ParseException;


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

function create_folder_if_not_exists($folder) {
    if (!file_exists($folder)) {
        mkdir($folder, 0755, true);
    }
}

function purge_cache_files($folder, $age_in_days) {
    // traverses folder, deleting all files older than $age_in_days
    if (!file_exists($folder)) {
        return;
    }

    $files = glob($folder ."*");
    $age_in_seconds = time() - ($age_in_days * 24 * 60 * 60); //
    foreach ($files as $file) {
        // debugme($file);
        if (filemtime($file) < $age_in_seconds) {
            unlink($file);
        }
    }
}

function get_readable_content($url) {
    $configuration = new Configuration();
    $configuration
        ->setFixRelativeURLs(true)
        ->setOriginalURL($url);
    $readability = new Readability($configuration);

    create_folder_if_not_exists('_cache/read');

    $unique_filename = md5($url);
    $cacheFilePath = __DIR__ . '/_cache/read/' . $unique_filename . '.html';
    if (file_exists($cacheFilePath)) {
        $content = file_get_contents($cacheFilePath);
        return $content;
    }


    $html = file_get_contents($url);

    try {
        $readability->parse($html);
        // save to cache
        $content = $readability->getContent();

        $domain = get_domain_from_url($url);
        $content = "<p><small>($domain)</small></p>" . $content;

        file_put_contents($cacheFilePath, $content);

        return $content;
    } catch (ParseException $e) {
        // echo sprintf('Error processing text: %s', $e->getMessage());
        return false;
    }
}

function parseJsonToItems($json) {
    $input = json_decode($json);
    $items = [];

    foreach ($input as $item) {
        $readable = get_readable_content($item->url);
        if (!$readable) {
            $readable = $item->provider_name . ' | ' . $item->description;
        }

        $parsedItem = [
            'title' => $item->title,
            'link' => $item->url,
            'description' => $readable,
            'guid' => $item->url
        ];

        $items[] = $parsedItem;
    }

    return $items;
}

function download_trends($domain) {
    $limit = 50; // Número máximo de registros para recuperar
    $api_limit = 20;  // Número máximo de registros para recuperar por chamada de API
    $offset = 0;  // Inicializa o offset em 0
    $all_trends = [];  // Array para armazenar todos os registros recuperados

    while ($offset < $limit) {
        // Monta a URL com os parâmetros limit e offset
        $url = 'https://' . $domain . '/api/v1/trends/links?limit=' . $api_limit . '&offset=' . $offset;

        // Faz a chamada da API
        $json = file_get_contents($url);
        $trends = json_decode($json, true);  // Decodifica o JSON em um array

        // Verifica se algum dado foi retornado
        if (empty($trends)) {
            break;  // Sai do loop se nenhum dado foi retornado
        }

        // Adiciona os registros recuperados ao array all_trends
        $all_trends = array_merge($all_trends, $trends);


        // Atualiza o offset para a próxima chamada
        $offset += $api_limit;
    }
    return json_encode($all_trends);  // Retorna todos os registros em formato JSON
}

function get_domain_from_url($url)
{
    $parsedUrl = parse_url($url);
    $host = $parsedUrl['host'];
    $host = str_replace('www.', '', $host);
    return $host;
}


function debugme($var) {
    // echo '<pre>';
    // echo htmlentities(print_r($var, true));
    // echo '</pre>';

    // error_log(''. print_r($var, true) . "\n", 3, __DIR__ . '/debug.log');
    file_put_contents(__DIR__ . '/debug.log', print_r($var, true) . "\n", FILE_APPEND);
}

?>
