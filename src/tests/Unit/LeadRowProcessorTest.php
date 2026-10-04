<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Imports\LeadRowProcessor;
use App\Imports\CsvLeadHeader;

class LeadRowProcessorTest extends TestCase
{
    protected array $header;
    protected array $row;
    protected LeadRowProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->header = CsvLeadHeader::getHeader();

        $this->row = [
            'LD-000001',
            '2025-07-10 05:06:54',
            'First',
            'Last',
            '+381234567890',
            'test@test.test',
            'city',
            'Facebook Ads',
            'catalog_2026',
            'Contextual advertising',
            '23 700',
            'in_progress',
            'Manager',
            'comment',
            '2025-07-23 05:06:54',
        ];

        $this->processor = new LeadRowProcessor($this->header);
    }

    public function test_valid_row_is_mapped_to_lead_columns()
    {
        $result = $this->processor->process($this->row);

        $this->assertIsArray($result['data']);
        $this->assertNotEmpty($result['data']);
        $this->assertSame([], $result['errors']);

        $this->assertSame($this->row[0], $result['data']['external_id']);
        $this->assertSame($this->row[1], $result['data']['lead_created_at']);
        $this->assertSame($this->row[2], $result['data']['first_name']);
        $this->assertSame($this->row[3], $result['data']['last_name']);
        $this->assertSame($this->row[4], $result['data']['phone']);
        $this->assertSame($this->row[5], $result['data']['email']);
        $this->assertSame($this->row[6], $result['data']['city']);
        $this->assertSame($this->row[7], $result['data']['source']);
        $this->assertSame($this->row[8], $result['data']['utm_campaign']);
        $this->assertSame($this->row[9], $result['data']['product']);
        $this->assertSame(23700, $result['data']['budget_uah']);
        $this->assertSame($this->row[11], $result['data']['status']);
        $this->assertSame($this->row[12], $result['data']['manager']);
        $this->assertSame($this->row[13], $result['data']['comment']);
        $this->assertSame($this->row[14], $result['data']['next_contact_at']);

        $this->assertEqualsCanonicalizing(
            [
                'external_id',
                'lead_created_at',
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
                'next_contact_at'
            ],
            array_keys($result['data'])
        );
    }

    public function test_budget_with_spaces_is_parsed_to_integer()
    {
        $data = $this->row;

        $data[10] = "1\xC2\xA0234";
        $result = $this->processor->process($data);

        $this->assertSame(1234, $result['data']['budget_uah']);
    }

    public function test_empty_budget_becomes_null()
    {
        $data = $this->row;

        $data[10] = '';
        $result = $this->processor->process($data);

        $this->assertNull($result['data']['budget_uah']);
    }

    public function test_non_numeric_budget_fails_validation()
    {
        $data = $this->row;

        $data[10] = 'abc';
        $result = $this->processor->process($data);

        $this->assertNull($result['data']);
        $this->assertArrayHasKey('budget_uah', $result['errors']);
    }

    public function test_empty_optional_fields_become_null()
    {
        $data = $this->row;

        $data[2] = '';
        $data[5] = '';
        $result = $this->processor->process($data);

        $this->assertNull($result['data']['first_name']);
        $this->assertNull($result['data']['email']);
    }

    public function test_invalid_email_fails_validation()
    {
        $data = $this->row;

        $data[5] = '@test';
        $result = $this->processor->process($data);

        $this->assertNull($result['data']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function test_overflowing_date_fails_validation()
    {
        $data = $this->row;

        $data[1] = '2025-13-45 10:00:00';
        $result = $this->processor->process($data);

        $this->assertNull($result['data']);
        $this->assertArrayHasKey('created_at', $result['errors']);
    }

    public function test_missing_external_id_fails_validation()
    {
        $data = $this->row;

        $data[0] = '';
        $result = $this->processor->process($data);

        $this->assertNull($result['data']);
        $this->assertArrayHasKey('external_id', $result['errors']);
    }

    public function test_row_with_extra_column_is_rejected()
    {
        $data = $this->row;
        $data['test'] = 'test';

        $result = $this->processor->process($data);

        $this->assertNull($result['data']);
        $this->assertArrayHasKey('columns', $result['errors']);
    }

    public function test_row_with_missing_column_is_rejected()
    {
        $data = $this->row;
        unset($data[0]);

        $result = $this->processor->process($data);

        $this->assertNull($result['data']);
        $this->assertArrayHasKey('columns', $result['errors']);
    }
}
