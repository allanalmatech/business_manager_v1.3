<?php
// Fractional quantities (kg, litres) were truncated by an INT column, and
// profit was computed from today's cost price instead of the cost at sale time.
return function (mysqli $db): void {
    mig_exec($db, "ALTER TABLE sale_items MODIFY qty_base DECIMAL(12,3) NOT NULL DEFAULT 0.000");
    if (!mig_column_exists($db, 'sale_items', 'unit_cost')) {
        mig_exec($db, "ALTER TABLE sale_items ADD COLUMN unit_cost DECIMAL(12,2) NULL AFTER unit_price");
    }
    // Best available backfill for history: the product's current cost.
    mig_exec($db, "UPDATE sale_items si JOIN products p ON p.id = si.product_id
                   SET si.unit_cost = p.cost_price WHERE si.unit_cost IS NULL");
    if (!mig_column_exists($db, 'sales', 'has_b2b')) {
        mig_exec($db, "ALTER TABLE sales ADD COLUMN has_b2b TINYINT(1) NOT NULL DEFAULT 0");
    }
};
