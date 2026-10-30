<?php

declare(strict_types=1);

namespace App\Modules\Product\Conversion;

use App\Shared\Http\ApiResponder;
use App\Shared\Http\JsonRequest;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/** The two frozen API-050 per-Product conversion endpoints. */
final class ProductConversionController extends Controller
{
    public function listProductUomConversions(string $id): ResponseInterface
    {
        $actor = auth()->user();
        if (!$actor instanceof User) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response, (new ProductConversionService($actor))->list($id));
    }

    public function replaceProductUomConversions(string $id): ResponseInterface
    {
        $actor = auth()->user();
        if (!$actor instanceof User) return ApiResponder::error($this->response, 'UNAUTHENTICATED', 401);
        return ApiResponder::success($this->response,
            (new ProductConversionService($actor))->replace($id, $this->replacementBody()));
    }

    /** A missing JSON body or {} is NOT an explicit request to delete all UOMs. */
    private function replacementBody(): array
    {
        $raw = trim((string)$this->request->getBody());
        if ($raw === '') throw new BadRequestException('Replacement requires an explicit JSON array or items object.');
        $body = JsonRequest::readObject($this->request);
        if (str_starts_with($raw, '{') && !array_key_exists('items', $body)) {
            throw new BadRequestException('Replacement object must contain items.');
        }
        return $body;
    }
}
