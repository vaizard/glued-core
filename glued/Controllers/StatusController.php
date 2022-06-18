<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Classes\Auth;
use Glued\Lib\Exceptions\DefaultException;
use Glued\Lib\Exceptions\AuthTokenException;
use Glued\Lib\Exceptions\AuthJwtException;
use Glued\Lib\Exceptions\AuthOidcException;
use Glued\Lib\Exceptions\DbException;
use Glued\Lib\Exceptions\TransformException;
use Linfo\Linfo;

class StatusController extends AbstractController
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
     * Get user's jwt token.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function token_fetch(Request $request, Response $response, array $args = []): Response {
        try {
            $data = $this->auth->fetch_token($request);
        }  catch (AuthJwtException | AuthTokenException $e) {
            $data['error'] = $e->getMessage();
            $data['message'] = "Login at ".$oidc['uri']['login'];
        }
        return $response->withJson($data);
    }

    /**
     * Decodes user's jwt token.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function token_decode(Request $request, Response $response, array $args = []): Response {
        // Get oidc config, jwk signing keys and the access token
        $oidc = $this->settings['oidc'];
        $certs = $this->auth->get_jwks($oidc);
        $accesstoken = $this->auth->fetch_token($request);
        $arr = $this->auth->decode_token($accesstoken, $certs);
        return $response->withJson($arr, options: JSON_UNESCAPED_SLASHES);
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
        $arr = $this->auth->decode_token($accesstoken, $certs);
        $arr['users'] = $this->auth->users();
        return $response->withJson($arr);
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
        return $response->withJson($data);
    }

}
