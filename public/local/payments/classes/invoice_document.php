<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders a paid transaction as a downloadable PDF invoice.
 *
 * Bilingual: the layout mirrors for Arabic rather than being translated in
 * place, because a right-to-left invoice with left-aligned columns reads as
 * broken. Everything that has a side — table alignment, the totals block, the
 * header — flips together, and the whole document is set in FreeSerif, the one
 * bundled TCPDF font that carries Arabic glyphs.
 */
class invoice_document {

    /** Font that covers both Latin and Arabic. */
    const FONT = 'freeserif';

    /**
     * Build the PDF for a transaction.
     *
     * @param \stdClass $transaction Row from local_payments_transactions.
     * @param string $lang 'en' or 'ar'.
     * @return string The PDF bytes.
     */
    public static function render(\stdClass $transaction, string $lang): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $rtl = ($lang === 'ar');
        $data = self::collect($transaction, $lang);

        $pdf = new \pdf();
        $pdf->SetCreator('Moodle');
        $pdf->SetAuthor($data['seller_name']);
        $pdf->SetTitle($data['invoice_number']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setRTL($rtl);
        $pdf->AddPage();

        self::write_header($pdf, $data);
        self::write_parties($pdf, $data, $rtl);
        self::write_items($pdf, $data, $rtl);
        self::write_totals($pdf, $data, $rtl);
        self::write_footer($pdf, $data);

        return $pdf->Output('', 'S');
    }

