<?php

namespace App\Imports;

class CsvLeadHeader
{
    public static function getHeader(): array
    {
        return [
            'external_id',
            'created_at',
            'first_name',
            'last_name',
            'phone',
            'email',
            'city',
            'source',
            'utm_campaign',
            'product',
            'budget_uah',
            'status',
            'manager',
            'comment',
            'next_contact_at',
        ];
    }

    public static function compare(array $header): bool
    {
        return self::getHeader() === $header;
    }
}
