<?php
class DeclensionHelper
{
    public static function hours($count)
    {
        return self::getDeclension(['час', 'часа', 'часов'], $count);
    }

    public static function storyPoints($count, $short = false)
    {
        return ($short ? 'SP' : ($count > 1 ? 'story points' : 'story point'));
    }

    public static function getDeclension($variants, $count)
    {
        if ($count < 0) {
            $count = -$count;
        }

        if ($count < 1) {
            return $variants[1];
        }

        if ($count > 10 && $count < 15) {
            return $variants[2];
        }

        switch ($count % 10) {
            case 1:
                return $variants[0];
            case 2:
            case 3:
            case 4:
                return $variants[1];
            default:
                return $variants[2];
        }
    }
}
