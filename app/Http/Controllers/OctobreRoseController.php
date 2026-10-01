<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OctobreRoseController extends Controller
{
    /**
     * L'utilisateur a vu le module de sensibilisation Octobre rose : on ne
     * le reproposera plus pour cette année.
     */
    public function infoVue(Request $request): JsonResponse
    {
        $request->user()->forceFill([
            'octobre_rose_vue_annee' => today()->year,
        ])->save();

        return response()->json(['ok' => true]);
    }
}
