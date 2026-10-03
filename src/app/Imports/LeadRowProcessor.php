<?php

namespace App\Imports;

use Illuminate\Support\Facades\Validator;

class LeadRowProcessor
{
    private array $header;
    private int $headerCount;

    public function __construct(array $header)
    {
        $this->header = $header;
        $this->headerCount = count($header);
    }

    public function process(array $row): array
    {
        if ($this->headerCount !== count($row)) {
            return [
                'data' => null,
                'errors' => ['columns' => ['The number of columns does not match.']],
            ];
        }

        $row = array_combine($this->header, $row);

        array_walk($row, function (&$value, $key) {
            $value = trim($value);

            if ($value === '') {
                $value = null;
            }

            if ('budget_uah' === $key && $value !== null) {
                $value = preg_replace('/\s+/u', '', $value);
            }
        });

        $validator = Validator::make($row, [
            'external_id' => 'required|string|max:64',
            'created_at'  => 'required|string|date_format:Y-m-d H:i:s',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'city' => 'required|string|max:255',
            'source' => 'required|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'product' => 'required|string|max:255',
            'budget_uah' => 'nullable|integer|min:0|max:4294967295',
            'status' => 'required|string|max:255',
            'manager' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'next_contact_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return [
                'data' => null,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $validated = $validator->validated();

        $validated['lead_created_at'] = $validated['created_at'];
        unset($validated['created_at']);
        $validated['budget_uah'] = is_null($validated['budget_uah']) ? null : (int) $validated['budget_uah'];

        return [
            'data' => $validated,
            'errors' => [],
        ];
    }
}
