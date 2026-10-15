# Status — openDesk meeting notes plugin

Plan of record: [`00_master_plan.md`](./00_master_plan.md). Steps:
[`07_sprints.md`](./07_sprints.md). A walk or a measurement is a dated line
here, not a chat message.

## Steps

| Step | State | Notes |
|------|-------|-------|
| `MN-0` Spike and decisions | in progress | Bridge path verified 2026-10-04; Prosody HTTP on the in-cluster address verified 2026-10-05. Captions, Element embedding, 320 px still open. |
| `MN-1` Core prerequisites | not started | Dev MVP runs on 5.1.3 without them (see 2026-10-05). New item: plugin_data lookups must normalize keys like the writes do. |
| `MN-2` Plugin skeleton | **dev MVP** | Plugin `synascriber` 0.3.0: settings (share with participants, live captions), admin status, personal page (started and received), five locales. |
| `MN-3` Sessions | **dev MVP** | State machine with watchdog, segments (idempotent per speaker and window), roster from Prosody, the transcriber session API ([02 §5](./02_architecture.md#5-interfaces)) incl. `preview`. Unit tests still missing. |
| `MN-4` Prosody module | **dev MVP** | `mod_synascriber` 0.3.0: start/stop/state/health (22-character session ids), client starts refused, attendees (Keycloak `sub` + email from the Jitsi token) for everyone present while notes are on. No busted specs yet. |
| `MN-5` Transcriber plugin mode | **dev MVP** | synaplan `sidecars/synaplan-transcriber` (branch `feat/transcriber-live-captions`): session API, previews every 1.5 s for live captions, windows end at pauses (0.7 s), silent stretches dropped, 8 s hard cut as the limit; 32/32 node tests. Deployed from a git ref. |
| `MN-6` Transcript file | **dev MVP** | One Markdown copy per signed-in participant in their Sources › Generated (source `generated`, kind `document`, chosen folder), vectorized; speakers, participants, times. |
| `MN-7` Jitsi surface | **dev MVP** | Floating button, dialog, banner, outcome with file link; Keycloak PKCE silent sign-in; live caption strip for everyone, growing while someone speaks (first caption ~2.5 s after start). |
| `MN-8` Engine and quality | in progress | German Whisper on our GPU host with Silero VAD (no more phantom "Vielen Dank."); `stt` chart merged in synaplan-charts#51 (2026-10-13), VAD on by default; image `synaplan-stt` 0.1.0 (cpu, cuda) published from metadist/synaplan-stt. Synaplan's own server mode (`MN-8a`) still open. |
| `MN-9` v1.0 release | not started | |
| `MN-10` v1.1 | not started | |
| `MN-11` v1.2 | not started | |
| `MN-12` openDesk packaging | **installer on standard openDesk** | `deploy/opendesk/`: `./synascriber check/plan/install/verify/opendesk-env/uninstall`, chart `synascriber`, Synaplan from synaplan-charts `deployments/opendesk-addon`. Journey green on openDesk v1.19.1 + Synaplan 5.3.2 (2026-10-12). Open: the pinned synaplan-charts commit no longer resolves (2026-10-14), `STT=cpu` and `gpu` with the now published `synaplan-stt` image, published transcriber image, openDesk merge request. |

## Decisions

| Date | Decision |
|------|----------|
| 2026-10-04 | Plan written. D1–D12 proposed in [`README.md`](./README.md) §4, waiting for the product owner. |
| 2026-10-05 | Product owner: transcripts land for **all participants** in their Generated overview; pushing to OpenCloud / Nextcloud comes from there in a later release. D6 and D12 rewritten accordingly. |

## Log

**2026-10-14 — Architecture checked against synaplan and synaplan-charts.**
[`02_architecture.md`](./02_architecture.md) now describes what runs
(repositories, packages, requirements, interfaces, storage, ToDo) instead of
the plan of 2026-10-04. Found on the way:

- The installer's synaplan-charts pin `a14fa65` is no longer on any branch
  (synaplan-charts#53 was force-pushed). helmfile 1.1.7 with an empty cache
  fails with `reference is not a tree`, so `install`, `plan` and `uninstall`
  stop at the Helm step. Workaround: a local checkout of that commit
  (`git fetch origin <sha>`) as `SYNAPLAN_CHARTS_PATH`; renders all four
  releases. The fix waits for #53, whose new layout no longer has
  `deployments/opendesk-addon`.
- `ghcr.io/metadist/synaplan-stt:0.1.0-cpu` and `-cuda` are published, so
  `./synascriber check` no longer stops `STT=cpu|gpu`; not walked yet.
- Synaplan `main` (5.4.1) reads no `WHISPER_SERVER_URL` and has no public
  plugin routes, so the plugin's own `WhisperClient` and the owner's API key
  for the transcriber stay. The transcriber on `main` is still the pinned
  `09d72d3`.

**2026-10-12 — One installer for a standard openDesk (`MN-12`).** The dev
cluster was rebuilt from upstream openDesk **v1.19.1** (Jitsi chart 3.12.4,
images `stable-11248`; Nubus, Jitsi, Element, Collabora, Nextcloud) instead
of the edition used so far. On top of it, from a clean namespace, one
command installs everything:

```bash
cd deploy/opendesk && ./synascriber install
```

What it does, in order: Helm releases (synaplan-charts
`deployments/opendesk-addon`: MariaDB 11.8 for Synaplan, generated secrets,
Synaplan 5.3.2; this repo's chart `synascriber`: plugin files, transcriber
from a pinned synaplan commit, Prosody module), the Keycloak clients
`synaplan` and `synascriber`, the plugin for Synaplan's first local
administrator with the transcriber's API key and the settings, and openDesk's
Jitsi (`helm upgrade` of the deployed chart version: `head.html`, the silent
page, `mod_synascriber`, `custom-jicofo.conf`). It ends with `verify`.

| Run | Result |
|-----|--------|
| `check` on the fresh openDesk | found namespace, domain, Keycloak, Jitsi chart 3.12.4, ingress class, certificate issuer, Collabora by itself |
| first `install` | Synaplan ready in about 1 minute; every `verify` check green |
| journey (`tests/e2e/meeting-notes.mjs`, two people, 50 s German speech) | button 0.8 s after joining; banner and Jitsi's indicator at the second person at once; first caption after 2.4 s, 72 caption messages; both copies in Sources › Generated, correct speakers and text |
| `install` again | no Helm release changed, Jitsi "already current" (idempotent) |
| `uninstall`, then `install` | Jitsi back to openDesk's own values, Synaplan stays; reinstall green; journey again green (first caption after 1.4 s) |
| openDesk's own `helmfile apply` for `jitsi` with the generated `synascriber.yaml.gotmpl` in the environment | empty diff: openDesk renders exactly what the installer applied |

Found on the way:

- **openDesk removes unknown Keycloak clients.** Its bootstrap (`kcom`)
  deletes every client not declared in openDesk's values and recreates
  `functional.authentication.oidc.clients` on every run. The installer
  therefore writes `synascriber.yaml.gotmpl` (both clients, with the client
  secret as `existingSecret`, and the Jitsi values through
  `customization.release.jitsi`). After a bootstrap run the recreated clients
  match the installer's definition field by field; `verify` reports whether
  the file is in place.
- **Synaplan 5.3 needs `TIKA_BASE_URL`.** It is read without a default; the
  synaplan chart only set it with Tika on, so every request failed with
  Tika off. Fixed in the chart (always set, empty = off).
- **Jicofo reads the bridge token from a Secret.** `custom-jicofo.conf` uses
  HOCON `${?SYNASCRIBER_TRANSCRIBER_TOKEN}` from `extraEnvFrom`, so no secret
  sits in a ConfigMap or in the openDesk files. All references are optional,
  so Jitsi still starts if the settings outlive an uninstall.
- **Chart 3.12.4 has a proper Jicofo hook** (`jicofo.custom.configs`); the
  start-script workaround is only used for older charts.
- openDesk 1.19's LDAP leader elector (Python 3.13, strict X.509) rejects a
  cluster CA that has an extended key usage and no authority key identifier;
  the dev cluster pins the previous elector image. Not a synaScriber issue,
  noted for others on such clusters.

Open: Jitsi `stable-11248` shows its own toast "Transcription started · Also
record this meeting?" when notes start (offers recording although Jibri is
off). `STT=cpu|gpu` waits for the first `synaplan-stt` image; the transcriber
runs from source until its image is published (`MN-1c`).

**2026-10-11 — Step-by-step live captions; plugin on the transcriber
session API.** The dev cluster ran a transcriber copy that existed in no repository
(the MVP interface); synaplan `main` already spoke the session API of
[plan 02 §5.5](https://github.com/metadist/synaScriber/blob/6babe67590475885bdb6a1e387c83e02ba9ff4a6/docs/02_architecture.md#55-transcriber--plugin-bearer-token-cluster-internal-source).
The plugin (0.3.0) now implements it: `bind` with meeting-id check and
the caption switch, `audio` as multipart with `dropped` reasons and
idempotent segments, `events` (`connected` sets the time origin,
`stt-failure` counts gaps and the transcript says parts are missing,
`finished` writes the files), 22-character session ids (Prosody 0.3.0
accepts them). New: `preview`, recognised and never stored. The
transcriber (synaplan branch `feat/transcriber-live-captions`) previews the
window so far every 1.5 s while someone speaks and sends it as an interim
caption; the preview's speech end decides the window boundary (0.7 s of
silence ⇒ `cut: "pause"`), and 4 s without speech are dropped before they
become a window. `deploy.sh` ships the transcriber from a git ref, never
from the synaplan working copy. Admin switch **live captions** (default on;
off ⇒ no previews, per-window windows only).

Dev cluster, `tests/e2e/meeting-notes.mjs` (now also measures captions at
the second participant):

| Run | Captions at the other participant | Transcriber | Transcript |
|-----|-----------------------------------|-------------|------------|
| Before (per window) | first ~10 s after start, one block per 8 s | 8 s hard cuts | sentences cut mid-word |
| Both talking, 45 s | first after 2.6 s; 36 messages (28 interim, 8 final); a line grows ~3.8 s before it is final | 10 windows, 41 previews, 5 pause cuts, 3 silent stretches dropped | mostly whole sentences; long sentences without a pause still split at 8 s (a boundary word can go missing) |
| Second person silent (room noise, −50 dBFS), 40 s | first after 2.4 s | 6 windows, 6 silent stretches dropped | **no line from the silent person** (no "Vielen Dank.") |

Open: overlap at hard cuts so a boundary word is not lost; unit tests for
the new plugin routes; GPU load per speaker with previews (one 1–2 s
window every 1.5 s while speaking, none while silent).

**2026-10-11 — Phantom "Vielen Dank." and live captions.** Real meetings
on the dev cluster had "Vielen Dank." lines nobody said, always in the
stream of the person who was listening. Cause: every unmuted participant's
audio goes to Whisper in 8 s windows, also when they are silent; Whisper
turns silence and room noise into stock phrases. Measured on the GPU host
(German turbo model, windows through Opus like Jitsi audio):

| Window (8 s) | VAD off (until today) | VAD on |
|--------------|-----------------------|--------|
| Digital silence | "Vielen Dank." | empty |
| Quiet room noise (pink, −50 dBFS) | "Vielen Dank." | empty |
| Other speaker leaking in at −35 dB | an invented sentence | empty |
| Normal speech | correct | identical |
| 2 s speech, then quiet | correct | identical |

Fix: Silero VAD in `whisper-server` (`--vad`; the model was already on the
host), live on the dev cluster since 2026-10-11. A no-speech window now returns empty text and the
plugin writes nothing. The `stt` chart and image (synaplan-charts#51) turn
VAD on by default so other targets do not regress.

Live captions: the transcriber already sent every result back into the
meeting; Jitsi hands it to all participants as a message from
`transcriber` (verified: 6 results in 30 s at the second participant), but
nobody saw it because Jitsi's own subtitles were off. The loader now shows
a small caption strip (bottom centre, above the toolbar and the speaker
name, at most 640 px wide, the last two speakers, names from Jitsi's
roster, "You" for your own speech, fades after 7 s), for everyone in the
meeting, signed in or not, with a per-browser **Captions: on/off** switch
next to the notes button and a hide button on the strip. Interim results
(`is_interim`) already update the same line, so step-by-step captions need
no UI change. Today the text arrives per 8 s window (about 9–10 s after it
was said). E2E checks own captions ("You:"), the other speaker's name, and
the off/on switch.

**2026-10-05 — Transcripts for every participant (plugin 0.2.0, Prosody
module 0.2.0).** Product owner: the transcript lands for all participants
in their Generated overview; pushing to OpenCloud / Nextcloud comes from
there later. What changed:

- Prosody remembers everyone in the room while notes are on (also people
  who join late or leave early), with the identity from their Jitsi token:
  Keycloak `sub`, email, display name. Guests have none and get no copy.
- At the end the plugin writes one copy per recipient: the starter plus
  every attendee matched to a Synaplan account (email + `sub` check;
  existing accounts are only looked up, never updated). Someone who never
  opened Synaplan gets their account created through Synaplan's own
  first-sign-in path (`OidcUserService::findOrCreateFromClaims`).
- Each copy: source `generated`, kind `document`, the chosen folder,
  `sourceId synascriber-<ref>`, vectorized, so it lists in
  **Sources › Generated** and Synaplan's push menu can send it to a cloud
  folder. The header gains a participants line (everyone present, also
  those who never spoke).
- Copy uses Synaplan's real labels: the page is **Sources** (Quellen,
  Fuentes, Sources, Kaynaklar), not "Files". The dialog says who gets the
  notes; the starter's notice says how many people got a copy and how many
  did not; every other participant gets "The meeting notes {name} started
  are in your Synaplan Sources › Generated" with a link to their own copy.
- Admin switch **share with participants** (default on; off = only the
  starter). Personal page lists notes you started or received.

Walked on the dev cluster with `tests/e2e/meeting-notes.mjs` (now also
signs both people in to Synaplan and checks Sources › Generated in the list
and through the files API):

| Run | People | Result |
|-----|--------|--------|
| 1, 2 | demo1 starts, demo2 | Both notices, own links (files 4–7), both copies in Generated, 76 s each |
| 3 | demo1 starts, demo3 (new directory user) | demo3's account came from the loader's first Synaplan call; copy in Generated |
| 4 | demo1 starts, demo4 (new, loader blocked like the mobile app) | Plugin created demo4's account at save time (user 5); demo4's first Synaplan sign-in matched it, no duplicate; copy waiting in Generated |

`demo3` / `demo4` are test directory users created with
`keycloak-nubus-setup.py --ensure-user` (passwords in the private
credentials file, never in git).

**2026-10-04 — Research.** Read `origin/main` `b0390620c` (plugin host,
auth, speech-to-text, files, [synaplan#2252](https://github.com/metadist/synaplan/pull/2252)), `synaplan-charts` `origin/main`,
Synaform 4.4.3, the openDesk edition used on the dev cluster, Jitsi's
bridge-transcription handbook page and the July 2026 Jitsi blog post.
Findings in [`01_findings.md`](./01_findings.md).

**2026-10-04 — Spike MN-0.1: bridge transcription on openDesk's Jitsi
`stable-11031`.** Dev cluster, reversible. A WebSocket logger in the Jitsi
namespace; `custom-jicofo.conf` with `url-template` and an `Authorization`
header; a test Prosody module loaded at runtime that set
`asyncTranscription`, `transcription.urlParams` and (for one room)
`recording.isTranscribingEnabled`; Playwright Chromium as `demo1` with a
fake microphone. Results:

- The bridge connected with `sessionId=<meeting uuid>&sendBack=true&notes=…&lang=de`
  and the header, ~1.5 s after join.
- Per speaker: `start` (tag `<endpointId>-<ssrc>`, Opus 48 kHz,
  `customParameters.endpointId`, no name), `media` (~38 packets/s,
  ~30 kbit/s, RTP timestamps), `ping` every 10 s, `session-end` + close 1001
  on stop or when the last person left.
- A moderator's `setMetadata('recording', {isTranscribingEnabled})` started
  and stopped it.
- 20 test results accepted by the bridge (0 parse failures), broadcast, and
  received by the page as `non_participant_message_received` from
  `transcriber`; **not displayed** because the viewer's subtitle language
  was `en-US` and the results were `de` (open box in `MN-0`).
- `demo1` was a moderator; Jitsi showed `isTranscribing: true`.

Reverted the same day: logger removed, `custom-jicofo.conf` deleted and
Jicofo restarted, Prosody module unloaded and deleted. Spike scripts are in
`.local/`.

**2026-10-04 — MN-0 engine round 0 (GPU part).** Our GPU host (NVIDIA RTX
PRO 6000 Blackwell, 96 GB, shared with chat models that were idle during the
runs). whisper.cpp
`ghcr.io/ggml-org/whisper.cpp:main-cuda` (1.9.4, CUDA 13, runs on compute
12.0 through PTX), `whisper-server` with `-l de --convert`, one model at a
time. GPU access through NVIDIA Container Toolkit 1.20.1 + CDI, no Docker
restart. Test set: FLEURS de_de dev (CC-BY-4.0), 363 read utterances,
75.8 min, references normalised to lowercase without punctuation.

| Model | WER | Median / p95 per utterance | Speed | VRAM |
|-------|-----|----------------------------|-------|------|
| `ggml-large-v3-turbo` (stock) | 5.88 % | 0.168 / 0.226 s | ~72× real time | 2.5 GB |
| `primeline/whisper-large-v3-turbo-german` → ggml f16 | **5.36 %** | 0.166 / 0.216 s | ~73× real time | 2.5 GB |

Notes: on the first 150 utterances the gap was larger (7.47 vs 4.94 %)
because stock turbo dropped whole clauses there; over all 363 both models
badly damage 8 utterances (≥ 30 % of words wrong or missing), and per
utterance the German model is better on 26 and worse on 29 of the first
150 (spelling variants such as "W-Lan"). FLEURS is not in primeline's
training mix (Common Voice 17, MLS). Conversion needed a bf16 → f32 cast in
whisper.cpp's `convert-h5-to-ggml.py`. Read speech only: meeting audio
(set C) decides. CPU part of round 0 still open.

**2026-10-04 — MN-8a2 quantization and the `synaplan-stt` image.** Same
host and set. German model quantized with `whisper-quantize` (5–6 s each):
q8_0 **5.30 %** WER, 874 MB, 1.6 GB GPU memory; q5_0 5.41 %, 574 MB,
1.3 GB; speed unchanged (0.17 s median). Servers ran as UID 65532 with an
empty CUDA cache: ready in 0.9–1.4 s, no files left in `--tmp-dir`. CPU
(whisper.cpp 1.9.4 CPU image, 8 threads, first 30 utterances): q8_0 3.65 %
at 5.8 s per utterance, q5_0 4.17 % at 7.6 s — q8_0 is the one model for
both variants. Then built `synaplan-stt` from `synaplan-charts`
`feat/stt-whisper-server` on the host (cuda 4.3 GB, cpu 1.9 GB): the
baked q8_0 file has the same sha256 as the hand-made one; the cuda image
with a read-only root filesystem scored 5.30 % on all 363 utterances
(0.18 s median); the cpu image transcribed a sample correctly in 6 s. The
CUDA variant does not start without a GPU (`libcuda.so.1` comes from the
driver). Details in [08](./08_stt_service.md).

**2026-10-05 — Dev MVP walked end to end on the dev cluster.** Synaplan
5.1.3 (moved from 5.1.0 the same day), openDesk Jitsi `stable-11031`, German
Whisper on our GPU host. Journey J-MN-2 + J-MN-3 automated
(`tests/e2e/meeting-notes.mjs`): two signed-in people join with German
speech audio; the floating button appears 1.3 s after joining; Start →
"Notes on" for the starter in 0.2 s; the second person sees the banner and
Jitsi's own "transcribing" indicator at once; after 70 s Stop → "Notes
saved in Synaplan Files › Meetings" with a link that opens the file in
14 s. The file has the right speaker names (from Prosody's participant
list), times and the German text; it is vectorized (4 chunks). The plugin
page lists both runs with Open file.

How it runs without the `MN-1` core changes (to replace when 5.2.0 exists):

| Plan | Dev MVP |
|------|---------|
| Public plugin routes with HMAC for the transcriber | Transcriber uses the plugin owner's Synaplan API key; every call is checked against a live session |
| Synaplan's own Whisper (server mode, `MN-8a`) | The plugin calls the Whisper server directly (`WhisperClient`) with the §5 filters |
| File source `meeting` | Source `api` |
| Loader served by a public plugin route | Served from the plugin's public assets path of the owner account |

Found while walking:

- `plugin_data` reduces type and key to `[a-z0-9_]` on write but looks them
  up unchanged, so keys with `-` or `:` are written and never found again.
  The plugin now builds only such keys; the core fix belongs to `MN-1`.
- Whisper ends segments inside words; joining segments with a space split
  words ("gem eldet"). Raw join fixed it. A sub-second leftover at Stop
  produced "Amen."; leftovers under 1 s are no longer sent.
- Fixed 8 s windows still cut sentences at window edges ("eine |
  WLAN-Türklingel"): voice-activity windows from `MN-5` are the next
  quality step.
- Keycloak's redirect headers outgrow nginx's default proxy buffer once a
  browser holds several sessions (502 on Synaplan sign-in after Jitsi). The
  dev cluster's ingress now uses a 16k buffer; openDesk installs need the
  same.
- Jicofo's start script `chown`s `/config`, so a read-only mount there stops
  Jicofo. The Jicofo config is mounted elsewhere and copied in by a small
  `cont-init` script.
- Jitsi's own UI language decides the loader's language (`<html lang>`);
  the meeting language for the transcript is chosen in the dialog.
