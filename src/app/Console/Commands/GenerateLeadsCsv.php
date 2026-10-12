<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Imports\CsvLeadHeader;
use Faker\Factory as Faker;
use Faker\Generator;
use App\Jobs\PrepareImportJob;

class GenerateLeadsCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:generate-csv
        {rows=100000}
        {--path= : Output CSV path}
        {--seed= : Seed for reproducible generation}
        {--invalid=1 : Percentage of invalid rows}
        {--duplicates=0 : Percentage of duplicate external IDs (0.2 = 0.2%)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a test CSV file with the specified number of rows.';

    /**
     * Faker
     */
    private Generator $faker;

    /**
     * Faker data.
     */
    private array $pools;
    private const CLOSED_STATUSES = ['won', 'lost', 'spam'];
    private const DATE_FROM = '2024-01-01 00:00:00';
    private const DATE_TO = '2026-06-30 23:59:59';

    private int $tsFrom;
    private int $tsTo;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->faker = Faker::create('uk_UA');

        $memoryStart = memory_get_usage();
        $timeStart = microtime(true);

        // rows start
        $rows = (int) $this->argument('rows');

        if ($rows < 1) {
            $this->error('Rows must be greater than 0.');

            return self::FAILURE;
        }
        // rows end

        // path start
        $path = $this->getOutputPath($rows);

        if ($path === null) {
            return self::FAILURE;
        }
        // path end

        // seed start
        $seed = $this->option('seed');

        if ($seed !== null) {
            $this->faker->seed((int) $seed);
        }
        // seed end

        // invalid start
        $invalidOption = $this->option('invalid');

        if (!is_numeric($invalidOption)) {
            $this->error('Invalid percentage must be a number.');

            return self::FAILURE;
        }

        $invalidPercent = (float) $invalidOption;

        if ($invalidPercent < 0 || $invalidPercent > 100) {
            $this->error('Invalid percentage must be between 0 and 100.');

            return self::FAILURE;
        }
        // invalid end

        // duplicates start
        $duplicateOption = $this->option('duplicates');

        if (!is_numeric($duplicateOption)) {
            $this->error('Duplicates percentage must be a number.');

            return self::FAILURE;
        }

        $duplicatePercent = (float) $duplicateOption;

        if ($duplicatePercent < 0 || $duplicatePercent > 50) {
            $this->error('Duplicates percentage must be between 0 and 50.');

            return self::FAILURE;
        }
        // duplicates end

        $this->pools = $this->preparePools();
        $this->tsFrom = strtotime(self::DATE_FROM);
        $this->tsTo = strtotime(self::DATE_TO);

        $stats = $this->writeCsv($path, $rows, $invalidPercent, $duplicatePercent);

        if ($stats === null) {
            return self::FAILURE;
        }

        $this->table(['Metric', 'Value'], [
            ['Rows', $stats['rows']],
            ['Invalid', $stats['invalid']],
            ['Duplicates (same chunk)', $stats['duplicates_near']],
            ['Duplicates (other chunk)', $stats['duplicates_far']],
            ['Time', sprintf('%.1f s', microtime(true) - $timeStart)],
            ['Peak memory', sprintf('%.1f MB (+%.1f MB)', memory_get_peak_usage() / 1024 / 1024, (memory_get_peak_usage() - $memoryStart) / 1024 / 1024)],
            ['File', $path],
        ]);

        return self::SUCCESS;
    }

    private function preparePools(): array
    {
        return [
            'first_name' => $this->makePool(fn () => $this->faker->firstName()),
            'last_name' => $this->makePool(fn () => $this->faker->lastName()),
            // latin local part, e.g. "oleksandr.polishchuk"; the row index is appended later
            'email_name' => $this->makePool(fn () => explode('@', $this->faker->safeEmail())[0]),
            'email_domain' => ['gmail.com', 'ukr.net', 'i.ua', 'outlook.com', 'meta.ua'],
            'city' => $this->makePool(fn () => $this->faker->city(), 50),
            'manager' => $this->makePool(
                fn () => $this->faker->lastName() . ' ' . mb_substr($this->faker->firstName(), 0, 1) . '.',
                10
            ),
            'source' => [
                'Site', 'Instagram', 'Facebook Ads', 'Google Ads', 'TikTok',
                'YouTube', 'Referral', 'Email Marketing', 'LinkedIn',
            ],
            'utm_campaign' => [
                'new_year', 'webinar', 'organic', 'black_friday', 'summer_sale',
                'product_launch', 'lead_generation', 'retargeting',
            ],
            'product' => [
                'преміум-підписка', 'стартовий тариф', 'консультація',
                'онлайн-курс', 'безкоштовний пробний період',
            ],
            'status' => [
                'new', 'contacted', 'qualified', 'proposal_sent',
                'in_progress', 'won', 'lost', 'spam',
            ],
            'comment' => [
                'Клієнт зацікавлений, очікує на комерційну пропозицію',
                'Потрібно уточнити "бюджет" перед оформленням замовлення',
                'Домовилися про повторний дзвінок наступного тижня',
                'Клієнт хоче отримати додаткову інформацію про послугу',
                'Потрібно надіслати презентацію та приклади робіт',
                'Передзвонити після обіду, зараз на нараді',
                'Порівнює ціни, просить знижку',
                'Сказав "подумаю", повернутися через місяць',
            ],
        ];
    }

    private function makePool(callable $generator, int $size = 100): array
    {
        $pool = [];

        for ($k = 0; $k < $size; $k++) {
            $pool[] = $generator();
        }

        return $pool;
    }

    private function pick(string $key): string
    {
        $pool = $this->pools[$key];

        return $pool[$this->faker->numberBetween(0, count($pool) - 1)];
    }

    private function pickOrEmpty(string $key, int $emptyPercent): string
    {
        return $this->faker->numberBetween(1, 100) <= $emptyPercent ? '' : $this->pick($key);
    }

    /**
     * Decides whether row $i is a duplicate and picks the original row.
     *
     * @return array{0: int, 1: string}|null [original row index, 'near'|'far']
     */
    private function pickDuplicate(int $i, float $duplicatePercent): ?array
    {
        if ($this->faker->randomFloat(4, 0, 100) >= $duplicatePercent) {
            return null;
        }

        // Data row $i lands in chunk intdiv($i - 1, PrepareImportJob::CHUNK_SIZE):
        // rows 1–1000 → chunk 0 (startLine 2), rows 1001–2000 → chunk 1, ...
        $chunkStart = intdiv($i - 1, PrepareImportJob::CHUNK_SIZE) * PrepareImportJob::CHUNK_SIZE + 1;

        $canNear = $i > $chunkStart;   // there are earlier rows in this chunk
        $canFar = $chunkStart > 1;     // there are earlier chunks

        if (!$canNear && !$canFar) {
            return null;               // the very first row of the file
        }

        $near = $canNear && (!$canFar || $this->faker->boolean());

        return $near
            ? [$this->faker->numberBetween($chunkStart, $i - 1), 'near']
            : [$this->faker->numberBetween(1, $chunkStart - 1), 'far'];
    }

    private function writeCsv(string $path, int $rows, float $invalidPercent = 0.0, float $duplicatePercent = 0.0): ?array
    {
        try {
            $handle = fopen($path, 'w');
        } catch (\ErrorException $e) {
            $this->error("Unable to open file: {$path}");

            return null;
        }

        if ($handle === false) {
            $this->error("Unable to open file: {$path}");

            return null;
        }

        $bar = $this->output->createProgressBar($rows);

        try {
            if (fputcsv($handle, CsvLeadHeader::getHeader()) === false) {
                $this->error('Unable to write CSV header.');

                return null;
            }

            $bar->setFormat('debug');
            $bar->setRedrawFrequency(1000);
            $bar->start();

            $stats = ['rows' => 0, 'invalid' => 0, 'duplicates_near' => 0, 'duplicates_far' => 0];

            for ($i = 1; $i <= $rows; $i++) {
                $isInvalid = $this->faker->randomFloat(4, 0, 100) < $invalidPercent;
                $duplicate = $this->pickDuplicate($i, $duplicatePercent);
                $externalId = $duplicate !== null ? $this->getExternalId($duplicate[0]) : null;

                if (fputcsv($handle, $this->generateRow($i, $isInvalid, $externalId)) === false) {
                    $bar->clear();

                    $this->error("Unable to write CSV row: {$i}");

                    return null;
                }

                $stats['rows']++;
                if ($isInvalid) {
                    $stats['invalid']++;
                }

                if ($duplicate !== null) {
                    $stats['duplicates_' . $duplicate[1]]++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (\Throwable $e) {
            $bar->clear();

            $this->error("Unable to write CSV: {$e->getMessage()}");

            return null;
        } finally {
            fclose($handle);
        }

        return $stats;
    }

    private function getOutputPath(int $rows): ?string
    {
        $relativePath = $this->option('path');
        if ($relativePath === null) {
            $relativePath = "leads-{$rows}.csv";
        }

        if (
            $relativePath === ''
            || $relativePath[0] === '/'
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $relativePath)
        ) {
            $this->error('Path must be relative to the generated directory.');

            return null;
        }

        $baseDirectory = Storage::path('generated');
        if (!is_dir($baseDirectory)) {
            if (!mkdir($baseDirectory, 0755, true) && !is_dir($baseDirectory)) {
                $this->error('Unable to create generated directory.');

                return null;
            }
        }

        $baseDirectory = realpath($baseDirectory);
        $path = Storage::path("generated/{$relativePath}");
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                $this->error("Unable to create directory: {$directory}");

                return null;
            }
        }

        $realDirectory = realpath($directory);
        if (
            $realDirectory === false
            || (
                $realDirectory !== $baseDirectory
                && strpos($realDirectory, $baseDirectory . DIRECTORY_SEPARATOR) !== 0
            )
        ) {
            $this->error('Path must be inside the generated directory.');

            return null;
        }

        return $realDirectory . DIRECTORY_SEPARATOR . basename($path);
    }

    private function generateRow(int $index, bool $invalid, ?string $externalId): array
    {
        $createdAt = $this->faker->numberBetween($this->tsFrom, $this->tsTo);
        $status = $this->pick('status');

        // closed leads have no next contact; others: 1 hour .. 14 days after creation
        $nextContactAt = in_array($status, self::CLOSED_STATUSES, true)
            ? ''
            : date('Y-m-d H:i:s', $createdAt + $this->faker->numberBetween(3600, 14 * 86400));

        $row = [
            $externalId ?? $this->getExternalId($index),
            date('Y-m-d H:i:s', $createdAt),
            $this->pickOrEmpty('first_name', 1),
            $this->pick('last_name'),
            '380' . $this->faker->numerify('#########'),
            $this->getEmail($index),
            $this->pick('city'),
            $this->pick('source'),
            $this->pickOrEmpty('utm_campaign', 9),
            $this->pick('product'),
            $this->getBudget(),
            $status,
            $this->pickOrEmpty('manager', 16),
            $this->pickOrEmpty('comment', 28),
            $nextContactAt,
        ];

        return $invalid ? $this->makeRowInvalid($row) : $row;
    }

    private function getEmail(int $index): string
    {
        if ($this->faker->numberBetween(1, 1000) <= 2) {   // ~0.2% empty
            return '';
        }

        // the row index keeps addresses unique without remembering issued ones
        return $this->pick('email_name') . '.' . $index . '@' . $this->pick('email_domain');
    }

    private function getBudget(): string
    {
        if ($this->faker->numberBetween(1, 100) <= 20) {   // ~20% empty
            return '';
        }

        // 500 .. 150 000: both "800" (no separator) and "23 700" (with separator)
        return number_format($this->faker->numberBetween(500, 150000), 0, '', ' ');
    }

    private function makeRowInvalid(array $row): array
    {
        switch ($this->faker->numberBetween(1, 8)) {
            case 1:
                $row[0] = '';                       // empty external_id (upsert key)
                break;
            case 2:
                $row[1] = '31-12-2025';             // wrong date format
                break;
            case 3:
                $row[1] = '2025-13-45 10:00:00';    // right format, impossible date
                break;
            case 4:
                $row[3] = '';                       // empty required last_name
                break;
            case 5:
                $row[5] = 'invalid-email';
                break;
            case 6:
                $row[10] = '-100';                  // negative budget
                break;
            case 7:
                $row[10] = 'abc';                   // non-numeric budget
                break;
            case 8:
                $row[] = 'unexpected';              // extra column
                break;
        }

        return $row;
    }

    private function getExternalId(int $index): string
    {
        return 'LD-' . str_pad(
            (string) $index,
            6,
            '0',
            STR_PAD_LEFT
        );
    }
}
