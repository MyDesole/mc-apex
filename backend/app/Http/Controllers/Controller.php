<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Даёт $this->authorize(...) во всех контроллерах.
     * Проверки прав вынесены в политики (app/Policies), а не в abort_unless.
     */
    use AuthorizesRequests;
}
