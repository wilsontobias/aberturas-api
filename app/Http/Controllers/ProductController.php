<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/api/products',
        summary: 'Ver el catálogo',
        description: 'Devuelve los productos activos con su categoría, material y disponibilidad. Se puede filtrar por stock y por categoría. No requiere token.',
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(name: 'in_stock', description: 'Enviar 1 para ver solo los productos con stock', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'category_id', description: 'ID de la categoría para filtrar', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Productos obtenidos correctamente.'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Product::with('category:id,name', 'material:id,name')
            ->where('status', true)
            ->orderBy('name');

        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        return response()->json([
            'message' => 'Productos obtenidos correctamente.',
            'products' => $query->get(),
        ], 200);
    }

    #[OA\Get(
        path: '/api/products/{id}',
        summary: 'Ver un producto',
        description: 'Devuelve un producto activo con su categoría y material. No requiere token.',
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del producto', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto obtenido correctamente.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
        ]
    )]
    public function show(string $id)
    {
        $product = Product::with('category:id,name', 'material:id,name')
            ->where('status', true)
            ->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Producto obtenido correctamente.',
            'product' => $product,
        ], 200);
    }

    #[OA\Post(
        path: '/api/products',
        summary: 'Crear producto',
        description: 'Crea un producto nuevo en el catálogo. Solo administradores.',
        tags: ['Productos'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['category_id', 'material_id', 'name', 'width_cm', 'height_cm', 'price', 'stock'],
                properties: [
                    new OA\Property(property: 'category_id', type: 'integer', example: 1),
                    new OA\Property(property: 'material_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Ventana batiente de aluminio'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Ventana de una hoja con vidrio simple.'),
                    new OA\Property(property: 'width_cm', type: 'integer', minimum: 1, example: 60),
                    new OA\Property(property: 'height_cm', type: 'integer', minimum: 1, example: 90),
                    new OA\Property(property: 'color', type: 'string', nullable: true, example: 'Blanco'),
                    new OA\Property(property: 'price', type: 'number', minimum: 0, example: 95000.50),
                    new OA\Property(property: 'stock', type: 'integer', minimum: 0, example: 5),
                    new OA\Property(property: 'image', type: 'string', nullable: true, example: 'https://ejemplo.com/ventana.jpg'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Producto creado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')->where('status', true)],
                'material_id' => ['required', 'integer', Rule::exists('materials', 'id')->where('status', true)],
                'name' => ['required', 'string', 'max:150'],
                'description' => ['nullable', 'string'],
                'width_cm' => ['required', 'integer', 'min:1'],
                'height_cm' => ['required', 'integer', 'min:1'],
                'color' => ['nullable', 'string', 'max:50'],
                'price' => ['required', 'numeric', 'min:0'],
                'stock' => ['required', 'integer', 'min:0'],
                'image' => ['nullable', 'string', 'max:255'],
            ], $this->messages());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'product' => $product->fresh()->load('category:id,name', 'material:id,name'),
        ], 201);
    }

    #[OA\Put(
        path: '/api/products/{id}',
        summary: 'Editar producto',
        description: 'Modifica los datos de un producto o lo vuelve a activar. Solo se cambian los campos enviados. Solo administradores.',
        tags: ['Productos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del producto', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Ventana corrediza de aluminio reforzada'),
                    new OA\Property(property: 'price', type: 'number', example: 195000),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Producto actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'category_id' => ['sometimes', 'required', 'integer', Rule::exists('product_categories', 'id')->where('status', true)],
                'material_id' => ['sometimes', 'required', 'integer', Rule::exists('materials', 'id')->where('status', true)],
                'name' => ['sometimes', 'required', 'string', 'max:150'],
                'description' => ['sometimes', 'nullable', 'string'],
                'width_cm' => ['sometimes', 'required', 'integer', 'min:1'],
                'height_cm' => ['sometimes', 'required', 'integer', 'min:1'],
                'color' => ['sometimes', 'nullable', 'string', 'max:50'],
                'price' => ['sometimes', 'required', 'numeric', 'min:0'],
                'stock' => ['sometimes', 'required', 'integer', 'min:0'],
                'image' => ['sometimes', 'nullable', 'string', 'max:255'],
                'status' => ['sometimes', 'boolean'],
            ], $this->messages());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'product' => $product->fresh()->load('category:id,name', 'material:id,name'),
        ], 200);
    }

    #[OA\Delete(
        path: '/api/products/{id}',
        summary: 'Desactivar producto',
        description: 'Desactiva un producto (no lo borra): deja de aparecer en el catálogo. Solo administradores.',
        tags: ['Productos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del producto', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto desactivado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
        ]
    )]
    public function destroy(string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        $product->update(['status' => false]);

        return response()->json([
            'message' => 'Producto desactivado correctamente.',
        ], 200);
    }

    #[OA\Patch(
        path: '/api/products/{id}/stock',
        summary: 'Actualizar el stock',
        description: 'Cambia solo la cantidad en stock de un producto. Solo administradores.',
        tags: ['Productos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del producto', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 3),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['stock'],
                properties: [
                    new OA\Property(property: 'stock', type: 'integer', minimum: 0, example: 8),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Stock actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Producto no encontrado.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function updateStock(Request $request, string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'stock' => ['required', 'integer', 'min:0'],
            ], $this->messages());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $product->update(['stock' => $validated['stock']]);

        return response()->json([
            'message' => 'Stock actualizado correctamente.',
            'product' => $product->fresh(),
        ], 200);
    }

    private function messages(): array
    {
        return [
            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.exists' => 'La categoría seleccionada no existe o no está activa.',
            'material_id.required' => 'El material es obligatorio.',
            'material_id.exists' => 'El material seleccionado no existe o no está activo.',
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar los 150 caracteres.',
            'width_cm.required' => 'El ancho es obligatorio.',
            'width_cm.integer' => 'El ancho debe ser un número entero.',
            'width_cm.min' => 'El ancho debe ser mayor a 0.',
            'height_cm.required' => 'El alto es obligatorio.',
            'height_cm.integer' => 'El alto debe ser un número entero.',
            'height_cm.min' => 'El alto debe ser mayor a 0.',
            'color.max' => 'El color no puede superar los 50 caracteres.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número.',
            'price.min' => 'El precio no puede ser negativo.',
            'stock.required' => 'El stock es obligatorio.',
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock.min' => 'El stock no puede ser negativo.',
            'status.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }
}