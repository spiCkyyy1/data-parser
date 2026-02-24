<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\LogParserInterface;
use App\Service\LogParserService;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use League\Csv\Writer;
use League\Csv\Reader;

#[AsCommand(name: 'app:parse')]
class ParseLogCommand extends Command
{
    private const string APP_CODES_FILE_NAME = '/appCodes.ini';
    private const string OUTPUT_FILE_NAME = '/output.csv';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        // Define root path relative to the src directory to ensure portability across environments
        $root = dirname(__DIR__, 2);

        try {
            // Instantiate service with external configuration mapping
            $logParser = new LogParserService($root . self::APP_CODES_FILE_NAME);

            // Initialize stream-based writer to keep memory footprint constant
            $writer = Writer::from($root . self::OUTPUT_FILE_NAME, 'w+');
            $writer->insertOne([
                'id',
                'appCode',
                'deviceId',
                'contactable',
                'subscription_status',
                'has_downloaded_free_product_status',
                'has_downloaded_iap_product_status'
            ]);

            // Finder component used for memory-efficient file iteration
            $files = new Finder()->files()->in($root . '/data')->name('*.log');

            $total = 0;
            if($files->count() > 0) {
                foreach ($files as $file) {
                    $reader = Reader::from($file->getRealPath());
                    $reader->setHeaderOffset(0);

                    // Delegate transformation to maintain separation of concerns
                    $total += $this->processEntries($reader, $writer, $logParser, $total + 1);
                }
            }

            $io->success("Processed {$total} records.");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            // Global catch to prevent CLI crashes
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }


    /**
     * Iterates through a single file and persists transformed rows.
     * Note: Type-hinting the LogParserInterface allows for future-proof polymorphism.
     * * @throws CannotInsertRecord
     * @throws Exception
     */
    private function processEntries(Reader $reader, Writer $writer, LogParserInterface $parser, int $startId): int
    {
        $count = 0;
        if($reader->count() > 0) {
            // Process records individually to avoid loading entire files into RAM
            foreach ($reader as $row) {
                $writer->insertOne($parser->processRow($row, $startId + $count));
                $count++;
            }
        }
        return $count;
    }
}