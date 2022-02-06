<?php

use Aws\S3\S3Client;
use FastRoute\Dispatcher;
use League\Flysystem\Filesystem;
use Symfony\Component\Dotenv\Dotenv;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

date_default_timezone_set('Europe/Brussels');

require 'vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->load('../data/.env');

$dispatcher = FastRoute\cachedDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', 'get_index');
    $r->addRoute('POST', '/', 'post_index');
}, [
    'cacheFile' => __DIR__ . '/route.cache'
]);

$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case Dispatcher::NOT_FOUND:
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['okay' => false, msg => 'not_found']);
    break;
    case Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['okay' => false, msg => 'method_not_allowed']);
    break;
    case Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        switch ($handler) {
            case 'get_index':
                echo file_get_contents('_index.html');
            break;
            case 'post_index':
                header('Content-Type: application/json; charset=utf-8');
                if(isset($_POST['moedertaal'])) {
                    if(isset($_POST['geslacht'])) {
                        if(isset($_POST['leeftijd'])) {
                            if(isset($_POST['professioneel'])) {
                                // audio bestanden uploaden naar Scaleway Object
                                $client = new S3Client([
                                    'credentials'               => [
                                        'key'                       => $_ENV['S3_KEY'],
                                        'secret'                    => $_ENV['S3_SECRET'],
                                    ],
                                    'region'                    => $_ENV['S3_REGION'],
                                    'version'                   => $_ENV['S3_VERSION'],
                                    'endpoint'                  => $_ENV['S3_ENDPOINT'],
                                ]);
                                
                                $adapter = new AwsS3V3Adapter($client, $_ENV['S3_BUCKET']);
                                //$adapter = new LocalFilesystemAdapter(__DIR__.'/_files');
                                $filesystem = new Filesystem($adapter);
    
                                $stream = fopen($_FILES['leeftijdsgenootOpname']['tmp_name'], 'r+');
                                $filesystem->writeStream(
                                    $_FILES['leeftijdsgenootOpname']['name'],
                                    $stream
                                );
                                if (is_resource($stream)) {
                                    fclose($stream);
                                }
    
                                $stream = fopen($_FILES['oudereOpname']['tmp_name'], 'r+');
                                $filesystem->writeStream(
                                    $_FILES['oudereOpname']['name'],
                                    $stream
                                );
                                if (is_resource($stream)) {
                                    fclose($stream);
                                }
    
                                // data wegschrijven in csv
                                $line = [
                                    date('c'),
                                    filter_input(INPUT_POST, 'deelnemer', FILTER_SANITIZE_STRING),
                                    filter_input(INPUT_POST, 'geslacht', FILTER_SANITIZE_STRING),
                                    filter_input(INPUT_POST, 'leeftijd', FILTER_VALIDATE_INT),
                                    filter_input(INPUT_POST, 'moedertaal', FILTER_SANITIZE_STRING),
                                    filter_input(INPUT_POST, 'professioneel', FILTER_SANITIZE_STRING),
                                    $_FILES['leeftijdsgenootOpname']['name'],
                                    $_FILES['oudereOpname']['name'],
                                    filter_input(INPUT_POST, 'browser', FILTER_SANITIZE_STRING),
                                    filter_input(INPUT_POST, 'os', FILTER_SANITIZE_STRING),
                                    filter_input(INPUT_POST, 'platform', FILTER_SANITIZE_STRING),
                                ];
                                
                                $csv_file = '../data/_dataverza.csv';
                                if(!file_exists($csv_file)) {
                                    $out = fopen($csv_file, 'a');
                                    fputcsv($out, [
                                        'datum',
                                        'deelnemer',
                                        'geslacht',
                                        'leeftijd',
                                        'moedertaal',
                                        'professioneel',
                                        'leeftijdsgenoot_opname',
                                        'oudere_opname',
                                        'browser',
                                        'os',
                                        'platform',
                                    ], ';');
                                } else {
                                    $out = fopen($csv_file, 'a');
                                }
                                fputcsv($out, $line, ';');
                                fclose($out);
    
                                http_response_code(201);
                                echo json_encode(['okay' => true]);
                            } else {
                                http_response_code(400);
                                echo json_encode(['okay' => false, msg => 'professioneel_missing']);
                            }
                        } else {
                            http_response_code(400);
                            echo json_encode(['okay' => false, msg => 'leeftijd_missing']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['okay' => false, msg => 'geslacht_missing']);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['okay' => false, msg => 'moedertaal_missing']);
                }
            break;
        }
    break;
}