    /**
     * A filename that is safe on every OS and still identifies the invoice.
     */
    public static function filename(\stdClass $transaction, string $lang): string {
        global $DB;
        $number = $DB->get_field('local_payments_invoices', 'invoice_number',
            ['transaction_id' => $transaction->id]);
        $stem = $number ?: $transaction->order_id;
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $stem) . '-' . $lang . '.pdf';
    }

    /**
     * Everything the layout needs, resolved once.
     */
    private static function collect(\stdClass $transaction, string $lang): array {
        global $DB, $SITE;

        $strman = get_string_manager();
        $s = static function (string $key) use ($strman, $lang): string {
            return $strman->get_string($key, 'local_payments', null, $lang);
        };

        $meta = json_decode($transaction->metadata ?? '{}', true) ?: [];
        $invoice = $DB->get_record('local_payments_invoices', ['transaction_id' => $transaction->id]);
        $buyer = $DB->get_record('user', ['id' => $transaction->userid],
            'id, firstname, lastname, email, phone1, phone2, city, country');

        // What was bought. A course has a name in its own table; a subscription
        // or package only has the name we recorded at checkout.
        $itemname = '';
        if (($meta['item_type'] ?? 'course') === 'course' && !empty($transaction->courseid)) {
            $itemname = (string) $DB->get_field('course', 'fullname', ['id' => $transaction->courseid]);
        }
        if ($itemname === '') {
            $itemname = (string) ($meta['subscription_name'] ?? $meta['course_name'] ?? $s('invoice_item'));
        }

        $amount = (float) $transaction->amount;
        $original = (float) ($transaction->original_amount ?? $amount);
        $discount = max(0, $original - $amount);

        $sellername = trim((string) get_config('local_payments', 'invoice_seller_name'));
        if ($sellername === '') {
            $sellername = format_string($SITE->fullname);
        }

        return [
            's' => $s,
            'lang' => $lang,
            'invoice_number' => $invoice->invoice_number ?? $transaction->order_id,
            'invoice_date' => userdate($invoice->timecreated ?? $transaction->timecreated,
                get_string('strftimedaydate', 'langconfig')),
            'order_id' => $transaction->order_id,
            'status' => $transaction->status,
            'seller_name' => $sellername,
            'seller_details' => trim((string) get_config('local_payments', 'invoice_seller_details')),
            'buyer_name' => $buyer ? fullname($buyer) : '',
            'buyer_email' => $buyer->email ?? '',
            'buyer_phone' => trim((string) (($buyer->phone1 ?? '') ?: ($buyer->phone2 ?? ''))),
            'item_name' => $itemname,
            'currency' => $transaction->currency,
            'original' => $original,
            'discount' => $discount,
            'coupon' => (string) ($meta['coupon_code'] ?? ''),
            'amount' => $amount,
            'payment_method' => (string) ($transaction->payment_method_type ?: ''),
            'paid_date' => userdate($transaction->timemodified ?: $transaction->timecreated),
            'footer_note' => trim((string) get_config('local_payments', 'invoice_footer')),
        ];
    }

    private static function write_header(\pdf $pdf, array $data): void {
        $s = $data['s'];

        $pdf->SetFont(self::FONT, 'B', 20);
        $pdf->Cell(0, 12, $s('invoice_title'), 0, 1);

        $pdf->SetFont(self::FONT, '', 10);
        $pdf->Cell(0, 6, $s('invoice_number') . ': ' . $data['invoice_number'], 0, 1);
        $pdf->Cell(0, 6, $s('invoice_date') . ': ' . $data['invoice_date'], 0, 1);
        $pdf->Cell(0, 6, $s('orderid') . ': ' . $data['order_id'], 0, 1);
        $pdf->Ln(4);
    }

    private static function write_parties(\pdf $pdf, array $data, bool $rtl): void {
        $s = $data['s'];
        $align = $rtl ? 'R' : 'L';

        $seller = '<b>' . s($data['seller_name']) . '</b>';
        if ($data['seller_details'] !== '') {
            $seller .= '<br/>' . nl2br(s($data['seller_details']));
        }

        $buyer = '<b>' . s($data['buyer_name']) . '</b>';
        if ($data['buyer_email'] !== '') {
            $buyer .= '<br/>' . s($data['buyer_email']);
        }
        if ($data['buyer_phone'] !== '') {
            $buyer .= '<br/>' . s($data['buyer_phone']);
        }

        $pdf->SetFont(self::FONT, 'B', 11);
        $pdf->Cell(0, 7, $s('invoice_from'), 0, 1, $align);
        $pdf->SetFont(self::FONT, '', 10);
        $pdf->writeHTMLCell(0, 0, '', '', $seller, 0, 1, false, true, $align);
        $pdf->Ln(3);

        $pdf->SetFont(self::FONT, 'B', 11);
        $pdf->Cell(0, 7, $s('invoice_to'), 0, 1, $align);
        $pdf->SetFont(self::FONT, '', 10);
        $pdf->writeHTMLCell(0, 0, '', '', $buyer, 0, 1, false, true, $align);
        $pdf->Ln(5);
    }

    private static function write_items(\pdf $pdf, array $data, bool $rtl): void {
        $s = $data['s'];
        $descwidth = 130;
        $amountwidth = 50;

        $pdf->SetFont(self::FONT, 'B', 10);
        $pdf->SetFillColor(240, 240, 240);
        // In RTL, TCPDF lays cells out right to left, so the description column
        // still comes first in reading order — the amounts simply align to the
        // opposite edge.
        $pdf->Cell($descwidth, 9, $s('invoice_description'), 1, 0, $rtl ? 'R' : 'L', true);
        $pdf->Cell($amountwidth, 9, $s('amount'), 1, 1, $rtl ? 'L' : 'R', true);

        $pdf->SetFont(self::FONT, '', 10);
        $pdf->Cell($descwidth, 9, $data['item_name'], 1, 0, $rtl ? 'R' : 'L');
        $pdf->Cell($amountwidth, 9, self::money($data['original'], $data['currency']), 1, 1,
            $rtl ? 'L' : 'R');
    }

    private static function write_totals(\pdf $pdf, array $data, bool $rtl): void {
        $s = $data['s'];
        $labelwidth = 130;
        $valuewidth = 50;

        $line = static function (string $label, string $value, bool $bold) use ($pdf, $rtl, $labelwidth, $valuewidth) {
            $pdf->SetFont(self::FONT, $bold ? 'B' : '', $bold ? 12 : 10);
            $pdf->Cell($labelwidth, 8, $label, 0, 0, $rtl ? 'L' : 'R');
            $pdf->Cell($valuewidth, 8, $value, 0, 1, $rtl ? 'L' : 'R');
        };

        if ($data['discount'] > 0.001) {
            $line($s('invoice_subtotal'), self::money($data['original'], $data['currency']), false);

            $label = $s('invoice_discount');
            if ($data['coupon'] !== '') {
                $label .= ' (' . $data['coupon'] . ')';
            }
            $line($label, '-' . self::money($data['discount'], $data['currency']), false);
        }

        $line($s('invoice_total'), self::money($data['amount'], $data['currency']), true);
        $pdf->Ln(4);

        $pdf->SetFont(self::FONT, '', 10);
        if ($data['payment_method'] !== '') {
            $pdf->Cell(0, 6, $s('paymentmethod') . ': ' . $data['payment_method'], 0, 1,
                $rtl ? 'R' : 'L');
        }
        $pdf->Cell(0, 6, $s('invoice_paid_on') . ': ' . $data['paid_date'], 0, 1, $rtl ? 'R' : 'L');
    }

    private static function write_footer(\pdf $pdf, array $data): void {
        $s = $data['s'];

        $pdf->Ln(8);
        $pdf->SetFont(self::FONT, '', 9);
        $pdf->SetTextColor(110, 110, 110);

        $note = $data['footer_note'] !== '' ? $data['footer_note'] : $s('invoice_footer_default');
        $pdf->writeHTMLCell(0, 0, '', '', nl2br(s($note)), 0, 1, false, true, 'C');
    }

    /**
     * Amounts are written the same way in both languages: Western digits with the
     * ISO code. An invoice is a financial record, and a number that reads
     * differently per language is a support call waiting to happen.
     */
    private static function money(float $amount, string $currency): string {
        return number_format($amount, 2, '.', ',') . ' ' . $currency;
    }
}
