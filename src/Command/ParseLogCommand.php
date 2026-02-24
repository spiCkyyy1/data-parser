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
        $root = dirname(__DIR__, 2);

        try {
            $logParser = new LogParserService($root . self::APP_CODES_FILE_NAME);

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

            $files = new Finder()->files()->in($root . '/data')->name('*.log');

            $total = 0;
            if($files->count() > 0) {
                foreach ($files as $file) {
                    $reader = Reader::from($file->getRealPath());
                    $reader->setHeaderOffset(0);

                    $total += $this->processEntries($reader, $writer, $logParser, $total + 1);
                }
            }

            $io->success("Processed {$total} records.");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }


    /**
     * @throws CannotInsertRecord
     * @throws Exception
     */
    private function processEntries(Reader $reader, Writer $writer, LogParserInterface $parser, int $startId): int
    {
        $count = 0;
        if($reader->count() > 0) {
            foreach ($reader as $row) {
                $writer->insertOne($parser->processRow($row, $startId + $count));
                $count++;
            }
        }
        return $count;
    }
}