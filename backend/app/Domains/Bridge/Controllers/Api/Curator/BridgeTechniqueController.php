<?php

namespace App\Domains\Bridge\Controllers\Api\Curator;

use App\Domains\Bridge\Models\BridgeTechnique;
use App\Domains\Bridge\Models\BridgeTechniqueVariant;
use App\Domains\Bridge\Requests\BridgeVariantRequest;
use App\Domains\Bridge\Requests\StoreBridgeTechniqueRequest;
use App\Domains\Bridge\Requests\UpdateBridgeTechniqueRequest;
use App\Domains\Bridge\Resources\BridgeTechniqueResource;
use App\Domains\Bridge\Services\BridgeService;
use App\Domains\Players\Resources\UserCardResource;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Куратор бридж-теста: ведёт каталог видов и подвидов.
 *
 * Раздел доступен только куратору и админу.
 */
class BridgeTechniqueController extends Controller
{
    public function __construct(private readonly BridgeService $bridge)
    {
    }

    /** Весь каталог, включая выключенные виды и подвиды. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->bridge->fullCatalog()->map(fn (BridgeTechnique $technique) => [
                'id' => $technique->id,
                'key' => $technique->key,
                'label' => $technique->label,
                'description' => $technique->description,
                'is_active' => (bool) $technique->is_active,
                'sort_order' => (int) $technique->sort_order,
                'variants' => $technique->allVariants->map(fn (BridgeTechniqueVariant $variant) => [
                    'id' => $variant->id,
                    'key' => $variant->key,
                    'label' => $variant->label,
                    'is_active' => (bool) $variant->is_active,
                    'sort_order' => (int) $variant->sort_order,
                ])->values(),
            ])->values(),
        ]);
    }

    /** Добавить вид. */
    public function store(StoreBridgeTechniqueRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $technique = $this->bridge->createTechnique(
            label: $validated['label'],
            description: $validated['description'] ?? null,
        );

        return response()->json([
            'technique' => (new BridgeTechniqueResource(
                $technique->load('variants')
            ))->resolve(),
        ], 201);
    }

    /** Изменить вид. */
    public function update(UpdateBridgeTechniqueRequest $request, BridgeTechnique $technique): JsonResponse
    {
        $updated = $this->bridge->updateTechnique($technique, $request->validated());

        return response()->json([
            'technique' => (new BridgeTechniqueResource($updated->load('variants')))->resolve(),
        ]);
    }

    /** Удалить вид: если по нему есть заявки — просто выключаем. */
    public function destroy(BridgeTechnique $technique): JsonResponse
    {
        $this->bridge->deleteTechnique($technique);

        return response()->json(['ok' => true]);
    }

    /** Добавить подвид. */
    public function storeVariant(BridgeVariantRequest $request, BridgeTechnique $technique): JsonResponse
    {
        $variant = $this->bridge->createVariant($technique, $request->validated()['label']);

        return response()->json([
            'variant' => [
                'id' => $variant->id,
                'key' => $variant->key,
                'label' => $variant->label,
                'is_active' => (bool) $variant->is_active,
                'sort_order' => (int) $variant->sort_order,
            ],
        ], 201);
    }

    /** Изменить подвид. */
    public function updateVariant(BridgeVariantRequest $request, BridgeTechniqueVariant $variant): JsonResponse
    {
        $updated = $this->bridge->updateVariant($variant, $request->validated());

        return response()->json([
            'variant' => [
                'id' => $updated->id,
                'key' => $updated->key,
                'label' => $updated->label,
                'is_active' => (bool) $updated->is_active,
                'sort_order' => (int) $updated->sort_order,
            ],
        ]);
    }

    /** Удалить подвид. */
    public function destroyVariant(BridgeTechniqueVariant $variant): JsonResponse
    {
        $this->bridge->deleteVariant($variant);

        return response()->json(['ok' => true]);
    }
}
