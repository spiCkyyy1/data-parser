<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

class LogParserService implements LogParserInterface
{
    private array $appRegistry = [];

    // Using descriptive constant names
    private const array SUBSCRIPTION_TAGS = ['active_subscriber', 'expired_subscriber', 'never_subscribed', 'subscription_unknown'];
    private const array FREE_PRODUCT_TAGS = ['has_downloaded_free_product', 'not_downloaded_free_product', 'downloaded_free_product_unknown'];
    private const array IAP_PRODUCT_TAGS  = ['has_downloaded_iap_product', 'not_downloaded_free_product', 'downloaded_iap_product_unknown'];

    public function __construct(string $iniPath)
    {
        if (file_exists($iniPath)) {
            $data = parse_ini_file($iniPath);
            $this->appRegistry = is_array($data) ? $data : [];
        }
    }

    public function processRow(array $rawData, int $recordId): array
    {
        // Guard clause for data integrity
        if (!isset($rawData['app'], $rawData['deviceToken'])) {
            throw new RuntimeException("Record {$recordId} is missing critical app or token data.");
        }

        $rawTags = explode('|', $rawData['tags'] ?? '');

        return [
            'id'           => $recordId,
            'appCode'      => $this->appRegistry[$rawData['app']] ?? $rawData['app'],
            'deviceId'     => $rawData['deviceToken'],
            'contactable'  => (int)(($rawData['deviceTokenStatus'] ?? '0') === '1'),
            'subscription_status' => $this->matchTag($rawTags, self::SUBSCRIPTION_TAGS, 'subscription_unknown'),
            'has_downloaded_free_product_status' => $this->matchTag($rawTags, self::FREE_PRODUCT_TAGS, 'downloaded_free_product_unknown'),
            'has_downloaded_iap_product_status'  => $this->matchTag($rawTags, self::IAP_PRODUCT_TAGS, 'downloaded_iap_product_unknown'),
        ];
    }

    private function matchTag(array $tags, array $validSet, string $fallback): string
    {
        foreach ($tags as $tag) {
            $clean = trim(strtolower($tag));
            if (in_array($clean, $validSet, true)) {
                return $clean;
            }
        }
        return $fallback;
    }
}