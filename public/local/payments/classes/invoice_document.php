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

        self::write_header($pdf, $data, $rtl);
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
        // Course and plan names are stored bilingually in one field. Nothing
        // resolves that on the way into a PDF, so the invoice printed the raw
        // {mlang} markup until this was here.
        $itemname = multilang::resolve($itemname, $lang);

        $amount = (float) $transaction->amount;
        $original = (float) ($transaction->original_amount ?? $amount);
        $discount = max(0, $original - $amount);

        // The seller block and the footer are free-text settings, so they can be
        // written bilingually too.
        $sellername = trim(multilang::resolve(get_config('local_payments', 'invoice_seller_name'), $lang));
        if ($sellername === '') {
            $sellername = multilang::resolve(format_string($SITE->fullname), $lang);
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
            'seller_details' => trim(multilang::resolve(get_config('local_payments', 'invoice_seller_details'), $lang)),
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
            'footer_note' => trim(multilang::resolve(get_config('local_payments', 'invoice_footer'), $lang)),
        ];
    }

    private static function write_header(\pdf $pdf, array $data, bool $rtl): void {
        $s = $data['s'];

        // The logo sits in the corner opposite the reading direction, so it never
        // collides with the title in either language.
        $logo = self::logo();
        $titlewidth = 0;
        if ($logo !== null) {
            $height = 18;
            $width = self::logo_width($logo, $height);
            // The logo goes to the corner opposite the reading edge: the title
            // starts at the left in English and at the right in Arabic.
            //
            // The two cases are not symmetric, because TCPDF reads $x as the
            // image's LEFT edge in LTR and its RIGHT edge in RTL
            // ($ximg = $this->rtl ? $x - $w : $x). Passing the same left-edge
            // coordinate in both put the Arabic logo at 15 - width, i.e. off the
            // side of the page.
            $x = $rtl ? 15 + $width : $pdf->getPageWidth() - 15 - $width;
            // '@' tells TCPDF the string is the image itself rather than a path.
            $pdf->Image('@' . $logo['content'], $x, 12, $width, $height, '', '', '', true);
            // Reserve the strip the logo occupies so the title does not run under it.
            $titlewidth = $pdf->getPageWidth() - 30 - $width - 5;
        }

        $pdf->SetFont(self::FONT, 'B', 20);
        $pdf->Cell($titlewidth, 12, $s('invoice_title'), 0, 1);

        $pdf->SetFont(self::FONT, '', 10);
        $pdf->Cell($titlewidth, 6, $s('invoice_number') . ': '
            . self::ltr($data['invoice_number'], $rtl), 0, 1);
        $pdf->Cell($titlewidth, 6, $s('invoice_date') . ': '
            . self::ltr($data['invoice_date'], $rtl), 0, 1);
        $pdf->Cell($titlewidth, 6, $s('orderid') . ': '
            . self::ltr($data['order_id'], $rtl), 0, 1);

        // Clear the logo strip before the next block starts.
        if ($logo !== null && $pdf->GetY() < 34) {
            $pdf->SetY(34);
        }
        $pdf->Ln(4);
    }

    /**
     * The logo to print, as raw bytes plus its pixel size.
     *
     * Falls back to the site logo when no invoice-specific one is uploaded, so an
     * invoice is branded out of the box. The dedicated setting stays useful: a
     * site logo is often designed for a dark header and can be invisible on
     * white paper.
     *
     * @return array|null ['content' => string, 'w' => int, 'h' => int]
     */
    private static function logo(): ?array {
        $contextid = \context_system::instance()->id;
        $candidates = [
            ['local_payments', 'invoice_logo'],
            ['core_admin', 'logo'],
            ['core_admin', 'logocompact'],
        ];

        $fs = get_file_storage();
        foreach ($candidates as [$component, $filearea]) {
            $files = $fs->get_area_files($contextid, $component, $filearea, 0, 'itemid', false);
            if (empty($files)) {
                continue;
            }

            $file = reset($files);
            $content = $file->get_content();
            if ($content === '') {
                continue;
            }

            // TCPDF can only place raster images this way; an SVG site logo has
            // to be skipped rather than drawn as garbage.
            $size = @getimagesizefromstring($content);
            if ($size === false) {
                continue;
            }

            return [
                'content' => $content,
                'w' => (int) $size[0],
                'h' => (int) $size[1],
            ];
        }

        return null;
    }

    /**
     * Width that keeps the logo's aspect ratio at a fixed height, capped so a
     * very wide banner cannot push the invoice title off the page.
     */
    private static function logo_width(array $logo, float $height): float {
        if (empty($logo['w']) || empty($logo['h'])) {
            return $height; // Unknown dimensions: assume square.
        }
        return min($height * ($logo['w'] / $logo['h']), 60);
    }

    private static function write_parties(\pdf $pdf, array $data, bool $rtl): void {
        $s = $data['s'];
        $align = $rtl ? 'R' : 'L';

        $seller = '<b>' . s(self::ltr($data['seller_name'], $rtl)) . '</b>';
        if ($data['seller_details'] !== '') {
            $seller .= '<br/>' . nl2br(s($data['seller_details']));
        }

        $buyer = '<b>' . s(self::ltr($data['buyer_name'], $rtl)) . '</b>';
        if ($data['buyer_email'] !== '') {
            $buyer .= '<br/>' . s(self::ltr($data['buyer_email'], $rtl));
        }
        if ($data['buyer_phone'] !== '') {
            $buyer .= '<br/>' . s(self::ltr($data['buyer_phone'], $rtl));
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
        $pdf->Cell($descwidth, 9, self::ltr($data['item_name'], $rtl), 1, 0, $rtl ? 'R' : 'L');
        $pdf->Cell($amountwidth, 9,
            self::ltr(self::money($data['original'], $data['currency']), $rtl), 1, 1,
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
            $line($s('invoice_subtotal'),
                self::ltr(self::money($data['original'], $data['currency']), $rtl), false);

            $label = $s('invoice_discount');
            if ($data['coupon'] !== '') {
                $label .= ' (' . self::ltr($data['coupon'], $rtl) . ')';
            }
            $line($label,
                self::ltr('-' . self::money($data['discount'], $data['currency']), $rtl), false);
        }

        $line($s('invoice_total'),
            self::ltr(self::money($data['amount'], $data['currency']), $rtl), true);
        $pdf->Ln(4);

        $pdf->SetFont(self::FONT, '', 10);
        if ($data['payment_method'] !== '') {
            $pdf->Cell(0, 6, $s('paymentmethod') . ': '
                . self::ltr($data['payment_method'], $rtl), 0, 1, $rtl ? 'R' : 'L');
        }
        $pdf->Cell(0, 6, $s('invoice_paid_on') . ': '
            . self::ltr($data['paid_date'], $rtl), 0, 1, $rtl ? 'R' : 'L');
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

    /**
     * Keep a left-to-right value readable inside right-to-left text.
     *
     * Left alone, the bidi algorithm reorders the runs either side of the neutral
     * hyphens in something like INV-2026-0000022, which renders as
     * 2026-0000022-INV — the same characters in the wrong order, which is worse
     * than useless on a number somebody has to quote back to support.
     *
     * The fix is to mark the value as a self-contained left-to-right run. This
     * uses the older embedding controls (U+202A / U+202C) rather than the newer
     * isolates: TCPDF implements the pre-6.3 bidi algorithm and does not
     * recognise LRI/PDI.
     *
     * Strings that already contain right-to-left characters are left alone —
     * forcing those to LTR would break the very text this protects.
     */
    private static function ltr(string $text, bool $rtl): string {
        if (!$rtl || $text === '') {
            return $text;
        }

        // Hebrew, Arabic, Arabic Supplement/Extended and the presentation forms.
        if (preg_match('/[\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text)) {
            return $text;
        }

        return "\u{202A}" . $text . "\u{202C}";
    }
}
