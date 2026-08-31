<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Reading the bilingual values the platform stores in a single field.
 *
 * Course names, plan names and the invoice settings are written as
 * "{mlang en}Monthly Subscription{mlang}{mlang ar}اشتراك شهري{mlang}". Moodle's
 * own multilang filter uses a different syntax and filter_multilang2 is not
 * installed here, so nothing resolves this for us — and even if it were, filters
 * only run on formatted HTML output, never on a string on its way into a PDF.
 *
 * The invoice needs the language it is being rendered in rather than the
 * session's, which is why the language is a parameter instead of being read
 * from current_language().
 */
class multilang {

    /**
     * Resolve a possibly-bilingual value to plain text.
     *
     * A value with no {mlang} markup comes back untouched, so this is safe to
     * apply to anything.
     *
     * @param string|null $text Stored value.
     * @param string $lang Language to render, or '' for the current one.
     * @return string
     */
    public static function resolve(?string $text, string $lang = ''): string {
        $text = (string) $text;
        if (stripos($text, '{mlang') === false) {
            return $text;
        }

        $blocks = [];
        if (preg_match_all('/\{\s*mlang\s+([^}]+)\s*\}(.*?)\{\s*mlang\s*\}/is',
                $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                // One block may be tagged for several languages: {mlang en,other}.
                foreach (explode(',', strtolower($m[1])) as $code) {
                    $code = trim($code);
                    if ($code !== '' && !isset($blocks[$code])) {
                        $blocks[$code] = trim($m[2]);
                    }
                }
            }
        }

        if (!$blocks) {
            // Markup we do not recognise. Returning the raw value keeps the
            // tags visible, which is at least a readable symptom.
            return $text;
        }

        $lang = strtolower($lang !== '' ? $lang : current_language());
        foreach ($blocks as $code => $value) {
            // ar matches ar_sa and the other way round, the way Moodle's own
            // language packs are named.
            if ($code === $lang || strpos($code, $lang) === 0 || strpos($lang, $code) === 0) {
                return $value;
            }
        }

        // Nothing in the asked-for language: an explicit "other" block, then
        // English, then whatever was written first — anything but an empty line
        // on an invoice.
        return $blocks['other'] ?? $blocks['en'] ?? reset($blocks);
    }
}
