<?php

class FileSizeFormatter
{
    /**
     * Formats a byte size using a human readable string.
     * @param int $bytes Size in bytes.
     * @return string
     */
    public static function format($bytes)
    {
        $bytes = (int)$bytes;
        if ($bytes < 1024) {
            return $bytes . ' Б';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' Кб';
        }

        return round($bytes / 1024 / 1024, 1) . ' Мб';
    }
}
