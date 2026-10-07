<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CorsPreflightRouteTest extends CIUnitTestCase
{
    public function testApiGroupHasCatchAllOptionsRouteForPreflightRequests(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../app/Config/Routes.php');

        self::assertStringContainsString(
            "\$routes->options('(:any)', 'Api\\\\Cors::options');",
            $routes,
        );
    }
}
