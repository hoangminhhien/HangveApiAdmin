<?php

namespace ThuongDo\Buyder\src\App\Http\Controllers;

use Illuminate\Routing\Controller;
use ThuongDo\Buyder\src\Services\BaseService;

class BaseController extends Controller
{
    protected BaseService $service;
}
