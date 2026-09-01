<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Choosing a PDF font that can actually draw the text it is given.
 *
 * A certificate carrying an Arabic name printed in Times comes out as a row of
 * empty boxes. That is not an encoding fault and not a right-to-left fault -
 * TCPDF shapes and reorders Arabic correctly on its own. It is simply that the
 * five PostScript core fonts (courier, helvetica, times, symbol, zapfdingbats)
 * carry 256 glyphs each and none of them is an Arabic letter, so every letter
 * falls back to .notdef. Of the fonts Moodle bundles only freeserif and freemono
 * carry Arabic; freesans, despite the name, ships a Latin-only subset.
 *
 * The font is chosen per element in the certificate designer, by whoever laid the
 * template out - usually looking at English sample data, where every font works.
 * The failure only appears later, on a real learner with an Arabic name, on a PDF
 * nobody reviews. So the choice is corrected at render time instead of trusted.
 *
 * Nothing here is hard-coded about which fonts can do what: the answer is read
 * out of the font's own width map, which is the same thing TCPDF consults when
 * it draws. A better Arabic font dropped into moodledata/fonts is picked up by
 * the same test with no change here.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_fonts {

    /**
     * Families to fall back to, best first.
     *
     * Only used when the configured font cannot draw the text. Each is verified
     * against the font files actually present before being handed back, so a site
     * missing one of these is not a problem - it moves to the next.
     */
    const FALLBACKS = ['freeserif', 'freemono', 'dejavusans', 'aealarabiya'];

    /** @var array<string,bool> covers_arabic() results, per process. */
    protected static $covers = [];

    /**
     * Does this text contain Arabic script?
     *
     * Covers the Arabic block, the supplement, and the two presentation-forms
     * blocks - the last two because text that has already been shaped elsewhere
     * should be recognised too.
     *
     * @param string $text
     * @return bool
     */
    public static function has_arabic(string $text): bool {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    /**
     * Can this font draw Arabic?
     *
     * Probed rather than assumed: the font's own width map is loaded and checked
     * for a representative spread - three base letters and the initial, medial and
     * final forms TCPDF substitutes in when it shapes a word. A font that has the
     * base letters but not the contextual forms would still print boxes mid-word,
     * so both are required.
     *
     * @param string $font font file name without extension, e.g. 'freeserif'
     * @param string|null $fontpath directory to look in; defaults to the one TCPDF
     *        is using this request. Needed by the installer CLI, which has to check
     *        a font it has just written into a directory TCPDF will only start
     *        reading on the next request.
     * @return bool
     */
    public static function covers_arabic(string $font, ?string $fontpath = null): bool {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        $fontpath = $fontpath ?? \TCPDF_FONTS::_getfontpath();
        $cachekey = $fontpath . $font;

        if (isset(self::$covers[$cachekey])) {
            return self::$covers[$cachekey];
        }

        $file = $fontpath . $font . '.php';
        if (!is_readable($file)) {
            return self::$covers[$cachekey] = false;
        }

        $cw = null;
        // The bundled font definitions emit notices for includes of their own that
        // are not shipped; get_fonts() in mod_customcert suppresses them the same way.
        @include($file);

        if (!is_array($cw)) {
            return self::$covers[$cachekey] = false;
        }

        // Beh, meem, noon; then their initial, medial and final presentation forms.
        foreach ([0x0628, 0x0645, 0x0646, 0xFE91, 0xFEE4, 0xFEE6] as $codepoint) {
            if (!isset($cw[$codepoint])) {
                return self::$covers[$cachekey] = false;
            }
        }

        return self::$covers[$cachekey] = true;
    }

    /**
     * The font to draw this text in.
     *
     * Returns the configured font untouched unless it would fail: Latin text is
     * never redirected, and neither is Arabic in a font that can draw it. Only a
     * genuine mismatch is substituted, and if no installed font can do better the
     * original is returned rather than something arbitrary - a broken certificate
     * is bad, an unpredictable one is worse.
     *
     * The style is carried over where it can be. mod_customcert stores weight and
     * slant in the font name itself ('timesbi' = Times bold italic), so a
     * substitute has to bring the same suffix or the certificate silently loses
     * its bold. Where it cannot, the style is dropped rather than kept: every face
     * of a family is a separate font file with its own glyph set, and coverage
     * does not follow the family. Free Serif is the case in point - the regular
     * and bold faces carry Arabic, the italic and bold-italic faces do not. An
     * upright Arabic name is a compromise; an italic one made of empty boxes is
     * not a name at all.
     *
     * @param string|null $font the configured font, e.g. 'timesb'
     * @param string $text the content about to be drawn
     * @return string|null a font that can draw $text, or $font unchanged
     */
    public static function for_text(?string $font, string $text): ?string {
        if ($font === null || $font === '' || !self::has_arabic($text)) {
            return $font;
        }

        // Asked of the exact face, never the family: 'freeserifi' is not covered
        // by 'freeserif' being covered.
        if (self::covers_arabic($font)) {
            return $font;
        }

        [, $suffix] = self::split_style($font);

        foreach (self::FALLBACKS as $candidate) {
            foreach ([$candidate . $suffix, $candidate] as $face) {
                if (self::covers_arabic($face)) {
                    return $face;
                }
            }
        }

        return $font;
    }

    /**
     * Split a mod_customcert font name into family and style suffix.
     *
     * @param string $font e.g. 'timesbi'
     * @return array{0:string,1:string} e.g. ['times', 'bi']
     */
    protected static function split_style(string $font): array {
        foreach (['bi', 'b', 'i'] as $suffix) {
            // Only treat a trailing letter as a style when what is left is itself a
            // real family - 'zapfdingbats' ends in 's', not every tail is a style.
            $base = substr($font, 0, -strlen($suffix));
            if (substr($font, -strlen($suffix)) === $suffix && $base !== '' && self::font_exists($base)) {
                return [$base, $suffix];
            }
        }

        return [$font, ''];
    }

    /**
     * Is there a font definition file with this name?
     *
     * @param string $font
     * @return bool
     */
    protected static function font_exists(string $font): bool {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        return is_readable(\TCPDF_FONTS::_getfontpath() . $font . '.php');
    }
}
