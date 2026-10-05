<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class MaterialController extends Controller
{
    #[OA\Get(
        path: '/api/materials',
        summary: 'Listar materiales',
        description: 'Devuelve los materiales activos ordenados por nombre. No requiere token.',
        tags: ['Materiales'],
        responses: [
            new OA\Response(response: 200, description: 'Materiales obtenidos correctamente.'),
        ]
    )]
    public function index()
    {
        $materials = Material::select('id', 'name')
            ->where('status', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Materiales obtenidos correctamente.',
            'materials' => $materials,
        ], 200);
    }

    #[OA\Post(
        path: '/api/materials',
        summary: 'Crear material',
        description: 'Crea un material nuevo. Solo administradores.',
        tags: ['Materiales'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Acero inoxidable'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Material creado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100', 'unique:materials,name'],
            ], [
                'name.required' => 'El nombre es obligatorio.',
                'name.max' => 'El nombre no puede superar los 100 caracteres.',
                'name.unique' => 'Ya existe un material con ese nombre.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $material = Material::create($validated);

        return response()->json([
            'message' => 'Material creado correctamente.',
            'material' => $material->fresh(),
        ], 201);
    }

    #[OA\Put(
        path: '/api/materials/{id}',
        summary: 'Editar material',
        description: 'Cambia el nombre de un material o lo vuelve a activar. Solo administradores.',
        tags: ['Materiales'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del material', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Aluminio anodizado'),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Material actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Material no encontrado.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json([
                'message' => 'Material no encontrado.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('materials', 'name')->ignore($material->id),
                ],
                'status' => ['sometimes', 'boolean'],
            ], [
                'name.required' => 'El nombre es obligatorio.',
                'name.max' => 'El nombre no puede superar los 100 caracteres.',
                'name.unique' => 'Ya existe un material con ese nombre.',
                'status.boolean' => 'El estado debe ser verdadero o falso.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $material->update($validated);

        return response()->json([
            'message' => 'Material actualizado correctamente.',
            'material' => $material->fresh(),
        ], 200);
    }

    #[OA\Delete(
        path: '/api/materials/{id}',
        summary: 'Desactivar material',
        description: 'Desactiva un material (no lo borra). Solo administradores.',
        tags: ['Materiales'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del material', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Material desactivado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Material no encontrado.'),
        ]
    )]
    public function destroy(string $id)
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json([
                'message' => 'Material no encontrado.',
            ], 404);
        }

        $material->update(['status' => false]);

        return response()->json([
            'message' => 'Material desactivado correctamente.',
        ], 200);
    }
}