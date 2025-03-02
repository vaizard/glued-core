<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Glued\Lib\Controllers\AbstractService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * ServiceController
 */
class ServiceController extends AbstractService
{

    /**
     * Returns list of routes.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function getRoutes(Request $request, Response $response, array $args = []): Response {
        $data = $this->utils->get_routes( $this->utils->get_current_route($request) );
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }


    /**
     * This method overrides the `getHealth` method in AbstractService to provide
     * a service-specific health check implementation.
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function getHealth(Request $request, Response $response, array $args = []): Response
    {
        try {
            $check['service'] = basename(__ROOT__);
            $check['timestamp'] = microtime();
            $check['healthy'] = true;
            $check['status']['postgres'] = $this->pg->query("select true as test")->fetch()['test'] ?? false;
            $check['status']['auth'] = $_SERVER; // $_SERVER['X-GLUED-AUTH-UUID'] ?? 'anonymous';
        } catch (\Exception $e) {
            $check['healthy'] = false;
            return $response->withJson($check);
        }
        return $response->withJson($check);
    }


    /**
     * Returns /api home response.
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function home(Request $request, Response $response, array $args = []): Response {
        $data = [
            'message' => 'Glued API ingress.',
            'routes' => $this->settings['glued']['baseuri'].$this->routecollector->getRouteParser()->UrlFor('be_core_routes'),
            'health' => $this->settings['glued']['baseuri'].$this->routecollector->getRouteParser()->UrlFor('be_core_health'),
            'status' => 'OK'
        ];
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }


    /**
     * Returns a list of all OpenAPI specifications accessible to Glued Core.
     * Used by swagger-ui accessible on https://openapi.yourhostname on standard setups.
     *
     * @param  Request  $request  The server request.
     * @param  Response $response The response object.
     * @param  array    $args     Route arguments.
     * @return Response Json result set.
     */
    public function getOpenapis(Request $request, Response $response, array $args = []): Response {
        $data = [];
        $openapis = array_filter($this->settings['routes'], function ($route) {
            return isset($route['provides']) && $route['provides'] === 'openapi';
        });
        foreach ($openapis as $route) {
            $data[] = [
                'url' => "{$this->settings['glued']['protocol']}{$this->settings['glued']['hostname']}{$route['pattern']}",
                'name' => $route['label']
            ];
        }
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }

    public function frontendFallback(Request $request, Response $response, array $args = []): Response {
        echo <<<EOL
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Server Error</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f9f9f9;
                    color: #333;
                    text-align: center;
                    margin: 0;
                    padding: 0;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                }
                h1 {
                    color: #7a146f;
                    font-size: 2.5rem;
                    margin-bottom: 1rem;
                }
                p {
                    font-size: 1.2rem;
                    margin-bottom: 1.5rem;
                    line-height: 1.5;
                }
                a {
                    color: #0275d8;
                    text-decoration: none;
                    font-weight: bold;
                }
                a:hover {
                    text-decoration: underline;
                }
                .container {
                    max-width: 600px;
                    padding: 20px;
                    background-color: #fff;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Oops! Something went sideways 🤷‍♂️</h1>
                <p>
                    Looks like the frontend app decided to take a break (or it wasn’t invited to the party).
                    This is definitely the administrators fault, haunt the guys.
                </p>
                <p>
                    Need the APIs? Head over <a href='/api'>here</a> and knock yourself out. At least those might work.
                </p>
            </div>
        </body>
        </html>
        EOL;
        return $response->withStatus(500);
    }

    /**
     * Return complete glued configuration. NOTE that this method must me behind auth.
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function getConfig(Request $request, Response $response, array $args = []): Response {
        return $response->withJson($this->settings, options: JSON_UNESCAPED_SLASHES);
    }




}
