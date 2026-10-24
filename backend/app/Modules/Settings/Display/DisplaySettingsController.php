<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Exact five baseline Display Settings operations. */
final class DisplaySettingsController extends Controller
{
    public function runtime(): ResponseInterface { return ApiResponder::success($this->response, $this->service()->runtime($this->request->getGet())); }
    public function definitions(): ResponseInterface { return ApiResponder::success($this->response, $this->service()->definitions()); }
    public function values(): ResponseInterface { return ApiResponder::success($this->response, $this->service()->values($this->request->getGet())); }
    public function update(): ResponseInterface { return $this->dispatch(fn (DisplaySettingsService $s) => $s->update(JsonRequest::readObject($this->request))); }
    public function reset(): ResponseInterface { return $this->dispatch(fn (DisplaySettingsService $s) => $s->reset(JsonRequest::readObject($this->request))); }

    private function service(): DisplaySettingsService
    {
        $actor = auth()->user();
        if (!$actor instanceof User) throw new \App\Exceptions\DomainException('UNAUTHENTICATED');
        return new DisplaySettingsService($actor);
    }

    /** @param callable(DisplaySettingsService):mixed $callback */
    private function dispatch(callable $callback): ResponseInterface
    {
        $actor = auth()->user();
        if (!$actor instanceof User) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        if (strtolower((string) env('DINKERP_M33B_WRITE_MODE', 'off')) !== 'enabled') {
            return ApiResponder::error($this->response, 'ACCESS_MUTATION_NOT_ENABLED', 403);
        }
        return ApiResponder::success($this->response, $callback(new DisplaySettingsService($actor)));
    }
}
