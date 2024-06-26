<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Glued\Lib\Controllers\AbstractBlank;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Classes\Auth;
use Glued\Lib\Exceptions\TransformException;
use Linfo\Linfo;


class StatusController extends AbstractBlank
{

    /**
     * Return system info via the Linfo library. NOTE that this method must me behind auth.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function sysinfo(Request $request, Response $response, array $args = []): Response {
        $linfo = new Linfo(['temps'=> ['hwmon' => true]]);
        $parser = $linfo->getParser();
        $parser->determineCPUPercentage();
        $methods = ['Hostname', 'OS', 'Kernel', 'Distro', 'Uptime', 'Virtualization', 'CPU', 'HD', 'Ram', 'Load', 'Net', 'Temps'];
        foreach ($methods as $m) {
            $method = 'get' . $m;
            $data[strtolower($m)] = $parser->$method();
        }
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }


    /**
     * Returns PHP's get_defined_constants(true). NOTE that this method must me behind auth.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function phpconst(Request $request, Response $response, array $args = []): Response {
        $arr = get_defined_constants(true);
        $response->getBody()->write(json_encode($arr, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-type', 'application/json');
    }

    /**
     * Decodes user's jwt token.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function auth(Request $request, Response $response, array $args = []): Response {
        // Get oidc config, jwk signing keys and the access token
        
        $oidc = $this->settings['oidc'];
        $certs = $this->auth->get_jwks($oidc);
        $accesstoken = $this->auth->fetch_token($request);
        $arr = $this->auth->validate_jwt_token($accesstoken, $certs);
        $arr['users'] = $this->auth->users();
        return $response->withJson($arr, options: JSON_UNESCAPED_SLASHES);
    }

    /**
     * Reflects a client request.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function reflect_request(Request $request, Response $response, array $args = []): Response {
        $data = getallheaders();
        $data['http']['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $data['http']['REMOTE_PORT'] = $_SERVER['REMOTE_PORT'] ?? '';
        $data['http']['REMOTE_USER'] = $_SERVER['REMOTE_USER'] ?? '';
        $data['http']['REMOTE_X-FORWARDED-FOR'] = $_SERVER['X-FORWARDED-FOR'] ?? '';
        $data['http']['REMOTE_X-REAL-IP'] = $_SERVER['X-REAL-IP'] ?? '';
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }

    /**
     * Returns server internal state
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function server(Request $request, Response $response, array $args = []): Response {
        $data = $_SERVER;
        $this->logger->warning("core.status.server method invoked.");
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }

}
