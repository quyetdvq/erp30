<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class P1Api050ProductCatalogTest extends CIUnitTestCase
{
    public function testRuntimeAndOpenApiExposeExactlyTwentySixOperations(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $openapi = file_get_contents(__DIR__ . '/../../../contracts/openapi/openapi.yaml');
        self::assertIsString($routes);
        self::assertIsString($openapi);

        self::assertSame(26, substr_count($routes, 'Api\\\\ProductCatalog::'));
        $expected = [
            'listProductGroups', 'createProductGroup', 'getProductGroup', 'updateProductGroup', 'setProductGroupState',
            'listProductUoms', 'createProductUom', 'getProductUom', 'updateProductUom', 'setProductUomState',
            'listProducts', 'createProduct', 'getProduct', 'updateProduct', 'setProductState',
            'listProductUomConversions', 'replaceProductUomConversions', 'listComboItems', 'replaceComboItems',
            'listPriceLists', 'createPriceList', 'getPriceList', 'updatePriceList', 'setPriceListState',
            'listPriceListItems', 'replacePriceListItems',
        ];
        foreach ($expected as $operationId) {
            self::assertStringContainsString('operationId: ' . $operationId, $openapi);
        }
        self::assertSame(26, preg_match_all('/operationId: (?:listProductGroups|createProductGroup|getProductGroup|updateProductGroup|setProductGroupState|listProductUoms|createProductUom|getProductUom|updateProductUom|setProductUomState|listProducts|createProduct|getProduct|updateProduct|setProductState|listProductUomConversions|replaceProductUomConversions|listComboItems|replaceComboItems|listPriceLists|createPriceList|getPriceList|updatePriceList|setPriceListState|listPriceListItems|replacePriceListItems)\b/', $openapi, $matches));
    }

    public function testPendingAndOutOfScopeSurfacesRemainAbsent(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        $openapi = file_get_contents(__DIR__ . '/../../../contracts/openapi/openapi.yaml');
        self::assertIsString($routes);
        self::assertIsString($openapi);
        foreach (['/export', '/price-resolutions', 'preview-combo', 'product-import'] as $pending) {
            self::assertStringNotContainsString($pending, $routes);
            self::assertStringNotContainsString($pending, $openapi);
        }
    }

    public function testApplicationBoundaryRetainsDerivedProductAndAtomicDomainHooks(): void
    {
        $service = file_get_contents(__DIR__ . '/../../app/Services/Api050ProductCatalogService.php');
        $uom = file_get_contents(__DIR__ . '/../../app/Modules/Product/Uom/ProductUomService.php');
        self::assertIsString($service);
        self::assertIsString($uom);
        foreach (['getRole', 'getFullName', 'isSelectable', 'isLeafTransactionable', 'product_group', 'product_uom', 'price_list', 'CommonListQueryAdapter', "product_code'] = \$row['code']"] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString('replaceByProduct', $uom);
        self::assertStringContainsString('product_uom_conversion_replace', $uom);
    }
}
