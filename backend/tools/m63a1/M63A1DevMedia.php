<?php

declare(strict_types=1);

require_once __DIR__.'/M63A1MediaPlan.php';
require_once __DIR__.'/M63A1MediaTransport.php';

/** DEV-only media integration. May modify ONLY an empty, marker-verified fixture. */
final class M63A1DevMedia
{
    /** @return array<string,mixed> */
    public function run(string $marker, string $id, string $username, string $password, bool $apply): array
    {
        M63A1MediaPlan::marker($marker);
        M63A1MediaPlan::id($id);
        $result=['verdict'=>'FAIL_DEV_PRODUCT_MEDIA', 'phase'=>'auth', 'marker'=>$marker,
            'id'=>$id, 'mode'=>$apply?'EXPLICIT_MEDIA_MUTATION':'READ_ONLY_PREFLIGHT',
            'fixture_verified'=>false, 'edit_allowed'=>false, 'gd_all_codecs'=>false,
            'negative_cases'=>0, 'localized_types'=>0,
            'upload_http'=>null, 'replace_http'=>null, 'delete_http'=>null,
            'static_first'=>null, 'static_second'=>null, 'static_old_gone'=>null,
            'physical_files_cleaned'=>false, 'http_metadata_cleared'=>false,
            'image_urls'=>[], 'last_http'=>null, 'sql_metadata_audit'=>'NOT_TESTED',
            'sql_failure_injection'=>'NOT_TESTED', 'baseline_http_parity'=>'NOT_TESTED'];
        try {
            $http=new M63A1MediaTransport();
            $http->login($username,$password);
            $path='/api/v1/products/'.$id;
            $media=$path.'/image';
            $result['phase']='fixture';
            $initial=$http->json('GET',$path);
            $this->trace($result, 'fixture_get', $initial);
            $before=M63A1MediaPlan::fixture($initial,$marker,$id,true);
            $result['fixture_verified']=true;
            $result['edit_allowed']=true;
            $result['phase']='codec_check';
            $result['gd_all_codecs']=M63A1MediaPlan::codecsReady();
            if (!$result['gd_all_codecs']) throw new RuntimeException('Required PHP GD codecs are not all available.');
            if (!$apply) {
                $result['phase']='complete';
                $result['verdict']='PASS_DEV_PRODUCT_MEDIA_PREFLIGHT';
                return $result;
            }
            $result['phase']='negative';
            $gif=base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==',true);
            if (!is_string($gif)) throw new RuntimeException('Cannot build GIF negative fixture.');
            $messages=[];
            foreach (['en','vi'] as $locale) {
                $missing=$http->multipart($media,null,'image/png',$locale);
                $this->trace($result, 'negative_missing_'.$locale, $missing);
                $messages['missing'][$locale]=M63A1MediaPlan::negative($missing,'PRODUCT_IMAGE_INVALID_CONTENT',$locale);
                $result['negative_cases']++;
                $unsupported=$http->multipart($media,$gif,'image/gif',$locale);
                $this->trace($result, 'negative_gif_'.$locale, $unsupported);
                $messages['gif'][$locale]=M63A1MediaPlan::negative($unsupported,'PRODUCT_IMAGE_INVALID_TYPE',$locale);
                $result['negative_cases']++;
            }
            foreach ($messages as $translations) {
                if ($translations['en'] === $translations['vi']) throw new RuntimeException('Media messages not localized.');
                $result['localized_types']++;
            }
            // Catch unintended writes in the validation path BEFORE uploading valid media.
            M63A1MediaPlan::fixture($http->json('GET',$path),$marker,$id,true);
            $a=M63A1MediaPlan::sample(1);
            $b=M63A1MediaPlan::sample(2);
            $result['phase']='upload_first';
            $first=$http->multipart($media,$a['bytes']);
            $this->trace($result, 'upload_first', $first);
            $result['upload_http']=$first['status'];
            $urlA=M63A1MediaPlan::imageUrl($first,$id);
            $result['image_urls']['first']=$urlA;
            $this->verifyPersistedImage($http,$path,$marker,$id,$urlA);
            $staticA=$this->verifyStatic($http,$urlA);
            $result['static_first']=$staticA['status'];
            $result['phase']='replace';
            // Refuse replacement if an unrelated actor changed the image between checks.
            $this->verifyPersistedImage($http,$path,$marker,$id,$urlA);
            $second=$http->multipart($media,$b['bytes']);
            $this->trace($result, 'replace', $second);
            $result['replace_http']=$second['status'];
            $urlB=M63A1MediaPlan::imageUrl($second,$id);
            if ($urlA===$urlB) throw new RuntimeException('Replacement reused the original image path.');
            $result['image_urls']['second']=$urlB;
            $this->verifyPersistedImage($http,$path,$marker,$id,$urlB);
            $staticB=$this->verifyStatic($http,$urlB);
            $result['static_second']=$staticB['status'];
            if (is_file(M63A1MediaPlan::physicalPath($urlA))) {
                throw new RuntimeException('Replaced public image remains on disk.');
            }
            $old=$http->staticImage($urlA);
            $result['static_old_gone']=$old['status'];
            if ($old['status']!==404) throw new RuntimeException('Replaced static image still served.');
            $result['phase']='delete';
            $this->verifyPersistedImage($http,$path,$marker,$id,$urlB);
            $delete=$http->json('DELETE',$media);
            $this->trace($result, 'delete', $delete);
            $result['delete_http']=$delete['status'];
            M63A1MediaPlan::emptyAfterDelete($delete,$id);
            $after=M63A1MediaPlan::fixture($http->json('GET',$path),$marker,$id,true);
            foreach (['sku','product_code','status','description','product_type','role'] as $field) {
                if (($before[$field]??null)!==($after[$field]??null)) {
                    throw new RuntimeException('Product business data changed during media QA.');
                }
            }
            $result['http_metadata_cleared']=true;
            if (is_file(M63A1MediaPlan::physicalPath($urlB))) {
                throw new RuntimeException('Deleted public image remains on disk.');
            }
            if ($http->staticImage($urlB)['status']!==404) {
                throw new RuntimeException('Deleted image still served by Apache.');
            }
            $result['physical_files_cleaned']=true;
            $result['phase']='complete';
            $result['verdict']='PASS_DEV_PRODUCT_MEDIA_HTTP_FS';
        } catch (Throwable $error) {
            // Report only bounded diagnosis; never expose request bodies, CSRF, cookies or credentials.
            $result['failure_class']=get_class($error);
        }
        return $result;
    }

