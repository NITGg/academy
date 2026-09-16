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
 * button over it and the film is not fetched until the button is pressed - a
 * hero video that starts downloading on page load is what makes the page slow
 * for the visitors who never press play. The facade is an ordinary link to the
 * file, so without this module the click opens it in a new tab; with it, the
 * site's own player takes the picture's place and starts, with the picture as
 * its poster.
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
    const picture = poster.querySelector('img');

    const player = document.createElement('video');
    player.src = embed;
    player.controls = true;
    player.autoplay = true;
    player.playsInline = true;
    player.setAttribute('aria-label', poster.dataset.title || '');
    if (picture && picture.src) {
        player.poster = picture.src;
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
