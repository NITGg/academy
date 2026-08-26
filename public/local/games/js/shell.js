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

/**
 * The runtime every game in the corner shares.
 *
 * It owns the parts that must behave the same way in all 24 games - the HUD,
 * the start and end cards, sound, the score bookkeeping and saving the round -
 * so a game file only has to describe how its own round is played.
 *
 * A game registers itself:
 *
 *   LocalGames.register('math-race', function (api) {
 *       return {
 *           start: function () { ... },   // build a round into api.stage
 *           stop:  function () { ... }    // optional: clear timers/animation
 *       };
 *   });
 *
 * @module     local_games/shell
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    var ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    var factories = {};
    var booted = false;

    /**
     * Sound: short tones for feedback, and the browser's own voice for text.
     *
     * Everything here is best-effort. A device with no Web Audio, no voices, or
     * a blocked autoplay policy just stays quiet - a silent game is still a
     * playable game.
     */
    var audio = {
        enabled: true,
        ctx: null,

        context: function () {
            if (!this.ctx) {
                var Ctor = window.AudioContext || window.webkitAudioContext;
                if (!Ctor) {
                    return null;
                }
                try {
                    this.ctx = new Ctor();
                } catch (e) {
                    return null;
                }
            }
            // Browsers start the context suspended until a gesture; every call
            // here follows a tap, so this is the moment to wake it.
            if (this.ctx.state === 'suspended' && this.ctx.resume) {
                this.ctx.resume();
            }
            return this.ctx;
        },

        /**
         * One short tone.
         *
         * @param {number} freq frequency in Hz
         * @param {number} duration seconds
         * @param {string} type oscillator type
         */
        tone: function (freq, duration, type) {
            if (!this.enabled) {
                return;
            }
            var ctx = this.context();
            if (!ctx) {
                return;
            }
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = type || 'sine';
            osc.frequency.value = freq;
            // A tiny fade out; a square-edged stop clicks.
            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + duration);
        },

        /** Rising two-note chime: that was right. */
        ding: function () {
            this.tone(660, 0.12);
            var self = this;
            window.setTimeout(function () {
                self.tone(880, 0.16);
            }, 110);
        },

        /** Low, soft note: not right - deliberately not a harsh buzzer. */
        buzz: function () {
            this.tone(220, 0.18, 'triangle');
        },

        /** Little fanfare for the end of a round. */
        fanfare: function () {
            var notes = [523, 659, 784, 1046];
            var self = this;
            notes.forEach(function (freq, i) {
                window.setTimeout(function () {
                    self.tone(freq, 0.18);
                }, i * 130);
            });
        },

        /**
         * Read a line out loud.
         *
         * @param {string} text
         * @param {string} lang BCP-47 tag
         */
        say: function (text, lang) {
            if (!this.enabled || !window.speechSynthesis || !text) {
                return;
            }
            try {
                window.speechSynthesis.cancel();
                var utterance = new window.SpeechSynthesisUtterance(text);
                utterance.lang = lang || document.documentElement.lang || 'ar-EG';
                utterance.rate = 0.95;
                window.speechSynthesis.speak(utterance);
            } catch (e) {
                // No voices installed, or speech blocked. Stay quiet.
            }
        },

        silence: function () {
            if (window.speechSynthesis) {
                window.speechSynthesis.cancel();
            }
        }
    };

    /**
     * Build the object a game is handed.
     *
     * @param {Object} config the JSON blob from the page
     * @param {Object} dom cached elements
     * @return {Object}
     */
    function makeApi(config, dom) {
        var strings = config.strings || {};
        var lang = config.arabicdigits ? 'ar-EG' : (document.documentElement.lang || 'en');

        // Round bookkeeping. Reset by api.reset() before every round.
        var round = {correct: 0, wrong: 0, streak: 0, best: 0};

        var api = {
            stage: dom.stage,
            strings: strings,
            lang: lang,
            audio: audio,

            /**
             * A number as the child reads it: Arabic-Indic digits in Arabic.
             *
             * @param {number} n
             * @return {string}
             */
            fmt: function (n) {
                var text = String(n);
                if (!config.arabicdigits) {
                    return text;
                }
                return text.replace(/[0-9]/g, function (d) {
                    return ARABIC_DIGITS[Number(d)];
                });
            },

            /**
             * Random integer, both ends included.
             *
             * @param {number} min
             * @param {number} max
             * @return {number}
             */
            random: function (min, max) {
                return Math.floor(Math.random() * (max - min + 1)) + min;
            },

            /**
             * One item from an array.
             *
             * @param {Array} items
             * @return {*}
             */
            pick: function (items) {
                return items[Math.floor(Math.random() * items.length)];
            },

            /**
             * A shuffled copy.
             *
             * @param {Array} items
             * @return {Array}
             */
            shuffle: function (items) {
                var out = items.slice();
                for (var i = out.length - 1; i > 0; i--) {
                    var j = Math.floor(Math.random() * (i + 1));
                    var tmp = out[i];
                    out[i] = out[j];
                    out[j] = tmp;
                }
                return out;
            },

            say: function (text) {
                audio.say(text, lang);
            },

            /** Start a fresh round's counters. */
            reset: function () {
                round = {correct: 0, wrong: 0, streak: 0, best: 0};
                setHud(dom, 0, 0);
                hideLives(dom);
            },

            /**
             * The child got it right.
             *
             * @param {string} [message] what to show instead of the default praise
             */
            correct: function (message) {
                round.correct++;
                round.streak++;
                round.best = Math.max(round.best, round.streak);
                setHud(dom, round.correct, round.streak);
                audio.ding();
                flash(dom, 'ok', message || strings.correct);
            },

            /**
             * Not right. Never framed as a loss - the doc is explicit about it.
             *
             * @param {string} [message]
             */
            wrong: function (message) {
                round.wrong++;
                round.streak = 0;
                setHud(dom, round.correct, round.streak);
                audio.buzz();
                flash(dom, 'no', message || strings.wrong);
            },

            /**
             * Show the tries left, as hearts.
             *
             * @param {number} lives
             */
            setLives: function (lives) {
                var wrap = dom.root.querySelector('[data-hud="lives-wrap"]');
                var value = dom.root.querySelector('[data-hud="lives"]');
                wrap.classList.remove('gc-hud__item--hidden');
                value.textContent = lives > 0 ? new Array(lives + 1).join('❤️') : '💔';
            },

            /** The round's running numbers, should a game want them. */
            stats: function () {
                return {correct: round.correct, wrong: round.wrong, streak: round.best};
            },

            /**
             * End the round, save it, and show the end card.
             *
             * @param {number} [score] the game's own score; defaults to correct answers
             */
            finish: function (score) {
                finishRound(config, dom, round, typeof score === 'number' ? score : round.correct);
            }
        };

        return api;
    }

    /**
     * Write the two HUD numbers.
     *
     * @param {Object} dom
     * @param {number} score
     * @param {number} streak
     */
    function setHud(dom, score, streak) {
        dom.root.querySelector('[data-hud="score"]').textContent = String(score);
        dom.root.querySelector('[data-hud="streak"]').textContent = String(streak);
    }

    /**
     * Hide the lives slot again between rounds.
     *
     * @param {Object} dom
     */
    function hideLives(dom) {
        dom.root.querySelector('[data-hud="lives-wrap"]').classList.add('gc-hud__item--hidden');
    }

    /**
     * A short message floating over the stage.
     *
     * @param {Object} dom
     * @param {string} kind 'ok' or 'no'
     * @param {string} text
     */
    function flash(dom, kind, text) {
        if (!text) {
            return;
        }
        var el = document.createElement('div');
        el.className = 'gc-flash gc-flash--' + kind;
        el.textContent = text;
        dom.stage.appendChild(el);
        window.setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 900);
    }

    /**
     * Save the round and show what it earned.
     *
     * @param {Object} config
     * @param {Object} dom
     * @param {Object} round
     * @param {number} score
     */
    function finishRound(config, dom, round, score) {
        var strings = config.strings || {};

        audio.silence();
        audio.fanfare();

        var end = dom.root.querySelector('[data-role="end"]');
        var title = end.querySelector('[data-role="end-title"]');
        var text = end.querySelector('[data-role="end-text"]');
        var badges = end.querySelector('[data-role="end-badges"]');
        var again = end.querySelector('[data-action="again"]');

        title.textContent = strings.roundover || '';
        text.textContent = strings.saving || '';
        badges.innerHTML = '';
        again.textContent = strings.playagain || '';
        end.classList.remove('gc-overlay--hidden');

        submit(config, round, score).then(function (result) {
            text.textContent = (strings.yougot || '{$a}').replace('{$a}', String(round.correct));

            (result.newbadges || []).forEach(function (badge) {
                var el = document.createElement('div');
                el.className = 'gc-newbadge';
                el.innerHTML = '<span class="gc-newbadge__icon" aria-hidden="true">🏅</span>';
                var name = document.createElement('span');
                name.className = 'gc-newbadge__name';
                name.textContent = (strings.newbadge || '') + ' ' + badge.name;
                el.appendChild(name);
                badges.appendChild(el);
            });

            if ((result.newbadges || []).length) {
                audio.say(strings.newbadge + ' ' + result.newbadges[0].name, config.arabicdigits ? 'ar-EG' : 'en');
            }
        }).catch(function () {
            // The round still happened; only the saving failed.
            text.textContent = strings.savefailed || '';
        });
    }

    /**
     * Post the round to local_games_submit_result.
     *
     * @param {Object} config
     * @param {Object} round
     * @param {number} score
     * @return {Promise<Object>}
     */
    function submit(config, round, score) {
        var url = config.wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(config.sesskey)
            + '&info=local_games_submit_result';

        return window.fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify([{
                index: 0,
                methodname: 'local_games_submit_result',
                args: {
                    gameid: config.gameid,
                    correct: round.correct,
                    wrong: round.wrong,
                    streak: round.best,
                    score: score
                }
            }])
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('http ' + response.status);
            }
            return response.json();
        }).then(function (payload) {
            var first = payload && payload[0];
            if (!first || first.error) {
                throw new Error('service error');
            }
            return first.data;
        });
    }

    /**
     * Wire the page up and hand the stage to the registered game.
     */
    function boot() {
        if (booted) {
            return;
        }

        var root = document.querySelector('.gc-play');
        if (!root) {
            return;
        }

        var confignode = root.querySelector('[data-role="config"]');
        if (!confignode) {
            return;
        }

        var config;
        try {
            config = JSON.parse(confignode.textContent);
        } catch (e) {
            return;
        }

        var factory = factories[config.gameid];
        if (!factory) {
            // The game file has not registered yet; boot() runs again when it does.
            return;
        }

        booted = true;

        var dom = {
            root: root,
            stage: root.querySelector('[data-role="stage"]'),
            start: root.querySelector('[data-role="start"]'),
            end: root.querySelector('[data-role="end"]')
        };

        var api = makeApi(config, dom);
        var game = factory(api);

        var play = function () {
            dom.start.classList.add('gc-overlay--hidden');
            dom.end.classList.add('gc-overlay--hidden');
            dom.stage.innerHTML = '';
            api.reset();
            if (game.stop) {
                game.stop();
            }
            game.start();
        };

        root.querySelector('[data-action="start"]').addEventListener('click', play);
        dom.end.querySelector('[data-action="again"]').addEventListener('click', play);

        var soundbutton = root.querySelector('[data-action="sound"]');
        soundbutton.addEventListener('click', function () {
            audio.enabled = !audio.enabled;
            if (!audio.enabled) {
                audio.silence();
            }
            var label = audio.enabled ? config.strings.sound_on : config.strings.sound_off;
            soundbutton.setAttribute('aria-pressed', audio.enabled ? 'true' : 'false');
            soundbutton.setAttribute('title', label);
            soundbutton.querySelector('.sr-only').textContent = label;
            soundbutton.firstElementChild.textContent = audio.enabled ? '🔊' : '🔇';
        });

        // Leaving mid-round must not leave a voice talking to an empty room.
        window.addEventListener('pagehide', function () {
            audio.silence();
            if (game.stop) {
                game.stop();
            }
        });
    }

    window.LocalGames = {

        /**
         * A game file announces itself here.
         *
         * @param {string} gameid registry slug
         * @param {Function} factory receives the api, returns {start, stop}
         */
        register: function (gameid, factory) {
            factories[gameid] = factory;
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        }
    };
}());
