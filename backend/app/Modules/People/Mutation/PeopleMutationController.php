<?php

declare(strict_types=1);

namespace App\Modules\People\Mutation;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** Six API-020 protected Employee and Assignment mutations. */
final class PeopleMutationController extends Controller
{
    public function createEmployee(): ResponseInterface
    {
        return $this->dispatch(fn (PeopleMutationService $service) =>
            $service->createEmployee(JsonRequest::readObject($this->request)),201);
    }
    public function updateEmployee(string $id): ResponseInterface
    {
        return $this->dispatch(fn (PeopleMutationService $service) =>
            $service->updateEmployee($id,JsonRequest::readObject($this->request)));
    }
    public function setEmployeeState(string $id): ResponseInterface
    {
        return $this->dispatch(fn (PeopleMutationService $service) =>
            $service->setEmployeeState($id,JsonRequest::readObject($this->request)['status'] ?? null));
    }
    public function createEmployeeAssignment(): ResponseInterface
    {
        return $this->dispatch(fn (PeopleMutationService $service) =>
            $service->createAssignment(JsonRequest::readObject($this->request)),201);
    }
    public function updateEmployeeAssignment(string $id): ResponseInterface
    {
        return $this->dispatch(fn (PeopleMutationService $service) =>
            $service->updateAssignment($id,JsonRequest::readObject($this->request)));
    }
    public function setEmployeeAssignmentState(string $id): ResponseInterface
    {
        return $this->dispatch(fn (PeopleMutationService $service) =>
            $service->setAssignmentState($id,JsonRequest::readObject($this->request)['status'] ?? null));
    }
    /** @param callable(PeopleMutationService):array<string,mixed> $run */
    private function dispatch(callable $run,int $status=200): ResponseInterface
    {
        $actor=auth()->user();
        if (!$actor instanceof User) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        if (strtolower((string)env('DINKERP_M33B_WRITE_MODE','off'))!=='enabled') {
            return ApiResponder::error($this->response,'ACCESS_MUTATION_NOT_ENABLED',403);
        }
        return ApiResponder::success($this->response,$run(new PeopleMutationService($actor)),$status);
    }
}
