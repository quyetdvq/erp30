<?php

declare(strict_types=1);

namespace App\Modules\Customer;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

final class CustomerController extends Controller
{
    private function actor(): ?User
    {
        $user=auth()->user();return $user instanceof User?$user:null;
    }
    public function index(): ResponseInterface
    {
        if (($actor=$this->actor())===null) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        $page=(new CustomerService($actor))->list($this->request->getGet());
        return ApiResponder::success($this->response,$page['data'],200,
            ['page'=>$page['page'],'page_size'=>$page['page_size'],'total'=>$page['total']]);
    }
    public function show(string $id): ResponseInterface
    {
        if (($actor=$this->actor())===null) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        return ApiResponder::success($this->response,(new CustomerService($actor))->show($id));
    }
    public function create(): ResponseInterface
    { return $this->write(fn(CustomerService $s)=>$s->create(JsonRequest::readObject($this->request)),201); }
    public function update(string $id): ResponseInterface
    { return $this->write(fn(CustomerService $s)=>$s->update($id,JsonRequest::readObject($this->request))); }
    public function setState(string $id): ResponseInterface
    { return $this->write(fn(CustomerService $s)=>$s->setState($id,JsonRequest::readObject($this->request))); }
    public function interactions(string $id): ResponseInterface
    {
        if (($actor=$this->actor())===null) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        $page=(new CustomerService($actor))->interactions($id,$this->request->getGet());
        return ApiResponder::success($this->response,$page['data'],200,
            ['page'=>$page['page'],'page_size'=>$page['page_size'],'total'=>$page['total']]);
    }
    public function addInteraction(string $id): ResponseInterface
    { return $this->write(fn(CustomerService $s)=>$s->addInteraction($id,JsonRequest::readObject($this->request)),201); }
    private function write(callable $task,int $status=200): ResponseInterface
    {
        if (($actor=$this->actor())===null) return ApiResponder::error($this->response,'UNAUTHENTICATED',401);
        return ApiResponder::success($this->response,$task(new CustomerService($actor)),$status);
    }
}
