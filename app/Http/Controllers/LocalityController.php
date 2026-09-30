<?php

namespace App\Http\Controllers;

use App\Models\Locality;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class LocalityController extends Controller
{
    #[OA\Get(
        path: '/api/localities',
        summary: 'Listar localidades',
        description: 'Devuelve las localidades activas ordenadas por nombre, con su provincia. Se puede filtrar por provincia. No requiere token.',
        tags: ['Localidades'],
        parameters: [
            new OA\Parameter(
                name: 'province_id',
                description: 'ID de la provincia para filtrar (opcional)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 1
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Localidades obtenidas correctamente.'
            ),
        ]
    )]
    public function index(Request $request)
    {
        $query = Locality::with('province:id,name')
            ->select('id', 'province_id', 'name')
            ->where('status', true)
            ->orderBy('name');

        if ($request->filled('province_id')) {
            $query->where('province_id', $request->query('province_id'));
        }

        $localities = $query->get();

        return response()->json([
            'message' => 'Localidades obtenidas correctamente.',
            'localities' => $localities,
        ], 200);
    }

    #[OA\Get(
        path: '/api/localities/{id}',
        summary: 'Ver una localidad',
        description: 'Devuelve una localidad con su provincia. No requiere token.',
        tags: ['Localidades'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID de la localidad',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 1
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Localidad obtenida correctamente.'
            ),
            new OA\Response(
                response: 404,
                description: 'Localidad no encontrada.'
            ),
        ]
    )]
    public function show(string $id)
    {
        $locality = Locality::with('province:id,name')
            ->select('id', 'province_id', 'name', 'status')
            ->find($id);

        if (!$locality) {
            return response()->json([
                'message' => 'Localidad no encontrada.',
            ], 404);
        }

        return response()->json([
            'message' => 'Localidad obtenida correctamente.',
            'locality' => $locality,
        ], 200);
    }
}