    /** Keep only stable, bounded HTTP diagnostics (no bodies or credentials). */
    private function trace(array &$report, string $phase, array $response): void
    {
        $code=$response['json']['error']['code']??null;
        $report['last_http']=['phase'=>$phase, 'status'=>(int)($response['status']??0),
            'error_code'=>is_string($code) && preg_match('/\A[A-Z][A-Z0-9_]{0,95}\z/D',$code)===1 ? $code : null];
    }

    private function verifyPersistedImage(M63A1MediaTransport $http, string $path, string $marker, string $id, string $url): void
    {
        $dto=M63A1MediaPlan::fixture($http->json('GET',$path),$marker,$id,false);
        if (($dto['image_available']??null)!==true || ($dto['image_url']??null)!==$url) {
            throw new RuntimeException('Product read-after-write image metadata differs from mutation response.');
        }
    }

    /** @return array{status:int} */
    private function verifyStatic(M63A1MediaTransport $http, string $url): array
    {
        $filename=M63A1MediaPlan::filename($url);
        $physical=M63A1MediaPlan::physicalPath($url);
        if (!is_file($physical) || !is_readable($physical)) {
            throw new RuntimeException('Public uploaded media file is missing.');
        }
        $content=@file_get_contents($physical);
        if (!is_string($content) || $content==='' || strlen($content)>3*1024*1024) {
            throw new RuntimeException('Uploaded physical media is invalid.');
        }
        $static=$http->staticImage($url);
        $expectedMime=str_ends_with($filename,'.png')?'image/png':(str_ends_with($filename,'.webp')?'image/webp':'image/jpeg');
        if ($static['status']!==200
            || strtolower(trim(explode(';',$static['content_type'],2)[0]))!==$expectedMime
            || hash('sha256',$static['bytes'])!==hash('sha256',$content)
            || (new finfo(FILEINFO_MIME_TYPE))->buffer($content)!==$expectedMime) {
            throw new RuntimeException('Apache static image does not match file on disk.');
        }
        return ['status'=>200];
    }
}
