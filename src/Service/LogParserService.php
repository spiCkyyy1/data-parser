<?php

declare(strict_types=1);

namespace App\Service;

class LogParserService implements LogParserInterface
{
    private array $appRegistry = [];

    // Output Constants defined by the PDF Brief
    private const string SUB_ACTIVE  = 'active_subscriber';
    private const string SUB_EXPIRED = 'expired_subscriber';
    private const string SUB_NEVER   = 'never_subscribed';
    private const string SUB_UNKNOWN = 'subscription_unknown';

    private const string FREE_YES     = 'has_downloaded_free_product';
    private const string FREE_NO      = 'not_downloaded_free_product';
    private const string FREE_UNKNOWN = 'downloaded_free_product_unknown';

    private const string IAP_YES      = 'has_downloaded_iap_product';
    private const string IAP_UNKNOWN  = 'downloaded_iap_product_unknown';

    /**
     * TRANSFORMATION MAP: Consolidation of third-party tags into normalized states.
     */
    private const array TAG_MAP = [
        'active_subscriber'                             => self::SUB_ACTIVE,
        'expired_subscriber'                            => self::SUB_EXPIRED,
        'never_subscribed'                              => self::SUB_NEVER,

        // Transform various free-product tags into a single status
        'downloaded_free_single_issue_while_no_sub'     => self::FREE_YES,
        'downloaded_free_single_issue_while_active_sub' => self::FREE_YES,
        'has_downloaded_free_product'                   => self::FREE_YES,
        'not_downloaded_free_product'                   => self::FREE_NO, // Shared Status

        // Transform various IAP tags into a single status
        'purchased_single_issue_while_no_sub'           => self::IAP_YES,
        'purchased_single_issue_while_active_sub'       => self::IAP_YES,
        'purchased_single_issue_while_expired_sub'      => self::IAP_YES,
        'has_downloaded_iap_product'                    => self::IAP_YES,
    ];

    public function __construct(string $iniPath)
    {
        if (file_exists($iniPath)) {
            $data = @parse_ini_file($iniPath);
            $this->appRegistry = is_array($data) ? $data : [];
        }
    }

    public function processRow(array $rawData, int $recordId): array
    {
        $rawTags = isset($rawData['tags']) ? explode('|', (string)$rawData['tags']) : [];
        $tags = array_map(fn($t) => trim(strtolower($t)), $rawTags);

        return [
            'id'           => $recordId,
            'appCode'      => $this->appRegistry[$rawData['app']] ?? $rawData['app'], //
            'deviceId'     => $rawData['deviceToken'] ?? '',
            'contactable'  => ($rawData['deviceTokenStatus'] ?? '0') === '1' ? 1 : 0, //

            'subscription_status' => $this->resolveGroup(
                $tags, [self::SUB_ACTIVE, self::SUB_EXPIRED, self::SUB_NEVER], self::SUB_UNKNOWN
            ),

            // Transformation logic: 'not_downloaded_free_product' is valid for FREE status
            'has_downloaded_free_product_status' => $this->resolveGroup(
                $tags, [self::FREE_YES, self::FREE_NO], self::FREE_UNKNOWN
            ),

            // Transformation logic: 'not_downloaded_free_product' is ALSO valid for IAP status
            'has_downloaded_iap_product_status'  => $this->resolveGroup(
                $tags, [self::IAP_YES, self::FREE_NO], self::IAP_UNKNOWN
            ),
        ];
    }

    private function resolveGroup(array $tags, array $validOutputs, string $fallback): string
    {
        foreach ($tags as $tag) {
            if (isset(self::TAG_MAP[$tag])) {
                $mappedValue = self::TAG_MAP[$tag];
                // If the mapped value is valid for this specific column, return it
                if (in_array($mappedValue, $validOutputs, true)) {
                    return $mappedValue;
                }
            }
        }
        return $fallback;
    }
}