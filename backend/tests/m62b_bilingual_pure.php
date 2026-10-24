<?php

declare(strict_types=1);

/** M6.2B EN/VI Product errors, frozen catalog preservation and safe 500 handling. */
if (! function_exists('lang')) {
    function lang(string $line, array $args=[], ?string $locale=null): string
    {
        [$group,$key]=explode('.', $line, 2);
        $path=__DIR__.'/../app/Language/'.($locale==='en' ? 'en':'vi').'/'.$group.'.php';
        $catalog=require $path;
        return (string)($catalog[$key]??$line);
    }
}
require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Shared/Http/RequestMetadata.php';
require_once __DIR__.'/../app/Shared/Http/ApiEnvelope.php';
require_once __DIR__.'/../app/Shared/Http/ApiResponder.php';
require_once __DIR__.'/../app/Shared/Http/ApiErrorMapper.php';

use App\Exceptions\DomainException;
use App\Shared\Http\ApiResponder;
use App\Shared\Http\ApiErrorMapper;
use App\Shared\Http\RequestMetadata;

$n=0;
$check=static function(bool $ok,string $message) use (&$n):void {
    if (!$ok) throw new RuntimeException('M6.2B bilingual: '.$message);
    ++$n;
};
$catalogs=[];
foreach(['en','vi'] as $locale) {
    $catalogs[$locale]=require __DIR__.'/../app/Language/'.$locale.'/ProductRuntime.php';
    $check(count($catalogs[$locale])>=34,$locale.' Product translations coverage');
    $check(count($catalogs[$locale])===count(array_unique(array_keys($catalogs[$locale]))),$locale.' no duplicate message');
    RequestMetadata::begin('m62b-'. $locale,$locale);
    foreach($catalogs[$locale] as $error=>$message) {
        $check(is_string($message) && strlen($message)>=12, $locale.' '.$error.' nonempty localized message');
        $mapped=ApiErrorMapper::map(new DomainException($error));
        if ($mapped['status']===500) {
            // M6.3A exposes one intentionally stable and localized storage
            // failure without revealing filesystem paths or PHP diagnostics.
            if ($error === 'PRODUCT_IMAGE_STORAGE_FAILED') {
                $check($mapped['code']===$error, $locale.' safe media storage failure code');
                $check(ApiResponder::message($mapped['code'],500)===$message, $locale.' safe media storage message');
            } else {
                $check($mapped['code']==='INTERNAL_SERVER_ERROR', $locale.' hides internal Product error');
            }
        } else {
            $check($mapped['code']===$error, $locale.' preserves domain code: '.$error);
            $check(ApiResponder::message($mapped['code'],$mapped['status'])===$message, $locale.' translated '.$error);
        }
    }
    $group=require __DIR__.'/../app/Language/'.$locale.'/ProductGroup.php';
    $uom=require __DIR__.'/../app/Language/'.$locale.'/ProductUomMaster.php';
    $check(ApiResponder::message('PRODUCT_GROUP_NOT_FOUND',404)===$group['PRODUCT_GROUP_NOT_FOUND'],$locale.' frozen ProductGroup');
    $check(ApiResponder::message('PRODUCT_UOM_NOT_FOUND',404)===$uom['PRODUCT_UOM_NOT_FOUND'],$locale.' frozen ProductUom');
    $check(ApiResponder::message('PRODUCT_GROUP_NOT_EFFECTIVELY_ACTIVE',422)===$group['PRODUCT_GROUP_NOT_EFFECTIVELY_ACTIVE'],$locale.' correct group hierarchy code');
    $check(ApiResponder::message('WF_INVALID_TRANSITION',422)===(require __DIR__.'/../app/Language/'.$locale.'/Api.php')['validation'],$locale.' preexisting Workflow fallback');
}
RequestMetadata::begin('m62b-en','en');
$check(ApiResponder::message('PRODUCT_IDENTIFIER_DUPLICATE',409)!==
    (function(){RequestMetadata::begin('m62b-vi','vi');return ApiResponder::message('PRODUCT_IDENTIFIER_DUPLICATE',409);})(),
    'different user messages EN/VI');
$check(ApiErrorMapper::map(new DomainException('PRODUCT_IDENTIFIER_DUPLICATE'))['status']===409,'duplicate maps conflict');
$check(ApiErrorMapper::map(new DomainException('PRODUCT_AUTHORIZATION_DENIED'))['status']===403,'PBAC maps forbidden');
echo "M6.2B BILINGUAL: {$n}/{$n} PASS\n";
