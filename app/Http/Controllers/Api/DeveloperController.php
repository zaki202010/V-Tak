<?php

namespace App\Http\Controllers\Api;

use App\Models\Developer;
use App\Models\Setting;

class DeveloperController extends ApiController
{
    public function index()
    {
        return $this->ok([
            'project' => Setting::get('dev_project', ''),
            'items' => Developer::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->map->toPublic()->values(),
        ]);
    }
}
