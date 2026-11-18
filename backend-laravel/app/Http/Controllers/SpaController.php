<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SpaController
{
    public function __invoke(Request $request)
    {
        abort_if($request->is('api/*'), 404);

        $file = public_path('index.html');
        abort_unless(file_exists($file), 404);

        return response()->file($file);
    }
}
