<?php
/**
 * Calendar Converter
 * Handles conversion between custom saga calendar systems and normalized timestamps
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

namespace SagaManager\Helpers;

class CalendarConverter {

    /**
     * Convert saga date to normalized Unix timestamp
     *
     * @param string $canon_date Original saga date format
     * @param string $calendar_type Calendar type (absolute, epoch_relative, age_based)
     * @param array $calendar_config Calendar configuration
     * @return int Normalized Unix timestamp
     * @throws \InvalidArgumentException If date cannot be converted
     */
    public static function toTimestamp(string $canon_date, string $calendar_type, array $calendar_config): int {
        switch ($calendar_type) {
            case 'absolute':
                return self::parseAbsoluteDate($canon_date);

            case 'epoch_relative':
                return self::parseEpochRelativeDate($canon_date, $calendar_config);

            case 'age_based':
                return self::parseAgeBasedDate($canon_date, $calendar_config);

            default:
                throw new \InvalidArgumentException("Unknown calendar type: {$calendar_type}");
        }
    }

    /**
     * Convert normalized timestamp back to saga date format
     *
     * @param int $timestamp Unix timestamp
     * @param string $calendar_type Calendar type
     * @param array $calendar_config Calendar configuration
     * @return string Saga-formatted date
     */
    public static function toCanonDate(int $timestamp, string $calendar_type, array $calendar_config): string {
        switch ($calendar_type) {
            case 'absolute':
                return self::formatAbsoluteDate($timestamp);

            case 'epoch_relative':
                return self::formatEpochRelativeDate($timestamp, $calendar_config);

            case 'age_based':
                return self::formatAgeBasedDate($timestamp, $calendar_config);

            default:
                return date('Y-m-d H:i:s', $timestamp);
        }
    }

    /**
     * Parse absolute date (standard Gregorian calendar)
     *
     * @param string $date_string Date string
     * @return int Unix timestamp
     */
    private static function parseAbsoluteDate(string $date_string): int {
        $timestamp = strtotime($date_string);

        if ($timestamp === false) {
            throw new \InvalidArgumentException("Invalid absolute date: {$date_string}");
        }

        return $timestamp;
    }

    /**
     * Format absolute date
     *
     * @param int $timestamp Unix timestamp
     * @return string Formatted date
     */
    private static function formatAbsoluteDate(int $timestamp): string {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Parse epoch-relative date (e.g., "0 BBY", "10,191 AG")
     *
     * Format examples:
     * - "0 BBY" (Star Wars: Battle of Yavin)
     * - "10,191 AG" (Dune: After Guild)
     * - "TA 3019" (LOTR: Third Age)
     *
     * @param string $date_string Date string
     * @param array $config Configuration with 'epoch' and 'epoch_timestamp'
     * @return int Unix timestamp
     */
    private static function parseEpochRelativeDate(string $date_string, array $config): int {
        if (!isset($config['epoch'], $config['epoch_timestamp'])) {
            throw new \InvalidArgumentException('Epoch configuration requires epoch and epoch_timestamp');
        }

        // Parse various epoch formats
        $patterns = [
            // "32 BBY" or "0 ABY" (Before/After Battle of Yavin)
            '/^(\d+)\s+(BBY|ABY)$/i' => function($matches) use ($config) {
                $years = (int) $matches[1];
                $is_before = strtoupper($matches[2]) === 'BBY';

                $years_from_epoch = $is_before ? -$years : $years;
                return self::addYears($config['epoch_timestamp'], $years_from_epoch);
            },

            // "10,191 AG" or "10191 AG" (numeric year with suffix)
            '/^([\d,]+)\s+([A-Z]+)$/i' => function($matches) use ($config) {
                $year = (int) str_replace(',', '', $matches[1]);
                return self::addYears($config['epoch_timestamp'], $year);
            },

            // "TA 3019" or "SA 3441" (prefix + numeric year)
            '/^([A-Z]+)\s+([\d,]+)$/i' => function($matches) use ($config) {
                $age_prefix = strtoupper($matches[1]);
                $year = (int) str_replace(',', '', $matches[2]);

                // Handle age offsets if defined
                $age_offset = $config['age_offsets'][$age_prefix] ?? 0;

                return self::addYears($config['epoch_timestamp'], $age_offset + $year);
            },

            // "Year 10191" (simple year format)
            '/^Year\s+([\d,]+)$/i' => function($matches) use ($config) {
                $year = (int) str_replace(',', '', $matches[1]);
                return self::addYears($config['epoch_timestamp'], $year);
            },

            // Full date with month/day: "3019-03-25 TA"
            '/^(\d{4})-(\d{2})-(\d{2})\s+([A-Z]+)$/i' => function($matches) use ($config) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
                $age_prefix = strtoupper($matches[4]);

                $age_offset = $config['age_offsets'][$age_prefix] ?? 0;
                $base_timestamp = self::addYears($config['epoch_timestamp'], $age_offset + $year);

                // Add month and day offsets
                $base_timestamp = self::addMonths($base_timestamp, $month - 1);
                $base_timestamp = self::addDays($base_timestamp, $day - 1);

                return $base_timestamp;
            },
        ];

        foreach ($patterns as $pattern => $handler) {
            if (preg_match($pattern, $date_string, $matches)) {
                return $handler($matches);
            }
        }

        throw new \InvalidArgumentException("Cannot parse epoch-relative date: {$date_string}");
    }

    /**
     * Format epoch-relative date
     *
     * @param int $timestamp Unix timestamp
     * @param array $config Calendar configuration
     * @return string Formatted saga date
     */
    private static function formatEpochRelativeDate(int $timestamp, array $config): string {
        $epoch_timestamp = $config['epoch_timestamp'];
        $epoch_name = $config['epoch'];

        // Calculate years from epoch
        $years_diff = self::yearsDifference($epoch_timestamp, $timestamp);

        // Determine format based on configuration
        if (isset($config['format'])) {
            return self::applyDateFormat($years_diff, $timestamp, $config['format'], $epoch_name);
        }

        // Default format
        if ($years_diff >= 0) {
            return "{$years_diff} {$epoch_name}";
        } else {
            return abs($years_diff) . " B{$epoch_name}";
        }
    }

    /**
     * Parse age-based date (e.g., LOTR ages)
     *
     * @param string $date_string Date string
     * @param array $config Configuration with age definitions
     * @return int Unix timestamp
     */
    private static function parseAgeBasedDate(string $date_string, array $config): int {
        if (!isset($config['ages'])) {
            throw new \InvalidArgumentException('Age-based calendar requires ages configuration');
        }

        // Parse format like "Third Age, Year 3019"
        $pattern = '/^(.+?),?\s+Year\s+([\d,]+)$/i';

        if (preg_match($pattern, $date_string, $matches)) {
            $age_name = trim($matches[1]);
            $year_in_age = (int) str_replace(',', '', $matches[2]);

            // Find matching age
            foreach ($config['ages'] as $age) {
                if (strcasecmp($age['name'], $age_name) === 0) {
                    return $age['start_timestamp'] + self::yearsToSeconds($year_in_age);
                }
            }

            throw new \InvalidArgumentException("Unknown age: {$age_name}");
        }

        throw new \InvalidArgumentException("Cannot parse age-based date: {$date_string}");
    }

    /**
     * Format age-based date
     *
     * @param int $timestamp Unix timestamp
     * @param array $config Calendar configuration
     * @return string Formatted saga date
     */
    private static function formatAgeBasedDate(int $timestamp, array $config): string {
        if (!isset($config['ages'])) {
            return date('Y-m-d', $timestamp);
        }

        // Find which age this timestamp falls into
        foreach ($config['ages'] as $age) {
            $start = $age['start_timestamp'];
            $end = $age['end_timestamp'] ?? PHP_INT_MAX;

            if ($timestamp >= $start && $timestamp < $end) {
                $year_in_age = self::yearsDifference($start, $timestamp);
                return "{$age['name']}, Year {$year_in_age}";
            }
        }

        return date('Y-m-d', $timestamp);
    }

    /**
     * Apply custom date format
     *
     * @param int $years_diff Years from epoch
     * @param int $timestamp Unix timestamp
     * @param string $format Format string
     * @param string $epoch_name Epoch name
     * @return string Formatted date
     */
    private static function applyDateFormat(int $years_diff, int $timestamp, string $format, string $epoch_name): string {
        $replacements = [
            '{year}' => abs($years_diff),
            '{epoch}' => $epoch_name,
            '{sign}' => $years_diff >= 0 ? '' : 'B',
            '{month}' => date('m', $timestamp),
            '{day}' => date('d', $timestamp),
            '{month_name}' => date('F', $timestamp),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }

    /**
     * Add years to timestamp
     *
     * @param int $timestamp Base timestamp
     * @param int $years Years to add
     * @return int New timestamp
     */
    private static function addYears(int $timestamp, int $years): int {
        // Approximate: 365.25 days per year
        return $timestamp + ($years * 365.25 * 24 * 3600);
    }

    /**
     * Add months to timestamp
     *
     * @param int $timestamp Base timestamp
     * @param int $months Months to add
     * @return int New timestamp
     */
    private static function addMonths(int $timestamp, int $months): int {
        // Approximate: 30 days per month
        return $timestamp + ($months * 30 * 24 * 3600);
    }

    /**
     * Add days to timestamp
     *
     * @param int $timestamp Base timestamp
     * @param int $days Days to add
     * @return int New timestamp
     */
    private static function addDays(int $timestamp, int $days): int {
        return $timestamp + ($days * 24 * 3600);
    }

    /**
     * Calculate years difference between two timestamps
     *
     * @param int $from_timestamp Start timestamp
     * @param int $to_timestamp End timestamp
     * @return int Years difference
     */
    private static function yearsDifference(int $from_timestamp, int $to_timestamp): int {
        $seconds_diff = $to_timestamp - $from_timestamp;
        return (int) round($seconds_diff / (365.25 * 24 * 3600));
    }

    /**
     * Convert years to seconds
     *
     * @param int $years Years
     * @return int Seconds
     */
    private static function yearsToSeconds(int $years): int {
        return (int) ($years * 365.25 * 24 * 3600);
    }

    /**
     * Validate and normalize calendar configuration
     *
     * @param array $config Raw configuration
     * @param string $calendar_type Calendar type
     * @return array Normalized configuration
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public static function validateConfig(array $config, string $calendar_type): array {
        switch ($calendar_type) {
            case 'absolute':
                return [];

            case 'epoch_relative':
                if (!isset($config['epoch'], $config['epoch_timestamp'])) {
                    throw new \InvalidArgumentException('Epoch-relative calendar requires epoch and epoch_timestamp');
                }

                return [
                    'epoch' => $config['epoch'],
                    'epoch_timestamp' => (int) $config['epoch_timestamp'],
                    'age_offsets' => $config['age_offsets'] ?? [],
                    'format' => $config['format'] ?? null,
                ];

            case 'age_based':
                if (!isset($config['ages']) || !is_array($config['ages'])) {
                    throw new \InvalidArgumentException('Age-based calendar requires ages array');
                }

                // Validate each age
                foreach ($config['ages'] as &$age) {
                    if (!isset($age['name'], $age['start_timestamp'])) {
                        throw new \InvalidArgumentException('Each age requires name and start_timestamp');
                    }

                    $age['start_timestamp'] = (int) $age['start_timestamp'];
                    $age['end_timestamp'] = isset($age['end_timestamp']) ? (int) $age['end_timestamp'] : null;
                }

                return ['ages' => $config['ages']];

            default:
                throw new \InvalidArgumentException("Unknown calendar type: {$calendar_type}");
        }
    }

    /**
     * Get human-readable time span description
     *
     * @param int $start_timestamp Start timestamp
     * @param int $end_timestamp End timestamp
     * @return string Time span description
     */
    public static function describeTimeSpan(int $start_timestamp, int $end_timestamp): string {
        $diff_seconds = abs($end_timestamp - $start_timestamp);

        $years = (int) ($diff_seconds / (365.25 * 24 * 3600));
        $months = (int) (($diff_seconds % (365.25 * 24 * 3600)) / (30 * 24 * 3600));
        $days = (int) (($diff_seconds % (30 * 24 * 3600)) / (24 * 3600));

        $parts = [];

        if ($years > 0) {
            $parts[] = $years . ' ' . ($years === 1 ? 'year' : 'years');
        }

        if ($months > 0) {
            $parts[] = $months . ' ' . ($months === 1 ? 'month' : 'months');
        }

        if ($days > 0 && $years === 0) {
            $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
        }

        return empty($parts) ? 'Less than a day' : implode(', ', $parts);
    }
}
