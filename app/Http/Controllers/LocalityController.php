<?php

namespace App\Http\Controllers;

use App\Models\Locality;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    #[OA\Post(
        path: '/api/localities',
        summary: 'Crear localidad',
        description: 'Agrega una localidad nueva a una provincia. No puede haber dos localidades con el mismo nombre en la misma provincia. Solo administradores.',
        tags: ['Localidades'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['province_id', 'name'],
                properties: [
                    new OA\Property(property: 'province_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Federación'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Localidad creada correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'province_id' => ['bail', 'required', 'integer', Rule::exists('provinces', 'id')],
                'name' => [
                    'bail',
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('localities', 'name')->where('province_id', $request->input('province_id')),
                ],
            ], [
                'province_id.required' => 'La provincia es obligatoria.',
                'province_id.integer' => 'La provincia debe ser un número entero.',
                'province_id.exists' => 'La provincia indicada no existe.',
                'name.required' => 'El nombre es obligatorio.',
                'name.max' => 'El nombre no puede superar los 100 caracteres.',
                'name.unique' => 'Ya existe una localidad con ese nombre en esa provincia.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $locality = Locality::create($validated);

        return response()->json([
            'message' => 'Localidad creada correctamente.',
            'locality' => $locality->fresh()->load('province:id,name'),
        ], 201);
    }

    #[OA\Put(
        path: '/api/localities/{id}',
        summary: 'Editar localidad',
        description: 'Cambia el nombre o la provincia de una localidad, o la vuelve a activar. No puede quedar repetida en la misma provincia. Solo administradores.',
        tags: ['Localidades'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID de la localidad', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'province_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Federación'),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Localidad actualizada correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Localidad no encontrada.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $locality = Locality::find($id);

        if (!$locality) {
            return response()->json([
                'message' => 'Localidad no encontrada.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'province_id' => ['bail', 'sometimes', 'required', 'integer', Rule::exists('provinces', 'id')],
                'name' => ['bail', 'sometimes', 'required', 'string', 'max:100'],
                'status' => ['sometimes', 'boolean'],
            ], [
                'province_id.required' => 'La provincia es obligatoria.',
                'province_id.integer' => 'La provincia debe ser un número entero.',
                'province_id.exists' => 'La provincia indicada no existe.',
                'name.required' => 'El nombre es obligatorio.',
                'name.max' => 'El nombre no puede superar los 100 caracteres.',
                'status.boolean' => 'El estado debe ser verdadero o falso.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if (empty($validated)) {
            return response()->json([
                'message' => 'No se envió ningún dato para actualizar.',
            ], 422);
        }

        $provinceId = $validated['province_id'] ?? $locality->province_id;
        $name = $validated['name'] ?? $locality->name;

        $duplicated = Locality::where('province_id', $provinceId)
            ->where('name', $name)
            ->where('id', '!=', $locality->id)
            ->exists();

        if ($duplicated) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => ['name' => ['Ya existe una localidad con ese nombre en esa provincia.']],
            ], 422);
        }

        $locality->update($validated);

        return response()->json([
            'message' => 'Localidad actualizada correctamente.',
            'locality' => $locality->fresh()->load('province:id,name'),
        ], 200);
    }

    #[OA\Delete(
        path: '/api/localities/{id}',
        summary: 'Desactivar localidad',
        description: 'Desactiva una localidad (status = 0); no se borra porque puede estar usada en clientes o pedidos. Deja de aparecer en el registro y en los pedidos nuevos. Solo administradores.',
        tags: ['Localidades'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID de la localidad', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Localidad desactivada correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Localidad no encontrada.'),
        ]
    )]
    public function destroy(string $id)
    {
        $locality = Locality::find($id);

        if (!$locality) {
            return response()->json([
                'message' => 'Localidad no encontrada.',
            ], 404);
        }

        $locality->update(['status' => false]);

        return response()->json([
            'message' => 'Localidad desactivada correctamente.',
        ], 200);
    }
}
