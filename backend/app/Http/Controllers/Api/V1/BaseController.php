<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class BaseController extends Controller
{
    protected string $modelClass;

    protected array $searchableFields = [];

    protected array $withRelations = [];

    protected int $defaultPerPage = 15;

    protected function rules(Request $request, ?string $id = null): array
    {
        return [];
    }

    protected function additionalQuery(Request $request, $query): void {}

    protected function beforeStore(Request $request, array $validated): array
    {
        return $validated;
    }

    protected function afterStore(Model $record): void {}

    protected function beforeUpdate(Request $request, Model $record, array $validated): array
    {
        return $validated;
    }

    protected function afterUpdate(Model $record): void {}

    protected function beforeDestroy(Model $record): void {}

    protected function indexQuery(Request $request)
    {
        $model = new $this->modelClass;
        $query = $model->with($this->withRelations);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $field) {
                    $q->orWhereRaw('LOWER(' . $field . ') LIKE ?', ['%' . strtolower($search) . '%']);
                }
            });
        }

        $this->additionalQuery($request, $query);

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->get('per_page', $this->defaultPerPage)), 100);
        $items = $this->indexQuery($request)->paginate($perPage);

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules($request));
        $data = $this->beforeStore($request, $validated);

        /** @var Model $record */
        $record = ($this->modelClass)::create($data);
        $record->load($this->withRelations);

        $this->afterStore($record);

        return response()->json(['data' => $record], 201);
    }

    public function show(string $id): JsonResponse
    {
        $record = ($this->modelClass)::with($this->withRelations)->findOrFail($id);

        return response()->json(['data' => $record]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $record = ($this->modelClass)::findOrFail($id);
        $validated = $request->validate($this->rules($request, $id));
        $data = $this->beforeUpdate($request, $record, $validated);

        $record->update($data);
        $record->load($this->withRelations);

        $this->afterUpdate($record);

        return response()->json(['data' => $record]);
    }

    public function destroy(string $id): JsonResponse
    {
        $record = ($this->modelClass)::findOrFail($id);
        $this->beforeDestroy($record);

        $record->delete();

        return response()->json(['message' => class_basename($this->modelClass) . ' deleted successfully.'], 200);
    }
}
