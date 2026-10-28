/**
 * Player adapter for H5P activities (an Interactive Video, typically).
 *
 * The H5P content runs in an iframe (h5p/embed.php), and Interactive Video puts
 * itself in a second iframe inside that one. Both come from this site, so the
 * adapter can reach into them and find the H5P instance that owns a video.
 *
 * Same contract as player_vdocipher.js: getCurrentTime(), getDuration(), seekTo().
 *
 * @module     local_nit_ai/player_h5p
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function () {
    'use strict';

    window.NITAI = window.NITAI || {};
    window.NITAI.players = window.NITAI.players || {};

    /** H5P builds its content after its own scripts load; give it time. */
    var WAIT_MS = 20000;

    function whole(n) {
        return (typeof n === 'number' && !isNaN(n)) ? Math.floor(n) : -1;
    }

    /**
     * Search a window and the same-site frames inside it for an H5P instance
     * with a video.
     *
     * @param {Window} win
     * @param {number} depth guard against odd nesting
     * @return {Object|null}
     */
    function findInstance(win, depth) {
        if (!win || depth > 4) {
            return null;
        }
        try {
            var instances = (win.H5P && win.H5P.instances) || [];
            for (var i = 0; i < instances.length; i++) {
                var video = instances[i].video;
                if (video && typeof video.getCurrentTime === 'function') {
                    return instances[i];
                }
            }
            var frames = win.document.querySelectorAll('iframe');
            for (var j = 0; j < frames.length; j++) {
                var found = findInstance(frames[j].contentWindow, depth + 1);
                if (found) {
                    return found;
                }
            }
        } catch (e) {
            // A frame from another site: nothing to find in it.
            return null;
        }
        return null;
    }

    window.NITAI.players.h5p = function () {
        return new Promise(function (resolve) {
            var started = Date.now();
            (function attempt() {
                var instance = findInstance(window, 0);
                if (instance) {
                    var video = instance.video;
                    resolve({
                        getCurrentTime: function () {
                            return whole(video.getCurrentTime());
                        },
                        getDuration: function () {
                            return Math.max(0, whole(video.getDuration()));
                        },
                        seekTo: function (seconds) {
                            // Interactive Video's own seek keeps its timeline and
                            // interactions in step; a bare video falls back to the player.
                            if (typeof instance.seek === 'function') {
                                instance.seek(seconds);
                            } else {
                                video.seek(seconds);
                            }
                            video.play();
                        }
                    });
                    return;
                }
                if (Date.now() - started > WAIT_MS) {
                    resolve(null);
                    return;
                }
                setTimeout(attempt, 500);
            })();
        });
    };
}());
