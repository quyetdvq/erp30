<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Execution;

use App\Exceptions\DomainException;
use App\Shared\Http\ApiResponder;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** M4 business transition endpoints, separate from Workflow master CRUD. */
final class BusinessWorkflowController extends Controller
{
    public function states(string $resource): ResponseInterface
    { return ApiResponder::success($this->response,$this->service()->states($resource)); }
    public function options(string $resource,string $id): ResponseInterface
    { return ApiResponder::success($this->response,$this->service()->options($resource,$id)); }
    public function execute(string $resource,string $id,string $action): ResponseInterface
    { return $this->dispatch(fn (BusinessWorkflowService $s) => $s->execute($resource,$id,$action)); }

    private function service(): BusinessWorkflowService
    {
        $actor = auth()->user();
        if (!$actor instanceof User) throw new DomainException('UNAUTHENTICATED');
        return new BusinessWorkflowService($actor);
    }

    /** @param callable(BusinessWorkflowService):mixed $callback */
    private function dispatch(callable $callback): ResponseInterface
    {
        $actor = auth()->user();
        if (!$actor instanceof User) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        if (strtolower((string) env('DINKERP_M33B_WRITE_MODE','off')) !== 'enabled') {
            return ApiResponder::error($this->response, 'ACCESS_MUTATION_NOT_ENABLED', 403);
        }
        return ApiResponder::success($this->response, $callback(new BusinessWorkflowService($actor)));
    }
}
