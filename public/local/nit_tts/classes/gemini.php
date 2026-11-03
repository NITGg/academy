<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_nit_tts;

/**
 * Turns text into speech through the Gemini API.
 *
 * Core's AI subsystem has no text-to-speech action, so this calls Gemini
 * directly. Gemini answers with raw 16-bit mono PCM at 24 kHz, which no browser
 * plays on its own; it is wrapped in a WAV header before it leaves here.
 *
 * @package    local_nit_tts
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini {

    /** @var string Gemini REST endpoint, the model name goes in the middle. */
    const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** @var int Sample rate of the PCM Gemini returns. */
    const SAMPLE_RATE = 24000;

    /** @var array Gemini's prebuilt voices, name => gender. */
    const VOICES = [
        'Kore' => 'female', 'Zephyr' => 'female', 'Leda' => 'female', 'Aoede' => 'female',
        'Callirrhoe' => 'female', 'Autonoe' => 'female', 'Despina' => 'female', 'Erinome' => 'female',
        'Laomedeia' => 'female', 'Achernar' => 'female', 'Gacrux' => 'female', 'Pulcherrima' => 'female',
        'Vindemiatrix' => 'female', 'Sulafat' => 'female',
        'Charon' => 'male', 'Puck' => 'male', 'Fenrir' => 'male', 'Orus' => 'male',
        'Enceladus' => 'male', 'Iapetus' => 'male', 'Umbriel' => 'male', 'Algieba' => 'male',
        'Algenib' => 'male', 'Rasalgethi' => 'male', 'Alnilam' => 'male', 'Schedar' => 'male',
        'Achird' => 'male', 'Zubenelgenubi' => 'male', 'Sadachbia' => 'male', 'Sadaltager' => 'male',
    ];

    /**
     * Speak a text and return it as a WAV file.
     *
     * @param string $text what to say
     * @param string $voice one of self::VOICES
     * @param string $style optional delivery instructions, e.g. "read calmly"
     * @return string WAV file contents
     * @throws \moodle_exception when no key is set or Gemini refuses
     */
    public static function synthesize(string $text, string $voice, string $style = ''): string {
        $apikey = trim((string) get_config('local_nit_tts', 'apikey'));
        if ($apikey === '') {
            throw new \moodle_exception('err_nokey', 'local_nit_tts');
        }
        $model = get_config('local_nit_tts', 'model') ?: 'gemini-2.5-flash-preview-tts';

        // Gemini takes delivery instructions as part of the prompt itself.
        $prompt = $style === '' ? $text : $style . ":\n" . $text;
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]],
                ],
            ],
        ];

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json', 'x-goog-api-key: ' . $apikey]);
        $curl->setopt(['CURLOPT_TIMEOUT' => 180, 'CURLOPT_CONNECTTIMEOUT' => 15]);
        $body = $curl->post(sprintf(self::ENDPOINT, rawurlencode($model)), json_encode($payload));

        $code = $curl->get_info()['http_code'] ?? 0;
        $data = json_decode((string) $body, true);
        if ($code != 200) {
            $reason = $data['error']['message'] ?? ($curl->error ?: 'HTTP ' . $code);
            throw new \moodle_exception('err_api', 'local_nit_tts', '', $reason);
        }

        $audio = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? '';
        $pcm = base64_decode($audio, true);
        if ($pcm === false || $pcm === '') {
            $reason = $data['candidates'][0]['finishReason'] ?? 'no audio in response';
            throw new \moodle_exception('err_api', 'local_nit_tts', '', $reason);
        }
        return self::wav($pcm);
    }

    /**
     * Wrap 16-bit mono PCM in a WAV header.
     *
     * @param string $pcm raw samples
     * @return string
     */
    protected static function wav(string $pcm): string {
        $channels = 1;
        $bits = 16;
        $byterate = self::SAMPLE_RATE * $channels * $bits / 8;
        return 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVE'
            . 'fmt ' . pack('VvvVVvv', 16, 1, $channels, self::SAMPLE_RATE, $byterate, $channels * $bits / 8, $bits)
            . 'data' . pack('V', strlen($pcm)) . $pcm;
    }
}
