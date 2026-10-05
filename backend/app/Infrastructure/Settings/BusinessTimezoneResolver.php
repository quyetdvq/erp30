<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Settings\Settings;
use DateTimeZone;
use InvalidArgumentException;

final class BusinessTimezoneResolver
{
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
