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
        echo "<h1>Hello and welcome</h1><br>If you're seeing this, the webapp component glued-react did not load for you.<br>This is either by misconfiguration or because you're a developer.<br>Need the APIs? Go <a href='/api/core/v1/routes'>here</a>.";
        return $response;
    }

}
