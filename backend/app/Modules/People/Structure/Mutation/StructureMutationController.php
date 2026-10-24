<?php

declare(strict_types=1);

namespace App\Modules\People\Structure\Mutation;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Six reviewed M5.4 Department/Position mutation routes. */
final class StructureMutationController extends Controller
{
    public function createDepartment(): ResponseInterface
    { return $this->dispatch(fn(StructureMutationService $s)=>$s->create('department',JsonRequest::readObject($this->request)),201); }

    public function updateDepartment(string $id): ResponseInterface
    { return $this->dispatch(fn(StructureMutationService $s)=>$s->update('department',$id,JsonRequest::readObject($this->request))); }

    public function setDepartmentState(string $id): ResponseInterface
    { return $this->dispatch(fn(StructureMutationService $s)=>$s->setState('department',$id,JsonRequest::readObject($this->request))); }

    public function createPosition(): ResponseInterface
    { return $this->dispatch(fn(StructureMutationService $s)=>$s->create('position',JsonRequest::readObject($this->request)),201); }

    public function updatePosition(string $id): ResponseInterface
    { return $this->dispatch(fn(StructureMutationService $s)=>$s->update('position',$id,JsonRequest::readObject($this->request))); }

    public function setPositionState(string $id): ResponseInterface
    { return $this->dispatch(fn(StructureMutationService $s)=>$s->setState('position',$id,JsonRequest::readObject($this->request))); }

    /** @param callable(StructureMutationService):array<string,mixed> $task */
    private function dispatch(callable $task,int $status=200): ResponseInterface
    {
        $actor=auth()->user();
        if (!$actor instanceof User) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        if (strtolower((string)env('DINKERP_M33B_WRITE_MODE','off'))!=='enabled') {
            return ApiResponder::error($this->response,'ACCESS_MUTATION_NOT_ENABLED',403);
        }
        return ApiResponder::success($this->response,$task(new StructureMutationService($actor)),$status);
    }
}
