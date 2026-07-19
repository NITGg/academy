<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-side helper for student-facing invoices: list a user's invoices, fetch a
 * single invoice (with ownership checks), shape rows for display, and render a PDF.
 */
class invoice_manager {

    /**
     * A page of the user's invoices, newest first.
     *
     * @param array $filters optional: invoicenumber, item, type, status, amountmin,
     *                        amountmax, datefrom, dateto (see build_filter_sql())
     * @return array{rows: array, total: int}
     */
    public static function get_user_invoices(int $userid, int $page = 0, int $perpage = 20,
            array $filters = []): array {
        global $DB;

        [$filterwhere, $params] = self::build_filter_sql($filters);
        $where = 'userid = :userid' . ($filterwhere !== '' ? ' AND ' . $filterwhere : '');
        $params['userid'] = $userid;

        $total = $DB->count_records_select('local_payments_invoices', $where, $params);
        $records = $DB->get_records_select('local_payments_invoices', $where, $params,
            'timecreated DESC, id DESC', '*', $page * $perpage, $perpage);

        $rows = [];
        foreach ($records as $rec) {
            $rows[] = self::format_row($rec);
        }
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Build a WHERE fragment + params from the invoices-page filter inputs.
     *
     * @return array{0: string, 1: array} [$where, $params] — $where is '' when no filter is set.
     */
    private static function build_filter_sql(array $filters): array {
        global $DB;

        $clauses = [];
        $params = [];

        if (!empty($filters['invoicenumber'])) {
            $clauses[] = $DB->sql_like('invoice_number', ':invoicenumber', false);
            $params['invoicenumber'] = '%' . $DB->sql_like_escape(trim($filters['invoicenumber'])) . '%';
        }
        if (!empty($filters['item'])) {
            $clauses[] = $DB->sql_like('item_name', ':itemname', false);
            $params['itemname'] = '%' . $DB->sql_like_escape(trim($filters['item'])) . '%';
        }
        if (!empty($filters['type']) && in_array($filters['type'],
                [invoice_generator::SOURCE_COURSE, invoice_generator::SOURCE_PACKAGE,
                 invoice_generator::SOURCE_SUBSCRIPTION], true)) {
            $clauses[] = 'source_type = :sourcetype';
            $params['sourcetype'] = $filters['type'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['issued', 'void', 'draft'], true)) {
            $clauses[] = 'status = :invstatus';
            $params['invstatus'] = $filters['status'];
        }
        if (isset($filters['amountmin']) && $filters['amountmin'] !== '' && is_numeric($filters['amountmin'])) {
            $clauses[] = 'amount >= :amountmin';
            $params['amountmin'] = (float) $filters['amountmin'];
        }
        if (isset($filters['amountmax']) && $filters['amountmax'] !== '' && is_numeric($filters['amountmax'])) {
            $clauses[] = 'amount <= :amountmax';
            $params['amountmax'] = (float) $filters['amountmax'];
        }
        if (!empty($filters['datefrom'])) {
            $ts = strtotime($filters['datefrom'] . ' 00:00:00');
            if ($ts !== false) {
                $clauses[] = 'timecreated >= :datefrom';
                $params['datefrom'] = $ts;
            }
        }
        if (!empty($filters['dateto'])) {
            $ts = strtotime($filters['dateto'] . ' 23:59:59');
            if ($ts !== false) {
                $clauses[] = 'timecreated <= :dateto';
                $params['dateto'] = $ts;
            }
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Fetch a single invoice the given user is allowed to see.
     *
     * @param bool $canviewall true for staff with local/payments:viewalltransactions
     * @return \stdClass|null
     */
    public static function get_invoice(int $invoiceid, int $userid, bool $canviewall = false): ?\stdClass {
        global $DB;
        $invoice = $DB->get_record('local_payments_invoices', ['id' => $invoiceid]);
        if (!$invoice) {
            return null;
        }
        if ((int) $invoice->userid !== $userid && !$canviewall) {
            return null;
        }
        return $invoice;
    }

    /** Shape an invoice record for a list row. */
    public static function format_row(\stdClass $rec): array {
        return [
            'id'             => (int) $rec->id,
            'invoice_number' => $rec->invoice_number,
            'item_name'      => $rec->item_name ?: self::source_label($rec->source_type),
            'source_type'    => $rec->source_type,
            'source_label'   => self::source_label($rec->source_type),
            'amount'         => self::money($rec->amount, $rec->currency),
            'status'         => $rec->status,
            'status_label'   => self::status_label($rec->status),
            'status_class'   => self::status_class($rec->status),
            'date'           => userdate((int) $rec->timecreated, get_string('strftimedate', 'langconfig')),
        ];
    }

    /** Full display context for the detail view / PDF. */
    public static function detail_context(\stdClass $invoice, \stdClass $user): array {
        global $SITE;

        $hasdiscount = ($invoice->discount !== null && (float) $invoice->discount > 0);
        $sellername = self::config('invoice_seller_name') ?: format_string($SITE->fullname);

        return [
            'invoice_number' => $invoice->invoice_number,
            'status'         => $invoice->status,
            'status_label'   => self::status_label($invoice->status),
            'status_class'   => self::status_class($invoice->status),
            'is_void'        => ($invoice->status === 'void'),
            'date'           => userdate((int) $invoice->timecreated, get_string('strftimedaydate', 'langconfig')),
            'source_label'   => self::source_label($invoice->source_type),
            'item_name'      => $invoice->item_name ?: self::source_label($invoice->source_type),
            'seller_name'    => $sellername,
            'seller_details' => self::config('invoice_seller_details'),
            'seller_taxid'   => self::config('invoice_seller_taxid'),
            'footer'         => self::config('invoice_footer'),
            'buyer_name'     => fullname($user),
            'buyer_email'    => $user->email,
            'currency'       => $invoice->currency,
            'subtotal'       => self::money($invoice->subtotal ?? $invoice->amount, $invoice->currency),
            'has_discount'   => $hasdiscount,
            'discount'       => $hasdiscount ? '-' . self::money($invoice->discount, $invoice->currency) : null,
            'amount'         => self::money($invoice->amount, $invoice->currency),
        ];
    }

    /**
     * Stream a simple invoice PDF to the browser (uses Moodle's bundled TCPDF).
     * Terminates the request.
     */
    public static function stream_pdf(\stdClass $invoice, \stdClass $user): void {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $ctx = self::detail_context($invoice, $user);

        $pdf = new \pdf();
        $pdf->SetCreator('local_payments');
        $pdf->SetTitle($ctx['invoice_number']);
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->AddPage();

        // RTL support for Arabic invoices.
        if (right_to_left()) {
            $pdf->setRTL(true);
        }

        $rows = '';
        $rows .= self::pdf_line(get_string('invoice_item', 'local_payments'), $ctx['item_name']);
        $rows .= self::pdf_line(get_string('invoice_type', 'local_payments'), $ctx['source_label']);
        $rows .= self::pdf_line(get_string('invoice_subtotal', 'local_payments'), $ctx['subtotal']);
        if ($ctx['has_discount']) {
            $rows .= self::pdf_line(get_string('invoice_discount', 'local_payments'), $ctx['discount']);
        }
        $rows .= self::pdf_line('<b>' . get_string('invoice_total', 'local_payments') . '</b>', '<b>' . $ctx['amount'] . '</b>');

        $taxline = $ctx['seller_taxid']
            ? '<div>' . get_string('invoice_taxid', 'local_payments') . ': ' . s($ctx['seller_taxid']) . '</div>' : '';
        $footer = $ctx['footer'] ? '<hr><div style="font-size:9px;color:#666">' . s($ctx['footer']) . '</div>' : '';

        $html = '
            <h1 style="color:#7a1f4b">' . get_string('invoice', 'local_payments') . '</h1>
            <table style="width:100%"><tr>
                <td style="width:50%">
                    <b>' . s($ctx['seller_name']) . '</b><br>' . nl2br(s($ctx['seller_details'])) . $taxline . '
                </td>
                <td style="width:50%;text-align:right">
                    <b>' . get_string('invoice_number', 'local_payments') . '</b>: ' . s($ctx['invoice_number']) . '<br>
                    <b>' . get_string('date', 'local_payments') . '</b>: ' . s($ctx['date']) . '<br>
                    <b>' . get_string('status', 'local_payments') . '</b>: ' . s($ctx['status_label']) . '
                </td>
            </tr></table>
            <br>
            <b>' . get_string('invoice_billedto', 'local_payments') . '</b><br>
            ' . s($ctx['buyer_name']) . '<br>' . s($ctx['buyer_email']) . '<br><br>
            <table border="1" cellpadding="6" style="width:100%;border-collapse:collapse">' . $rows . '</table>
            ' . $footer;

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($ctx['invoice_number'] . '.pdf', 'D');
        exit;
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    private static function pdf_line(string $label, string $value): string {
        return '<tr><td style="width:60%">' . $label . '</td>'
            . '<td style="width:40%;text-align:right">' . $value . '</td></tr>';
    }

    /** Localized label for a purchase source type. */
    public static function source_label(string $source_type): string {
        switch ($source_type) {
            case invoice_generator::SOURCE_PACKAGE:
                return get_string('invoice_item_package', 'local_payments');
            case invoice_generator::SOURCE_SUBSCRIPTION:
                return get_string('invoice_item_subscription', 'local_payments');
            default:
                return get_string('invoice_item_course', 'local_payments');
        }
    }

    private static function status_label(string $status): string {
        $key = 'invstatus_' . $status;
        $manager = \get_string_manager();
        return $manager->string_exists($key, 'local_payments')
            ? get_string($key, 'local_payments') : ucfirst($status);
    }

    private static function status_class(string $status): string {
        switch ($status) {
            case 'issued': return 'badge-success';
            case 'void':   return 'badge-secondary';
            case 'draft':  return 'badge-warning';
            default:       return 'badge-info';
        }
    }

    private static function money($amount, string $currency): string {
        return number_format((float) $amount, 2) . ' ' . $currency;
    }

    private static function config(string $name): string {
        $val = get_config('local_payments', $name);
        return ($val === false || $val === null) ? '' : (string) $val;
    }
}
