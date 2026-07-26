// Parses Moodle {mlang xx}content{mlang} markup.
// Moodle course strings embed multiple translations in one field using this format.
// Falls back to the first available language if the requested one isn't present,
// and returns the raw string unchanged when no mlang markers exist.

const MLANG_PATTERN = /\{mlang\s+(\w+)\}([\s\S]*?)\{mlang\}/g;

export function parseMlang(text: string, lang: "ar" | "en"): string {
  if (!text || !text.includes("{mlang")) return text;

  const matches = [...text.matchAll(MLANG_PATTERN)];
  if (matches.length === 0) return text;

  const target = matches.find((m) => m[1] === lang);
  if (target) return target[2].trim();

  return matches[0][2].trim();
}
