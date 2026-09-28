<?php

namespace App\Http\Controllers;

use App\Models\Province;
use OpenApi\Attributes as OA;

class ProvinceController extends Controller
{
    #[OA\Get(
        path: '/api/provinces',
        summary: 'Listar provincias',
        description: 'Devuelve todas las provincias ordenadas por nombre. No requiere token.',
        tags: ['Provincias'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Provincias obtenidas correctamente.'
            ),
        ]
    )]
    public function index()
    {
        $provinces = Province::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Provincias obtenidas correctamente.',
            'provinces' => $provinces,
        ], 200);
    }
}