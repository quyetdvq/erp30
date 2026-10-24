<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Scope\Infrastructure\Settings;

use App\Modules\Numbering\Scope\Application\Ports\BusinessTimezoneProviderInterface;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Settings\Settings;
use DateTimeZone;
use InvalidArgumentException;

final class CodeIgniterBusinessTimezoneResolver implements BusinessTimezoneProviderInterface
{
    public const DEFAULT_GROUP_TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function __construct(private readonly Settings $settings)
    {
    }

    public function resolve(?int $companyId, ?int $groupId = null): ?DateTimeZone
    {
        $contexts = [];
        if ($companyId !== null) {
            $contexts[] = "company:$companyId";
        }
        if ($groupId !== null) {
            $contexts[] = "group:$groupId";
        }
        foreach ($contexts as $context) {
            $timezone = $this->readSetting($context);
            if (is_string($timezone) && $timezone !== '') {
                return $this->timezone($timezone);
            }
        }

        $timezone = $this->readSetting();
        if (is_string($timezone) && $timezone !== '') {
            return $this->timezone($timezone);
        }

        if ($groupId !== null) {
            return $this->timezone(self::DEFAULT_GROUP_TIMEZONE);
        }

        return null;
    }

    private function timezone(string $name): DateTimeZone
    {
        try {
            return new DateTimeZone($name);
        } catch (\Exception $exception) {
            throw new InvalidArgumentException('Invalid business timezone: ' . $name, 0, $exception);
        }
    }

    private function readSetting(?string $context = null): mixed
    {
        try {
            return $this->settings->get('App.businessTimezone', $context);
        } catch (DatabaseException) {
            return null;
        }
    }
}
