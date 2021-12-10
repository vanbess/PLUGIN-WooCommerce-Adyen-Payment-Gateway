<?php

trait SBAGetDecimals
{
    /**
     * Determines correct decimal count and returns it
     *
     * @param  string $currency
     * @return int $decimals
     */
    public static function get_decimals($currency)
    {
        $three_decimals = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];
        $zero_decimals = ['CVE', 'GNF', 'IDR', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

        if (in_array($currency, $three_decimals)) :
            $decimals = 3;
        elseif (in_array($currency, $zero_decimals)) :
            $decimals = 0;
        else :
            $decimals = 2;
        endif;

        return $decimals;
    }
}
