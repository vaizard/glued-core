<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


class ConfigController extends AbstractController
{

    /**
     * Return complete glued configuration. NOTE that this method must me behind auth.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function config(Request $request, Response $response, array $args = []): Response {
        return $response->withJson($this->settings, options: JSON_UNESCAPED_SLASHES);
    }  


    /**
     * Returns server internal state
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function phpinfo(Request $request, Response $response, array $args = []): Response {
        $data = ["message" => "Refer to the adm service, phpinfo route."];
        return $response->withJson($data);
    }

}
