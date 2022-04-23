<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FeController extends AbstractController
{

    //
    // React UI ingress
    //
    public function render_ui(Request $request, Response $response, array $args = []): Response {
        echo "Return glued-react here.";
        return $response;
    }   

    //
    // UI Status methods
    //
    public function render_phpinfo(Request $request, Response $response, array $args = []): Response {
        header('Access-Control-Allow-Origin: *');
        phpinfo();
        return $response;
    }
    
}
