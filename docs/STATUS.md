# Status — openDesk meeting notes plugin

Plan of record: [`00_master_plan.md`](./00_master_plan.md). Steps:
[`07_sprints.md`](./07_sprints.md). A walk or a measurement is a dated line
here, not a chat message.

## Steps

| Step | State | Notes |
|------|-------|-------|
| `MN-0` Spike and decisions | in progress | Bridge path verified 2026-10-04; Prosody HTTP on the in-cluster address verified 2026-10-05. Captions, Element embedding, 320 px still open. |
| `MN-1` Core prerequisites | not started | Dev MVP runs on 5.1.3 without them (see 2026-10-05). New item: plugin_data lookups must normalize keys like the writes do. |
| `MN-2` Plugin skeleton | **dev MVP** | Plugin `synascriber` 0.2.0: settings (incl. share with participants), admin status, personal page (started and received), five locales. |
| `MN-3` Sessions | **dev MVP** | State machine with watchdog, segments, roster from Prosody. Tests still missing. |
| `MN-4` Prosody module | **dev MVP** | `mod_synascriber` 0.2.0: start/stop/state/health, client starts refused, attendees (Keycloak `sub` + email from the Jitsi token) for everyone present while notes are on. No busted specs yet. |
| `MN-5` Transcriber plugin mode | **dev MVP** | `notes=<ref>` binding, windows to the plugin, 21/21 node tests. Fixed 8 s windows (no VAD yet). |
| `MN-6` Transcript file | **dev MVP** | One Markdown copy per signed-in participant in their Sources › Generated (source `generated`, kind `document`, chosen folder), vectorized; speakers, participants, times. |
| `MN-7` Jitsi surface | **dev MVP** | Floating button, dialog, banner, outcome with file link; Keycloak PKCE silent sign-in. |
| `MN-8` Engine and quality | in progress | German Whisper on our GPU host; `synaplan-stt` image + `stt` chart in synaplan-charts#51. |
| `MN-9` v1.0 release | not started | |
| `MN-10` v1.1 | not started | |
| `MN-11` v1.2 | not started | |
| `MN-12` openDesk packaging | not started | |

## Decisions

| Date | Decision |
|------|----------|
| 2026-10-04 | Plan written. D1–D12 proposed in [`README.md`](./README.md) §4, waiting for the product owner. |
| 2026-10-05 | Product owner: transcripts land for **all participants** in their Generated overview; pushing to OpenCloud / Nextcloud comes from there in a later release. D6 and D12 rewritten accordingly. |

## Log

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
