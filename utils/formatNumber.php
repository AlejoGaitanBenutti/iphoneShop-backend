<?php



function formatNumber($num)
{
    if (!is_numeric($num)) {
        $num = 0;
    }

    if ($num >= 1000000000) {
        return number_format($num / 1000000000, 1) . 'B'; // Miles de millones
    } elseif ($num >= 1000000) {
        return number_format($num / 1000000, 1) . 'M'; // Millones
    } elseif ($num >= 1000) {
        return number_format($num / 1000, 1) . 'K'; // Miles
    }

    return number_format($num, 0); // Números pequeños sin decimales
}
