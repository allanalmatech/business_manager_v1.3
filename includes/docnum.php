<?php
// includes/docnum.php
declare(strict_types=1);

/**
 * Next number for a document type in a year, e.g. RC-2026-000042.
 * Call inside the sale's transaction: the UPDATE locks the sequence row, so two
 * tills can never receive the same number, and a rolled-back sale leaves no gap.
 */
function doc_next_number(mysqli $db, string $docType, ?int $year = null): string
{
    $year = $year ?? (int)date('Y');
    $defaults = ['receipt' => 'RC', 'invoice' => 'INV', 'delivery_note' => 'DN'];
    $prefix = $defaults[$docType] ?? 'DOC';

    $st = $db->prepare("INSERT IGNORE INTO doc_sequences (doc_type, year, prefix, current_no) VALUES (?,?,?,0)");
    $st->bind_param('sis', $docType, $year, $prefix);
    $st->execute();
    $st->close();

    $st = $db->prepare("UPDATE doc_sequences SET current_no = LAST_INSERT_ID(current_no + 1) WHERE doc_type = ? AND year = ?");
    $st->bind_param('si', $docType, $year);
    if (!$st->execute() || $st->affected_rows !== 1) throw new RuntimeException('Document sequence failed: ' . $db->error);
    $st->close();
    $no = (int)$db->query("SELECT LAST_INSERT_ID()")->fetch_row()[0];

    $st = $db->prepare("SELECT prefix FROM doc_sequences WHERE doc_type = ? AND year = ? LIMIT 1");
    $st->bind_param('si', $docType, $year);
    $st->execute();
    $prefix = (string)($st->get_result()->fetch_row()[0] ?? $prefix);
    $st->close();

    return sprintf('%s-%d-%06d', $prefix, $year, $no);
}
