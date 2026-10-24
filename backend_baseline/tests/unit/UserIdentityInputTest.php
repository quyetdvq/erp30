<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Services\UserDisplayName;
use App\Services\UserGroupSelection;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class UserIdentityInputTest extends CIUnitTestCase
{
    public function testFullNamePreservesUnicodeAndTrimsWhitespace(): void
    {
        $this->assertSame('Nguyễn Văn A', UserDisplayName::normalize("  Nguyễn Văn A  "));
        $this->assertSame(str_repeat('Á', 255), UserDisplayName::normalize(str_repeat('Á', 255)));
    }

    public function testFullNameRejectsBlankWrongTypeAndOverlength(): void
    {
        foreach ([null, '', '  ', "\t\n", 7, str_repeat('Á', 256)] as $invalid) {
            try {
                UserDisplayName::normalize($invalid);
                $this->fail('Expected a validation error.');
            } catch (DomainException $exception) {
                $this->assertSame('VALIDATION_ERROR', $exception->getErrorCode());
                $this->assertSame('name', $exception->getContext()['field']);
            }
        }
    }

    public function testGroupIdsAcceptOmittedAndEmptyOrMultipleUniqueIds(): void
    {
        $this->assertSame([], UserGroupSelection::normalize([]));
        $this->assertSame(['2', '4'], UserGroupSelection::normalize(['2', '4']));
    }

    public function testGroupIdsRejectScalarNullDuplicateNoncanonicalAndOverflow(): void
    {
        foreach ([null, '2', 2, ['2', '2'], ['02'], ['0'], [-1], [2], ['+2'], ['2', '0'], ['999999999999999999999999999999']] as $invalid) {
            try {
                UserGroupSelection::normalize($invalid);
                $this->fail('Expected a group_ids validation error.');
            } catch (DomainException $exception) {
                $this->assertSame('VALIDATION_ERROR', $exception->getErrorCode());
                $this->assertSame('group_ids', $exception->getContext()['field']);
            }
        }
    }
}
