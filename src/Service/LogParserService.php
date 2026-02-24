<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Normalizes disparate third-party log tags into consolidated business groups.
 */
class LogParserService implements LogParserInterface
{
    private array $appRegistry = [];

    // Valid output strings as defined in the Technical Brief
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
     * TRANSFORMATION MAP: Normalizes "dirty" third-party log tags to valid output statuses.
     * This handles the requirement to "consolidate" a large number of tags.
     */
    private const array TAG_MAP = [
        // Subscription Group Normalization
        'active_subscriber'                             => self::SUB_ACTIVE,
        'expired_subscriber'                            => self::SUB_EXPIRED,
        'never_subscribed'                              => self::SUB_NEVER,

        // Free Product Group Normalization (Consolidating multiple raw tags)
        'downloaded_free_single_issue_while_no_sub'     => self::FREE_YES,
        'downloaded_free_single_issue_while_active_sub' => self::FREE_YES,
        'has_downloaded_free_product'                   => self::FREE_YES,
        'not_downloaded_free_product'                   => self::FREE_NO, //Shared Status

        // IAP Product Group Normalization (Consolidating multiple raw tags)
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

    /**
     * Processes a single log row and transforms it into the normalized CSV format.
     */
    public function processRow(array $rawData, int $recordId): array
    {
        $rawTags = isset($rawData['tags']) ? explode('|', (string)$rawData['tags']) : [];
        $tags = array_map(fn($t) => trim(strtolower($t)), $rawTags);

        return [
            'id'           => $recordId,
            'appCode'      => $this->appRegistry[$rawData['app']] ?? $rawData['app'],
            'deviceId'     => $rawData['deviceToken'] ?? '',
            'contactable'  => ($rawData['deviceTokenStatus'] ?? '0') === '1' ? 1 : 0,

            // Requirement: Group tags into specific columns with unique fallbacks
            'subscription_status' => $this->resolveGroup(
                $tags,
                [self::SUB_ACTIVE, self::SUB_EXPIRED, self::SUB_NEVER],
                self::SUB_UNKNOWN
            ),
            'has_downloaded_free_product_status' => $this->resolveGroup(
                $tags,
                [self::FREE_YES, self::FREE_NO],
                self::FREE_UNKNOWN
            ),
            'has_downloaded_iap_product_status'  => $this->resolveGroup(
                $tags,
                [self::IAP_YES, self::FREE_NO],
                self::IAP_UNKNOWN
            ),
        ];
    }

    /**
     * Resolves a set of raw tags to a single valid output status for a specific group.
     * Shared tags like 'not_downloaded_free_product' are handled via the $validOutputs filter.
     */
    private function resolveGroup(array $tags, array $validOutputs, string $fallback): string
    {
        foreach ($tags as $tag) {
            if (isset(self::TAG_MAP[$tag])) {
                $mappedValue = self::TAG_MAP[$tag];

                // Ensure the transformed tag belongs to the requirement group being resolved
                if (in_array($mappedValue, $validOutputs, true)) {
                    return $mappedValue;
                }
            }
        }

        return $fallback;
    }
}