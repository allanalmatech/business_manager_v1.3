<?php
// includes/tax.php
declare(strict_types=1);

require_once __DIR__ . '/settings.php';

/** @return array{enabled:bool,name:string,rate:float,inclusive:bool} */
function tax_config(): array
{
    return [
        'enabled'   => setting_bool('tax_enabled'),
        'name'      => setting('tax_name'),
        'rate'      => max(0.0, (float)setting('tax_rate')),
        'inclusive' => setting_bool('prices_include_tax'),
    ];
}

/**
 * Tax on an amount that already has discounts taken off.
 * Inclusive: the amount contains the tax, the total stays the same.
 * Exclusive: tax is added on top.
 *
 * @return array{tax: float, total: float}
 */
function tax_compute(float $amount, ?array $cfg = null): array
{
    $cfg = $cfg ?? tax_config();
    if (!$cfg['enabled'] || $cfg['rate'] <= 0 || $amount <= 0) return ['tax' => 0.0, 'total' => round($amount, 2)];
    $r = $cfg['rate'] / 100;
    if ($cfg['inclusive']) {
        return ['tax' => round($amount - $amount / (1 + $r), 2), 'total' => round($amount, 2)];
    }
    $tax = round($amount * $r, 2);
    return ['tax' => $tax, 'total' => round($amount + $tax, 2)];
}
