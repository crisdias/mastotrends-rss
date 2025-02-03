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

    $html = get_page_content($url);
    if (!$html) {
        return false;
    }


    try {
        $readability->parse($html);
        // save to cache
        $content = $readability->getContent();

        $sources_link = "https://ursal.zone/links/" . urlencode($url);

        $domain = get_domain_from_url($url);
        $content = "<p><small>($domain) - (<a href=\"$sources_link\">sources</a>)</small></p>" . $content;

        file_put_contents($cacheFilePath, $content);

        return $content;
    } catch (ParseException $e) {
        // echo sprintf('Error processing text: %s', $e->getMessage());
        return false;
    }
}

function sanitize_html_for_xml($html) {
    $config = HTMLPurifier_Config::createDefault();
    $config->set('HTML.Allowed', 'p,br,h1,h2,h3,h4,h5,h6,ul,ol,li,a[href],strong,em,blockquote,img,embed');
    $config->set('HTML.TargetBlank', true);
    $config->set('Core.Encoding', 'UTF-8');

    $purifier = new HTMLPurifier($config);
    return $purifier->purify($html);
}

function get_blocked_words() {
    $blocked_words = file_get_contents(__DIR__ . '/blocked_words.txt');
    $blocked_words = explode("\n", $blocked_words);
    $blocked_words = array_map('trim', $blocked_words);
    $blocked_words = array_filter($blocked_words);
    return $blocked_words;
}

function check_blocked_content($text, $blocked_words) {
    $text = strtolower($text); // converte para minúsculo para comparação case-insensitive

    foreach ($blocked_words as $word) {
        $word = strtolower(trim($word));
        if (!empty($word) && strpos($text, $word) !== false) {
            debugme("Blocked word found: " . $word . " in text: " . substr($text, 0, 100) . "...");
            return true;
        }
    }
    return false;
}

function convert_relative_urls($html, $base_url) {
    if (empty($html)) return $html;

    // Remove trailing slash from base_url if exists
    $base_url = rtrim($base_url, '/');

    // Converte URLs que começam com / (path absoluto)
    $html = preg_replace_callback(
        '/(src|href)=(["\'])(\/[^"\']*)(["\'])/i',
        function($matches) use ($base_url) {
            return $matches[1] . '=' . $matches[2] . $base_url . $matches[3] . $matches[4];
        },
        $html
    );

    // Converte URLs que começam com # (âncoras)
    $html = preg_replace_callback(
        '/(href=["\'](#[^"\']*)["\'])/i',
        function($matches) use ($base_url) {
            $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            return 'href="' . $base_url . $currentPath . $matches[2] . '"';
        },
        $html
    );

    // Converte URLs relativas (sem / no início)
    $html = preg_replace_callback(
        '/(src|href)=(["\'])(?!\w+:\/\/)(?!\/|#)([^"\']*)(["\'])/i',
        function($matches) use ($base_url) {
            return $matches[1] . '=' . $matches[2] . $base_url . '/' . $matches[3] . $matches[4];
        },
        $html
    );

    // Remove referências de caminho relativo ../
    $html = preg_replace('/\.\.\//', '', $html);

    return $html;
}



function parseJsonToItems($json) {
    debugme("entrei no parseJsonToItems");
    $input = json_decode($json);
    $items = [];
    $used_guids = [];

    $blocked_words = get_blocked_words();

    foreach ($input as $item) {
        // debugme("\n\nProcessing new item: " . $item->url);

        $guid = htmlspecialchars($item->url, ENT_QUOTES | ENT_XML1, 'UTF-8');

        if (in_array($guid, $used_guids)) {
            continue;
        }

        if (check_blocked_content($item->title, $blocked_words)) {
            continue;
        }

        $readable = get_readable_content($item->url);
        if (!$readable) {
            $readable = $item->provider_name . ' | ' . $item->description;
        }

        $base_url = parse_url($item->url, PHP_URL_SCHEME) . '://' . parse_url($item->url, PHP_URL_HOST);
        $readable = convert_relative_urls($readable, $base_url);
        $readable = sanitize_html_for_xml($readable);

        if (check_blocked_content($readable, $blocked_words)) {
            continue;
        }

        $used_guids[] = $guid;
        $filtered_title = htmlspecialchars($item->title, ENT_NOQUOTES);

        $image_mime_type = get_image_mime($item->image);

        $parsedItem = [
            'title' => $filtered_title,
            'image' => $item->image,
            'image_mime' => $image_mime_type,
            'link' => htmlspecialchars($item->url, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            'description' => $readable,
            'guid' => $guid
        ];

        $items[] = $parsedItem;
    }

    return $items;
}

function get_image_mime($url) {
    $headers = get_headers($url, 1);
    if (isset($headers['Content-Type'])) {
        return $headers['Content-Type'];
    }
    return false;
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


    if (getenv('APP_ENV') !== 'development') {
        return;
    }

    // error_log(''. print_r($var, true) . "\n", 3, __DIR__ . '/debug.log');
    file_put_contents(__DIR__ . '/debug.log', print_r($var, true) . "\n\n", FILE_APPEND);
}

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

function get_page_content($url) {
    debugme("get_page_content: $url");
    $client = new Client([
        'timeout'         => 5.0,
        'connect_timeout' => 5.0,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'
        ],
        'allow_redirects' => true
    ]);




    try {
        $response = $client->get($url);
        $html = (string) $response->getBody();

        // Normalizar encoding
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        return $html;
    } catch (RequestException $e) {
        error_log("Erro ao buscar a URL $url: " . $e->getMessage());
        return null;
    }
}


?>
