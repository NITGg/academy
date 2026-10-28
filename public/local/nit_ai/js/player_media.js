/**
 * Player adapter for Moodle's own media player, used by File and URL activities.
 *
 * Moodle embeds a video file and a YouTube link alike through Video.js
 * (media_videojs), so the adapter talks to Video.js first. Where a site has
 * switched that off it falls back to what is left on the page: a plain
 * <video>/<audio>, or a raw YouTube embed driven through YouTube's IFrame API.
 *
 * Same contract as player_vdocipher.js: getCurrentTime(), getDuration(), seekTo().
 *
 * @module     local_nit_ai/player_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function () {
    'use strict';

    window.NITAI = window.NITAI || {};
    window.NITAI.players = window.NITAI.players || {};

    /** How long to wait for the player to appear: Video.js builds it after load. */
    var WAIT_MS = 15000;

    function whole(n) {
        return (typeof n === 'number' && !isNaN(n)) ? Math.floor(n) : -1;
    }

    function play(result) {
        // Autoplay can be refused; the seek still happened.
        if (result && result.catch) {
            result.catch(function () {
                return null;
            });
        }
    }

    /**
     * Retry a lookup until it finds something or time runs out.
     *
     * @param {Function} lookup returns a value, or null to try again
     * @return {Promise}
     */
    function poll(lookup) {
        return new Promise(function (resolve) {
            var started = Date.now();
            (function attempt() {
                var found = null;
                try {
                    found = lookup();
                } catch (e) {
                    found = null;
                }
                if (found || Date.now() - started > WAIT_MS) {
                    resolve(found || null);
                    return;
                }
                setTimeout(attempt, 300);
            })();
        });
    }

    /**
     * The Video.js player on the page, through Moodle's AMD loader.
     *
     * @return {Promise} resolves with the player, or null
     */
    function videojsPlayer() {
        if (!document.querySelector('.video-js') || typeof window.require !== 'function') {
            return Promise.resolve(null);
        }
        return new Promise(function (resolve) {
            window.require(['media_videojs/video-lazy'], function (videojs) {
                poll(function () {
                    var players = videojs.getPlayers ? videojs.getPlayers() : {};
                    var ids = Object.keys(players).filter(function (id) {
                        return players[id];
                    });
                    return ids.length ? players[ids[0]] : null;
                }).then(resolve);
            }, function () {
                resolve(null);
            });
        });
    }

    function fromVideojs(player) {
        return {
            getCurrentTime: function () {
                return whole(player.currentTime());
            },
            getDuration: function () {
                return Math.max(0, whole(player.duration()));
            },
            seekTo: function (seconds) {
                player.currentTime(seconds);
                play(player.play());
            }
        };
    }

    function fromElement(el) {
        return {
            getCurrentTime: function () {
                return whole(el.currentTime);
            },
            getDuration: function () {
                return Math.max(0, whole(el.duration));
            },
            seekTo: function (seconds) {
                el.currentTime = seconds;
                play(el.play());
            }
        };
    }

    /**
     * A YouTube embed outside Video.js: attach YouTube's IFrame API to it.
     *
     * @param {HTMLIFrameElement} iframe
     * @return {Promise} resolves with the adapter, or null
     */
    function fromYoutubeIframe(iframe) {
        // The API can only steer an embed that was loaded with enablejsapi=1.
        if (!/[?&]enablejsapi=1/.test(iframe.src)) {
            iframe.src += (iframe.src.indexOf('?') === -1 ? '?' : '&') + 'enablejsapi=1&origin=' +
                encodeURIComponent(window.location.origin);
        }
        return new Promise(function (resolve) {
            var timer = setTimeout(function () {
                resolve(null);
            }, WAIT_MS);

            function attach() {
                var player = new window.YT.Player(iframe, {
                    events: {
                        onReady: function () {
                            clearTimeout(timer);
                            resolve({
                                getCurrentTime: function () {
                                    return whole(player.getCurrentTime());
                                },
                                getDuration: function () {
                                    return Math.max(0, whole(player.getDuration()));
                                },
                                seekTo: function (seconds) {
                                    player.seekTo(seconds, true);
                                    player.playVideo();
                                }
                            });
                        }
                    }
                });
            }

            if (window.YT && window.YT.Player) {
                attach();
                return;
            }
            var previous = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () {
                if (typeof previous === 'function') {
                    previous();
                }
                attach();
            };
            var script = document.createElement('script');
            script.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(script);
        });
    }

    window.NITAI.players.media = function () {
        return videojsPlayer().then(function (player) {
            if (player) {
                return fromVideojs(player);
            }
            return poll(function () {
                return document.querySelector('#region-main video, #region-main audio, ' +
                    '#region-main iframe[src*="youtube.com/embed"], #region-main iframe[src*="youtube-nocookie.com/embed"]');
            }).then(function (el) {
                if (!el) {
                    return null;
                }
                return el.tagName === 'IFRAME' ? fromYoutubeIframe(el) : fromElement(el);
            });
        }).catch(function (e) {
            window.console.warn('nit_ai: media player unavailable', e);
            return null;
        });
    };
}());
