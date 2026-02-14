<?php

namespace App\Jobs;

use App\Models\DataEntry;
use App\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportDataEntriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $websiteId;
    protected string $filePath;
    protected string $identifierColumn;

    /**
     * Create a new job instance.
     */
    public function __construct(int $websiteId, string $filePath, string $identifierColumn = 'nik')
    {
        $this->websiteId = $websiteId;
        $this->filePath = $filePath;
        $this->identifierColumn = $identifierColumn;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $website = Website::findOrFail($this->websiteId);
        $batchId = Str::uuid()->toString();
        
        $file = fopen($this->filePath, 'r');
        
        if (!$file) {
            throw new \Exception("Cannot open file: {$this->filePath}");
        }

        // Read header
        $header = fgetcsv($file);
        
        if (!$header) {
            throw new \Exception("Cannot read CSV header");
        }

        // Normalize header keys
        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        
        $importedCount = 0;
        $skippedCount = 0;

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) !== count($header)) {
                $skippedCount++;
                continue;
            }

            $data = array_combine($header, $row);
            
            // Get identifier value
            $identifier = $data[$this->identifierColumn] ?? null;
            
            // Skip if no identifier
            if (empty($identifier)) {
                $skippedCount++;
                continue;
            }

            // Check for duplicate
            $exists = DataEntry::where('website_id', $this->websiteId)
                ->where('identifier', $identifier)
                ->whereIn('status', ['pending', 'queued', 'processing'])
                ->exists();

            if ($exists) {
                $skippedCount++;
                continue;
            }

            // Create entry
            DataEntry::create([
                'website_id' => $this->websiteId,
                'identifier' => $identifier,
                'data' => $data,
                'status' => 'pending',
                'max_attempts' => $website->retry_attempts ?? 3,
                'batch_id' => $batchId,
            ]);

            $importedCount++;
        }

        fclose($file);

        // Log result
        \Log::info("Import completed", [
            'website_id' => $this->websiteId,
            'batch_id' => $batchId,
            'imported' => $importedCount,
            'skipped' => $skippedCount,
        ]);
        
        // Clean up file after successful import
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
        
        if ($importedCount === 0) {
            throw new \Exception("No entries were imported. Skipped: {$skippedCount}. Check if identifier column '{$this->identifierColumn}' exists in CSV.");
        }
    }
}
