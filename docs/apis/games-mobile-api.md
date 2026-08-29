# Games Corner — mobile API

The **Games Corner** (`local_games`) is a set of short learning games. On the
web it is two pages: a hub listing every game (`/local/games/index.php`) and a
play page that runs one of them (`/local/games/play.php?id=<slug>`).

This document covers the four web-service functions an app needs to build the
same thing natively — the catalogue, one game's start screen, the material the
games are made of, and saving a finished round.

All four are registered against the **Moodle Mobile service**
(`MOODLE_OFFICIAL_MOBILE_SERVICE`), so any valid mobile `wstoken` can call them.
No extra service setup is needed.

| Function | Type | Purpose |
|----------|------|---------|
| `local_games_get_games` | read | The whole catalogue + this user's totals, progress and badges |
| `local_games_get_game` | read | One game: start-screen text, its badges, this user's standing |
| `local_games_get_content` | read | The shared banks (words, questions, …) + every game string |
| `local_games_submit_result` | write | Save one finished round, get the new totals and any badge won |

---

## 0. Calling convention

Standard Moodle REST. Every call is:

```
POST {site}/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken={token}&wsfunction={function}&moodlewsrestformat=json&<params>
```

- `{site}` — the Moodle site root (locally: `http://localhost:8081`)
- `{token}` — the user's mobile web-service token

Every function requires the **`local/games:play`** capability, which is granted
to the `user` archetype — i.e. every logged-in account has it. Nothing here is
teacher-only.

