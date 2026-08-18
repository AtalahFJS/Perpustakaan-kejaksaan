<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Dipakai seluruh controller untuk menegakkan DocumentPolicy (F-8.4).
    use AuthorizesRequests;
}
