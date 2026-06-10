<?php

namespace App\Helpers;

class CryptoHelper
{
    /**
     * Calculate commission safely
     */
    public static function calculateCommission($amount, $commissionRate, int $precision = 20, bool $format = true): string
    {
        $amount = (string)$amount;
        $commissionRate = (string)$commissionRate;

        $commission = bcdiv(bcmul($amount, $commissionRate, $precision + 4), '100', $precision);

        return $format ? bcadd($commission, '0', $precision) : $commission;
    }

    /**
     * Calculate GST safely
     */
    public static function calculateGST($amount, $gstRate, int $precision = 20, bool $format = true): string
    {
        $amount = (string)$amount;
        $gstRate = (string)$gstRate;

        $gst = bcdiv(bcmul($amount, $gstRate, $precision + 4), '100', $precision);

        return $format ? bcadd($gst, '0', $precision) : $gst;
    }

    /**
     * Calculate rolling charge (percentage or fixed)
     */
    public static function calculateRollingCharge($transactionAmount, $rollingPayinAmount = null, $rollingFixedAmount = null, int $precision = 20): array
    {
        $transactionAmount = (string)$transactionAmount;
        $rollingCharge = "0";
        $rollingAmount = "0";

        if (!empty($rollingPayinAmount)) {
            $rollingCharge = bcdiv(bcmul($transactionAmount, $rollingPayinAmount, $precision + 4), '100', $precision);
            $rollingAmount = $rollingCharge;
        } elseif (!empty($rollingFixedAmount)) {
            $rollingCharge = "0";
            $rollingAmount = (string)$rollingFixedAmount;
        }

        return [
            'rollingCharge' => $rollingCharge,
            'rollingAmount' => $rollingAmount,
        ];
    }

    /**
     * Calculate total commission + GST + remaining amount
     */
    public static function calculateTotal(
        $transactionAmount,
        $commissionRate,
        $gstRate,
        $rollingPayinAmount = null,
        $rollingFixedAmount = null,
        int $precision = 20
    ): array {
        $transactionAmount = (string)$transactionAmount;

        // Commission
        $commission = self::calculateCommission($transactionAmount, $commissionRate, $precision, false);

        // GST
        $gst = self::calculateGST($commission, $gstRate, $precision, false);

        // Rolling charge
        $rolling = self::calculateRollingCharge($transactionAmount, $rollingPayinAmount, $rollingFixedAmount, $precision);
        $rollingCharge = $rolling['rollingCharge'];

        // Total commission + GST
        $totalCommissionWithGst = bcadd($commission, $gst, $precision);

        // Remaining amount after all deductions
        $remainingAmount = bcsub($transactionAmount, bcadd($totalCommissionWithGst, $rollingCharge, $precision), $precision);

        // Format all outputs
        return [
            'commission' => bcadd($commission, '0', $precision),
            'gst' => bcadd($gst, '0', $precision),
            'rollingCharge' => bcadd($rollingCharge, '0', $precision),
            'totalCommissionWithGst' => bcadd($totalCommissionWithGst, '0', $precision),
            'remainingAmount' => bcadd($remainingAmount, '0', $precision),
        ];
    }
}
