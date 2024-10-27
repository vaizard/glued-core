<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Glued\Lib\Controllers\AbstractService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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
     * Returns a health status response.
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    /*
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
            'message' => 'Hello! Welcome to the API ingress route.',
            'routes' => $this->settings['glued']['baseuri'].$this->routecollector->getRouteParser()->UrlFor('be_core_routes'),
            'health' => $this->settings['glued']['baseuri'].$this->routecollector->getRouteParser()->UrlFor('be_core_health'),
            'status' => 'OK'
        ];
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }




}
