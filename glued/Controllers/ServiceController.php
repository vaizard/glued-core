<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Glued\Lib\Controllers\AbstractService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Lib\Exceptions\TransformException;

class ServiceController extends AbstractService
{

    /**
     * Returns list of routes.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function routes_list(Request $request, Response $response, array $args = []): Response {
        $data = $this->utils->get_routes( $this->utils->get_current_route($request) );
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }

    /**
     * Returns an exception.
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function stub(Request $request, Response $response, array $args = []): Response {
        throw new \Exception('Stub method served where it shouldn\'t. Proxy misconfigured?');
    }

    /**
     * Returns a health status response.
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function health(Request $request, Response $response, array $args = []): Response {
        $params = $request->getQueryParams();
        $data = [
            'timestamp' => microtime(),
            'status' => 'OK',
            'params' => $params,
            'service' => basename(__ROOT__),
            'provided-for' => $_SERVER['HTTP_X-GLUED-AUTH-UUID'] ?? 'anonymous'
        ];
        //$data['x'] = $this->auth->verify_token()
        if ($data['provided-for'] !== 'anonymous') { $this->auth->generate_api_token($_SERVER['HTTP_X-GLUED-AUTH-UUID']); }
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
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
            'message' => 'Welcome! Follow to the Uri in details to obtain a list of available routes.',
            'details' => $this->settings['glued']['protocol'].$this->settings['glued']['hostname'].$this->routecollector->getRouteParser()->UrlFor('be_core_routes'),
            'status' => 'OK'
        ];
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }

    public function apidocs(Request $request, Response $response, array $args = []): Response {
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



}
