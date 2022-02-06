<?php

use Aws\S3\S3Client;
use League\Flysystem\Filesystem;
use Symfony\Component\Dotenv\Dotenv;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

require 'vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', 'get_index');
    $r->addRoute('POST', '/', 'post_index');
});

$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['okay' => false, msg => 'not_found']);
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['okay' => false, msg => 'method_not_allowed']);
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        switch ($handler) {
            case 'get_index':
                echo file_get_contents('_index.html');
                break;
            case 'post_index':
                header('Content-Type: application/json; charset=utf-8');
                if(isset($_POST['geslacht'])) {
                    if(isset($_POST['leeftijd'])) {
                        if(isset($_POST['professioneel'])) {
                            // audio bestanden uploaden naar Scaleway Object
                            $client = new S3Client([
                                'credentials' => [
                                    'key'    => $_ENV['S3_KEY'],
                                    'secret' => $_ENV['S3_SECRET'],
                                ],
                                'region' => $_ENV['S3_REGION'],
                                'version' => $ENV['S3_VERSION'],
                                'endpoint' => $_ENV['S3_ENDPOINT'],
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
                            $line = [date('c'), $_POST['geslacht'], $_POST['leeftijd'], $_POST['professioneel'], 'normaal_file', 'oudere_file'];
                            $out = fopen('_dataverza.csv', 'a');
                            fputcsv($out, $line);
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
                
        }
        break;
}