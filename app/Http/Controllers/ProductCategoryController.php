<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ProductCategoryController extends Controller
{
    #[OA\Get(
        path: '/api/categories',
        summary: 'Listar categorías',
        description: 'Devuelve las categorías activas ordenadas por nombre. No requiere token.',
        tags: ['Categorías'],
        responses: [
            new OA\Response(response: 200, description: 'Categorías obtenidas correctamente.'),
        ]
    )]
    public function index()
    {
        $categories = ProductCategory::select('id', 'name')
            ->where('status', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Categorías obtenidas correctamente.',
            'categories' => $categories,
        ], 200);
    }

    #[OA\Post(
        path: '/api/categories',
        summary: 'Crear categoría',
        description: 'Crea una categoría nueva. Solo administradores.',
        tags: ['Categorías'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Mamparas'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Categoría creada correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100', 'unique:product_categories,name'],
            ], [
                'name.required' => 'El nombre es obligatorio.',
                'name.max' => 'El nombre no puede superar los 100 caracteres.',
                'name.unique' => 'Ya existe una categoría con ese nombre.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $category = ProductCategory::create($validated);

        return response()->json([
            'message' => 'Categoría creada correctamente.',
            'category' => $category->fresh(),
        ], 201);
    }

    #[OA\Put(
        path: '/api/categories/{id}',
        summary: 'Editar categoría',
        description: 'Cambia el nombre de una categoría o la vuelve a activar. Solo administradores.',
        tags: ['Categorías'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID de la categoría', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Ventanas corredizas'),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Categoría actualizada correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Categoría no encontrada.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $category = ProductCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Categoría no encontrada.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('product_categories', 'name')->ignore($category->id),
                ],
                'status' => ['sometimes', 'boolean'],
            ], [
                'name.required' => 'El nombre es obligatorio.',
                'name.max' => 'El nombre no puede superar los 100 caracteres.',
                'name.unique' => 'Ya existe una categoría con ese nombre.',
                'status.boolean' => 'El estado debe ser verdadero o falso.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $category->update($validated);

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'category' => $category->fresh(),
        ], 200);
    }

    #[OA\Delete(
        path: '/api/categories/{id}',
        summary: 'Desactivar categoría',
        description: 'Desactiva una categoría (no la borra). Solo administradores.',
        tags: ['Categorías'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID de la categoría', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Categoría desactivada correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Categoría no encontrada.'),
        ]
    )]
    public function destroy(string $id)
    {
        $category = ProductCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Categoría no encontrada.',
            ], 404);
        }

        $category->update(['status' => false]);

        return response()->json([
            'message' => 'Categoría desactivada correctamente.',
        ], 200);
    }
}