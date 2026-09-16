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
 * The About page's video facade.
 *
 * When the page has both a picture and a film, the picture is drawn with a play
 * button over it and the player is not loaded until the button is pressed - a
 * YouTube iframe alone pulls in half a megabyte of script before anyone has
 * decided to watch. The facade is an ordinary link to the film, so without this
 * module the click opens it in a new tab; with it, the player takes the picture's
 * place and starts.
 *
 * @module     local_profilefields/aboutpage
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Swap the poster for the player.
 *
 * @param {HTMLAnchorElement} poster The facade link, carrying data-embed and data-provider.
 * @return {void}
 */
const play = (poster) => {
    const embed = poster.dataset.embed || '';
    if (!embed) {
        return;
    }

    const frame = poster.closest('[data-about-media]') || poster.parentElement;
    let player;

    if (poster.dataset.provider === 'file') {
        player = document.createElement('video');
        player.src = embed;
        player.controls = true;
        player.autoplay = true;
        player.playsInline = true;
    } else {
        player = document.createElement('iframe');
        player.src = embed + (embed.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1';
        player.title = poster.dataset.title || '';
        player.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        player.referrerPolicy = 'strict-origin-when-cross-origin';
        player.allowFullscreen = true;
    }

    frame.classList.add('nit-about-media--playing');
    frame.replaceChildren(player);
    player.focus();
};

/**
 * Wire every facade on the page.
 *
 * @return {void}
 */
export const init = () => {
    document.querySelectorAll('[data-about-play]').forEach((poster) => {
        poster.addEventListener('click', (event) => {
            event.preventDefault();
            play(poster);
        });
    });
};
