<?php

declare(strict_types=1);
namespace CodeIgniter\HTTP\Exceptions;
if (!class_exists(BadRequestException::class, false)) {
    class BadRequestException extends \RuntimeException {}
}
