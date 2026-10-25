<?php

declare(strict_types=1);

namespace App\Modules\Product\Combo;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** The two frozen API-050 COMBO endpoints; mutation gated OFF by default. */
final class ProductComboController extends Controller
{
    public function listComboItems(string $id): ResponseInterface
    {
        $actor=auth()->user();
        if (!$actor instanceof User) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response,(new ProductComboService($actor))->list($id));
    }

    public function replaceComboItems(string $id): ResponseInterface
    {
        $actor=auth()->user();
        if (!$actor instanceof User) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        if (strtolower((string)env('DINKERP_M33B_WRITE_MODE','off')) !== 'enabled') {
            return ApiResponder::error($this->response, 'ACCESS_MUTATION_NOT_ENABLED', 403);
        }
        return ApiResponder::success($this->response,
            (new ProductComboService($actor))->replace($id,$this->replacementBody()));
    }

    /** Distinguish explicit [] from missing/null/{}, which must never clear a COMBO. */
    private function replacementBody(): array
    {
        $raw=trim((string)$this->request->getBody());
        if ($raw==='' || !in_array($raw[0], ['{','['], true)) {
            throw new BadRequestException('Expected explicit JSON array or items object.');
        }
        $value=JsonRequest::readObject($this->request);
        if ($raw[0]==='{' && !array_key_exists('items', $value)) {
            throw new BadRequestException('Replacement object requires items.');
        }
        if ($raw[0]==='[' && !array_is_list($value)) {
            throw new BadRequestException('Replacement array must be a list.');
        }
        return $value;
    }
}
