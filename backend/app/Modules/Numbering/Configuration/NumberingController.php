<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Configuration;

use App\Exceptions\DomainException;
use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Six exact Resource Numbering Series API operations from baseline v1.2.4. */
final class NumberingController extends Controller
{
    public function listing(string $resource): ResponseInterface
    {
        $data = $this->service()->listing($resource);
        return ApiResponder::success($this->response,$data,200,['page'=>1,'page_size'=>max(1,count($data)),'total'=>count($data)]);
    }
    public function show(string $resource, string $series): ResponseInterface
    { return ApiResponder::success($this->response,$this->service()->detail($resource,$series)); }
    public function create(string $resource): ResponseInterface
    { return $this->dispatch(fn (NumberingService $s) => $s->create($resource,JsonRequest::readObject($this->request)), 201); }
    public function update(string $resource,string $series): ResponseInterface
    { return $this->dispatch(fn (NumberingService $s) => $s->update($resource,$series,JsonRequest::readObject($this->request))); }
    public function setState(string $resource,string $series): ResponseInterface
    { return $this->dispatch(fn (NumberingService $s) => $s->setState($resource,$series,JsonRequest::readObject($this->request)['status'] ?? null)); }
    public function validateConfig(string $resource): ResponseInterface
    { return ApiResponder::success($this->response,$this->service()->validateConfig($resource,JsonRequest::readObject($this->request))); }

    private function service(): NumberingService
    {
        $actor = auth()->user();
        if (!$actor instanceof User) throw new DomainException('UNAUTHENTICATED');
        return new NumberingService($actor);
    }

    /** @param callable(NumberingService):mixed $callback */
    private function dispatch(callable $callback, int $status = 200): ResponseInterface
    {
        $actor = auth()->user();
        if (!$actor instanceof User) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        if (strtolower((string) env('DINKERP_M33B_WRITE_MODE','off')) !== 'enabled') {
            return ApiResponder::error($this->response, 'ACCESS_MUTATION_NOT_ENABLED', 403);
        }
        return ApiResponder::success($this->response, $callback(new NumberingService($actor)), $status);
    }
}
