<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\LogsControllerActivity;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;
    use LogsControllerActivity;

    /**
     * Ejecuta una acción y registra la actividad automáticamente.
     */
    public function callAction($method, $parameters)
    {
        $response = parent::callAction($method, $parameters);

        // Obtener el Request del contenedor si está disponible
        $request = request();
        if ($request instanceof Request) {
            $this->logControllerActivity($method, $request, $response);
        }

        return $response;
    }
}