All text comes back **already in the user's language**. Send
`&moodlewssettinglang=ar` (or set the user's language) to switch; the banks,
the game names and every message follow it.

> **`errorcode: accessexception`** means the token's service does not list the
> function. These four are on the official mobile service; if you built a
> custom service, add them to it.

> **`errorcode: errorunknowngame`** means the `gameid` is not a slug the server
> knows, or names a game that is planned but not built yet. Only games with
> `live: true` can be opened or submitted against.

---

## 1. The catalogue

`local_games_get_games`

Everything the hub screen needs, in one call. No parameters.

**Example**

```
wsfunction=local_games_get_games
```

**Response**

```json
{
  "points": 340,
  "badges": 4,
  "plays": 27,
  "categories": [
    {
      "key": "numbers",
      "emoji": "🔢",
      "name": "Numbers",
      "note": "",
      "games": [
        {
          "id": "math-race",
          "emoji": "🔢",
          "name": "Math Race",
          "description": "Pick the right answer and keep the race going.",
          "level": 1,
          "live": true,
          "plays": 6,
          "points": 71,
          "bestscore": 14,
          "beststreak": 9
        }
      ]
    }
  ],
  "badgeshelf": [
    {
      "key": "fast-calculator",
      "gameid": "math-race",
      "name": "Sharp Calculator",
      "hint": "10 correct in a row",
      "earned": true
    }
  ]
}
```

**Fields**

| Field | Notes |
|---|---|
| `points`, `badges`, `plays` | Lifetime totals across the whole corner |
| `categories[].key` | `numbers`, `letters`, `quiz`, `memory`, `motion` — the hub's sections, in display order |
| `categories[].note` | An extra line under the section heading. **Empty string** when the section has none — do not render an empty row |
| `games[].level` | 1–3. The web hub draws this as one to three stars; draw it however you like |
| `games[].live` | `false` = on the plan, not built. Show it as "coming soon" and do not let it open |
| `games[].plays / points / bestscore / beststreak` | This user's history in that game. All `0` if never played |
| `badgeshelf` | Every badge the corner can award, earned or not. Order follows the catalogue |

Games are returned inside their category, in the order the corner wants them
shown. Do not re-sort.

---

## 2. One game

`local_games_get_game`

What the start screen shows before a round begins, plus this user's standing in
that game.

**Params**

| Name | Type | Notes |
|------|------|-------|
| `gameid` | string | Registry slug, e.g. `math-race`. Dashes are part of the slug |

**Example**

```
wsfunction=local_games_get_game&gameid=space-quiz
```

**Response**

```json
{
  "id": "space-quiz",
  "emoji": "🚀",
  "category": "quiz",
  "level": 2,
  "name": "Space Trip",
  "description": "Answer to fly. Every right answer moves the rocket.",
  "readytitle": "Ready for take-off?",
  "howto": "A question, three answers. Every right one moves the rocket a step closer to the planet.",
  "plays": 3,
  "points": 42,
  "bestscore": 21,
  "beststreak": 11,
  "badges": [
    {
      "key": "astronaut",
      "name": "Astronaut",
      "hint": "Reach the last planet",
      "earned": false,
      "streak": -1,
      "correct": -1,
      "maxwrong": -1,
      "goal": 1
    }
  ]
}
```

**The badge rule.** Each badge carries the four thresholds the server checks,
so an app can show progress rather than just a locked padlock. They are
**ANDed** — all the ones that apply must be met by a single round:

| Threshold | Met when |
|---|---|
| `streak` | the round's longest run of correct answers ≥ this |
| `correct` | correct answers in the round ≥ this |
| `maxwrong` | wrong answers in the round ≤ this |
| `goal` | the game's own goal count ≥ this (see §4) |

**`-1` means the threshold is not part of this badge's rule** — it is not a
value to compare against. Zero could not say that, because `maxwrong: 0` ("no
mistakes at all") is a real and common rule.

Badges are awarded **once, for life**. A round that meets the rule again
changes nothing.

---

## 3. What the games are made of

`local_games_get_content`

The word bank, the question bank and the rest live in the language pack, not in
code — translating the corner also translates the material the games are built
from. They are **shared**: six games read the same questions, four read the
same picture words. So they travel once, not with every game the child opens.

**Params**

| Name | Type | Notes |
|------|------|-------|
| `banks[0]`, `banks[1]`, … | string | Optional. Any of `words`, `shopitems`, `wordlist`, `quiz`, `truefalse`, `whoami`, `colours`. Omit for all of them |

**Example**

```
wsfunction=local_games_get_content&banks[0]=quiz&banks[1]=words
```

**Response**

```json
{
  "lang": "en",
  "arabicdigits": false,
  "revision": 1755600000,
  "strings": [
    {"key": "score", "value": "Score"},
    {"key": "streak", "value": "In a row"},
    {"key": "correct", "value": "Well done!"},
    {"key": "space_stage", "value": "Stage {$a}: to {$b}"}
  ],
  "words": [
    {"word": "apple", "emoji": "🍎", "clue": "A red fruit"}
  ],
  "shopitems": [],
  "wordlist": [],
  "quiz": [
    {
      "topic": "science",
      "question": "How many legs does a spider have?",
      "answer": "8",
      "wrong": ["6", "10", "4"]
    }
  ],
  "truefalse": [],
  "whoami": [],
  "colours": []
}
```

**A bank you did not ask for comes back as an empty array, not missing.** The
response shape never changes, so you can read every field without checking
what you requested.

**Caching.** `revision` moves whenever the site's language strings may have
changed. Store it with the banks and re-fetch only when the number you get back
differs. The banks are the biggest payload in this API and they change roughly
never.

**The `strings` bag** is a list of `{key, value}` pairs rather than an object,
because Moodle's REST format cannot describe an object with arbitrary keys.
Turn it into a dictionary on arrival. Keys are the language-pack keys with the
`js_` prefix removed — `js_space_stage` arrives as `space_stage`.

Placeholders inside a string are Moodle's: `{$a}`, and `{$b}` where a string
takes two. Substitute them yourself; **never build a sentence by joining
fragments**, because word order changes between languages.

`arabicdigits` is `true` when the interface language is Arabic. It means show
numbers in Arabic-Indic digits (`٠١٢٣٤٥٦٧٨٩`). The arithmetic itself always
runs on real numbers — this is presentation only.

**The banks, one by one**

| Bank | Shape | Used by |
|---|---|---|
| `words` | `{word, emoji, clue}` | the letters games, Memory Cards, the jigsaw |
| `shopitems` | `{emoji, name}` | Math Shop |
| `wordlist` | `["…"]` | Word Builder, to check whether something is a word |
| `quiz` | `{topic, question, answer, wrong[]}` | the six question games |
| `truefalse` | `{text, true, why}` | True or False |
| `whoami` | `{answer, emoji, clues[]}` | Who Am I — clues in the order they should be given |
| `colours` | `{name, hex}` | Colour Challenge |

For a quiz question, build the options by shuffling `answer` together with the
first three entries of `wrong` — three choices for a young child, four once
there are enough wrong answers to make a fourth meaningful. `wrong` may hold
one, two or three.

---

## 4. Saving a round

`local_games_submit_result`

Call this **once, when a round ends** — however it ends: the target reached,
the hearts gone, the board cleared. Not per answer.

**Params**

| Name | Type | Notes |
|------|------|-------|
| `gameid` | string | Registry slug |
| `correct` | int | Correct answers this round |
| `wrong` | int | Wrong answers this round |
| `streak` | int | The round's **longest** run of correct answers, not the final one |
| `score` | int | The round score as the game counts it |
| `goal` | int | How many times the game met its own goal. Optional, defaults to `0` |

**`goal` is the one that needs explaining.** Some games are not won by counting
right answers, and the server cannot know what winning means in each of them.
So the game says it:

| Game | What one `goal` is |
|---|---|
| `space-quiz` | reaching the last planet |
| `xo-quiz` | a match won (a round is three matches, so `goal` is 0–3) |
| `wheel` | a topic answered correctly (four topics) |
| `who-am-i` | an answer guessed from the first clue alone |
| `memory-cards` | the board cleared inside the flip budget |
| `puzzle` | the sixteen-piece board finished |
| `runner` | a stage finished with every heart intact |
| `color-challenge` | every colour on the board answered |

For every other game send `0`.

**Example**

```
wsfunction=local_games_submit_result&gameid=math-race&correct=14&wrong=2&streak=9&score=14&goal=0
```

**Response**

```json
{
  "points": 354,
  "badges": 5,
  "gamepoints": 85,
  "bestscore": 14,
  "newbadges": [
    {"key": "fast-calculator", "name": "Sharp Calculator"}
  ]
}
```

| Field | Notes |
|---|---|
| `points`, `badges` | The user's **new** lifetime totals, after this round |
| `gamepoints` | Lifetime points in this game |
| `bestscore` | Best single-round score in this game, after this round |
| `newbadges` | Badges won **by this round**. Usually empty — celebrate when it is not |

**What the server does with these numbers.** Points are not taken from the
client: lifetime points go up by `correct`, one per right answer, the same rule
for every game. `score` is only ever used as the game's own best-score. Every
value is clamped to 0–500, so a broken client cannot inflate a total. A round
that arrives mid-play is not rejected, it is simply clamped — a child should
never see an error over their own score.

---

## 5. Putting it together

A normal app session:

1. **Once per install / language change** — `local_games_get_content`. Store
   the banks and the `strings` dictionary against `revision`.
2. **Hub screen** — `local_games_get_games`. Re-fetch when returning to it, so
   points and badges stay current.
3. **Tapping a card** — `local_games_get_game` for the start screen. (Or skip
   it: everything except `readytitle` / `howto` / the badge rules is already in
   the catalogue response.)
4. **Play the round natively.** All the material is already on the device.
5. **Round ends** — `local_games_submit_result`, then show the new totals and
   anything in `newbadges`.

**The round counters.** Track four numbers while a round runs, and send them:

- `correct` — increment on every right answer
- `wrong` — increment on every wrong one
- `streak` — the **highest** value the running streak reached, not its final value
- `score` — usually the same as `correct`. They differ where an answer is worth
  more than a point: Word Builder pays by word length, so a long word scores
  more without being more than one correct answer.

**One thing worth copying from the web.** A wrong answer is never framed as a
loss — no red, no "you failed", no points taken away. The corner is a retention
feature for children; the tone is part of the product.

---

## 6. Errors

| `errorcode` | Meaning | What to do |
|---|---|---|
| `accessexception` | The function is not in the token's service | Use the official mobile service, or add these functions to yours |
| `errorunknowngame` | Unknown slug, or a game that is not built yet | Only open games with `live: true` |
| `invalidparameter` | A parameter is missing or the wrong type | Check `gameid` is the slug string, and the five counters are integers |
| `nopermissions` | The account lacks `local/games:play` | Check the capability is still granted to the `user` archetype |

A failed submit is not worth blocking the child on. Show the round's result
from the numbers you already have, and retry the save in the background.
