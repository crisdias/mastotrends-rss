<?php

require __DIR__ . '/vendor/autoload.php';
require_once 'util.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();
if (!defined('CACHE')) {
    define('CACHE', true);
}

purge_cache_files(__DIR__ . '/_cache/read/', 10);



$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

// Rota para validar um endereço web raiz
$app->get('/feed/{domain}', function (Request $request, Response $response, $args) {
    $domain = $args['domain'];

    $cacheDirectory = __DIR__ . '/_cache';
    $cacheLifetime = 3600; // 1 hora em segundos
    // Construa um nome de arquivo único com base na URL da solicitação
    $cacheKey = md5($request->getUri()->getPath());


    // Verifique se a resposta está em cache e se ainda é válida
    $cacheFilePath = "$cacheDirectory/$cacheKey.xml";
    if (CACHE && file_exists($cacheFilePath) && (time() - filemtime($cacheFilePath)) < $cacheLifetime) {
        // Se a resposta estiver em cache e válida, retorne-a diretamente
        $response = $response->withHeader('Content-Type', 'application/rss+xml');
        $response->getBody()->write(file_get_contents($cacheFilePath));
        return $response;
    }

    // Valide o domínio
    if (validarDominio($domain) === false) {
        // Domínio inválido, você pode fazer o que precisa fazer aqui
        $response->getBody()->write("Domínio inválido: $domain");
        return $response;
    }

    $json = download_trends($domain);
    if (valida_json($json) === false) {
        $response->getBody()->write("JSON inválido");
        return $response;
    }

    // Parse o JSON para obter os itens do feed
    $items = parseJsonToItems($json);

    // Renderize o feed RSS usando o template Twig
    $twig_input = [
        'feed' => [
            'title' => 'Trending at ' . $domain,
            'link' => 'https://' . $domain,
            'description' => 'Mastodon trends at ' . $domain,
            'lastBuildDate' => date(DATE_RSS) // Data atual no formato RSS
        ],
        'items' => $items,
    ];

    $loader = new \Twig\Loader\FilesystemLoader('views');
    $twig = new \Twig\Environment($loader, [
        'cache' => '_twigcache',
    ]);
    $template = $twig->load('rss.twig');
    $feed = $template->render($twig_input);

    file_put_contents($cacheFilePath, $feed);


    // Defina o tipo de conteúdo como RSS e retorne a resposta
    $response = $response->withHeader('Cache-Control', 'public, max-age=3600');
    $response->getBody()->write($feed);
    return $response->withHeader('Content-Type', 'application/rss+xml');
});




$app->run();
