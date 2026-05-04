<?php

namespace App\Modules\Meeting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Resources\PublicOptionResource;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusCatalogRequest;
use App\Modules\Meeting\Requests\ChangeStatusCatalogRequest;
use App\Modules\Meeting\Requests\StoreCatalogRequest;
use App\Modules\Meeting\Requests\UpdateCatalogRequest;
use App\Modules\Meeting\Resources\CatalogCollection;
use App\Modules\Meeting\Resources\CatalogResource;
use App\Modules\Meeting\Services\CatalogService;
use Illuminate\Database\Eloquent\Model;

abstract class BaseCatalogController extends Controller
{
    public function __construct(protected CatalogService $catalogService) {}

    abstract protected function modelClass(): string;

    abstract protected function successLabel(): string;

    public function public(FilterRequest $request)
    {
        $items = $this->catalogService->publicCatalog($this->modelClass(), $request->all());

        return $this->successCollection(CatalogResource::collection($items));
    }

    public function publicOptions(FilterRequest $request)
    {
        $items = $this->catalogService->publicOptions($this->modelClass(), $request->all());

        return $this->successCollection(PublicOptionResource::collection($items));
    }

    public function stats(FilterRequest $request)
    {
        return $this->success($this->catalogService->stats($this->modelClass(), $request->all()));
    }

    public function index(FilterRequest $request)
    {
        $items = $this->catalogService->index($this->modelClass(), $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new CatalogCollection($items));
    }

    public function show(Model $model)
    {
        return $this->successResource(new CatalogResource($this->catalogService->show($model)));
    }

    public function store(StoreCatalogRequest $request)
    {
        $model = $this->catalogService->store($this->modelClass(), $request->validated());

        return $this->successResource(new CatalogResource($model), 'Tạo '.$this->successLabel().' thành công!', 201);
    }

    public function update(UpdateCatalogRequest $request, Model $model)
    {
        $model = $this->catalogService->update($model, $request->validated());

        return $this->successResource(new CatalogResource($model), 'Cập nhật '.$this->successLabel().' thành công!');
    }

    public function destroy(Model $model)
    {
        $this->catalogService->destroy($model);

        return $this->success(null, 'Xóa '.$this->successLabel().' thành công!');
    }

    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->catalogService->bulkDestroy($this->modelClass(), $request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    public function bulkUpdateStatus(BulkUpdateStatusCatalogRequest $request)
    {
        $this->catalogService->bulkUpdateStatus($this->modelClass(), $request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    public function changeStatus(ChangeStatusCatalogRequest $request, Model $model)
    {
        $model = $this->catalogService->changeStatus($model, $request->status);

        return $this->successResource(new CatalogResource($model), 'Đổi trạng thái thành công!');
    }
}
