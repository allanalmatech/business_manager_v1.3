# B2B Audit Trail Implementation

## Overview
This document outlines all audit trail logging hooks for B2B functionality, ensuring complete traceability of B2B transactions and operations.

## Audit Events

### 1. B2B Item Addition to Sale
**Event**: `pos.b2b.add_lines`
**Called From**: `/modules/pos/pos_api.php` (confirm_sale action)
**Location**: After successful B2B lines insertion
**Format**:
```php
audit_log('pos.b2b.add_lines', 'sales', (string)$sale_id, "Added {$b2b_count} B2B line(s) to sale #{$sale_id}");
```

### 2. B2B Item Addition to Sale (Sales API)
**Event**: `sales.b2b.add_lines`
**Called From**: `/api/sales.php` (create action)
**Location**: After successful B2B lines insertion
**Format**:
```php
audit_log('sales.b2b.add_lines', 'sales', (string)$saleId, "Added {$b2b_count} B2B line(s) to sale #{$saleId}");
```

### 3. B2B Item Added to Shopping List
**Event**: `b2b.add_to_shopping_list`
**Called From**: `/api/reports/b2b.php` (add_to_shopping_list action)
**Location**: After successful shopping list insertion
**Format**:
```php
audit_log('b2b.add_to_shopping_list', 'b2b_sales_items', (string)$b2b_id, "ShoppingList #{$sl_id}");
```

### 4. B2B Report View
**Event**: `reports.b2b.view`
**Called From**: `/modules/reports/b2b_report.php` (page load)
**Location**: At page load for access tracking
**Format**:
```php
audit_log('reports.b2b.view', 'reports', null, "B2B report accessed");
```

## Standard Audit Format

All B2B audit events follow this standard format:

```php
audit_log(
    string $action,        // Event type (e.g., 'pos.b2b.add_lines')
    ?string $entity,       // Entity type (e.g., 'sales', 'b2b_sales_items')
    ?string $entityId,     // Entity ID (e.g., sale ID, B2B item ID)
    ?string $details       // Human-readable details
);
```

## Implementation Examples

### POS Checkout B2B Logging
```php
// In pos_api.php - confirm_sale action
if ($b2b_count > 0) {
    audit_log('pos.b2b.add_lines', 'sales', (string)$sale_id, 
              "Added {$b2b_count} B2B line(s) to sale #{$sale_id}");
}
```

### Sales API B2B Logging
```php
// In api/sales.php - create action
if ($b2b_count > 0) {
    audit_log('sales.b2b.add_lines', 'sales', (string)$saleId, 
              "Added {$b2b_count} B2B line(s) to sale #{$saleId}");
}
```

### Shopping List Conversion Logging
```php
// In api/reports/b2b.php - add_to_shopping_list action
audit_log('b2b.add_to_shopping_list', 'b2b_sales_items', (string)$b2b_id, 
          "ShoppingList #{$sl_id}");
```

## Required Permissions

### B2B Report Access
- **Permission**: `reports.b2b.view`
- **Description**: View B2B items report
- **Roles**: cashier, accountant, manager, super_admin

### Shopping List Creation
- **Permission**: `shopping_list.create`
- **Description**: Add items to shopping list
- **Roles**: cashier, manager, super_admin

## Database Schema Impact

### Audit Logs Table
The existing `audit_logs` table will store B2B events:
```sql
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity VARCHAR(100),
    entity_id VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Security Considerations

1. **Input Validation**: All audit details are properly sanitized
2. **User Context**: Audit logs capture the current user ID
3. **IP Tracking**: Client IP address is recorded
4. **Session Context**: User agent information is captured
5. **Data Integrity**: Audit logs are immutable once created

## Reporting and Analysis

### Common B2B Audit Queries

**All B2B Activities by User**:
```sql
SELECT action, entity, entity_id, details, created_at
FROM audit_logs 
WHERE action LIKE '%b2b%' 
  AND user_id = ?
ORDER BY created_at DESC;
```

**B2B Sales by Date Range**:
```sql
SELECT action, COUNT(*) as count, created_at::date as date
FROM audit_logs 
WHERE action IN ('pos.b2b.add_lines', 'sales.b2b.add_lines')
  AND created_at BETWEEN ? AND ?
GROUP BY date, action
ORDER BY date DESC;
```

**Shopping List Conversions**:
```sql
SELECT al.action, al.entity_id, al.details, al.created_at,
       u.name as user_name
FROM audit_logs al
JOIN users u ON u.id = al.user_id
WHERE al.action = 'b2b.add_to_shopping_list'
ORDER BY al.created_at DESC;
```

## Compliance Features

1. **Complete Traceability**: Every B2B operation is logged
2. **User Attribution**: Actions are linked to specific users
3. **Timestamp Accuracy**: Precise creation timestamps
4. **Detail Capture**: Contextual information for each event
5. **Immutable Records**: Audit logs cannot be modified

## Troubleshooting

### Missing Audit Entries
- Verify `audit_log()` function is available
- Check database connection in audit system
- Ensure proper error handling in audit calls

### Permission Issues
- Run the B2B permissions setup SQL script
- Verify role assignments in user management
- Check RBAC system functionality

### Performance Considerations
- Audit logging uses prepared statements for efficiency
- Minimal impact on checkout performance
- Consider audit log rotation for long-term deployments
