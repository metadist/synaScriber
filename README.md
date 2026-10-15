# synaScriber — the openDesk bridge for meeting transcripts

**Sovereign meeting notes for openDesk.** synaScriber is a plugin for the
[Synaplan](https://github.com/metadist/synaplan) AI server. It turns the
spoken words of a Jitsi meeting into a written transcript with speaker names
and times — on your own servers, with your own speech model, without a cloud
service in between.

*With friendly support by [iabg.de](https://www.iabg.de).*

---

## Get started on your openDesk

You run openDesk with Jitsi and want meeting notes. One command adds
Synaplan and everything else to it:

```bash
git clone https://github.com/metadist/synaScriber.git
cd synaScriber/deploy/opendesk
cp synascriber.env.example synascriber.env   # choose speech-to-text: cpu, gpu or your server
./synascriber check                          # changes nothing; shows what it found and will add
./synascriber install
```

**Step-by-step guide, requirements and troubleshooting:
[deploy/opendesk/README.md](deploy/opendesk/README.md).** Tested on
openDesk v1.19.1 with Synaplan 5.3.2.

| Start in the meeting | After the meeting |
|---|---|
| ![Start dialog](docs/images/opendesk-start.png) | ![Live captions and the saved notice](docs/images/opendesk-saved.png) |

## What it does

1. In an [openDesk](https://www.opendesk.eu) installation with Synaplan, every
   Jitsi meeting shows a small floating **Meeting notes** button.
2. A signed-in participant clicks it, picks the meeting language and a folder
   in Synaplan, and starts.
3. Everyone in the meeting sees that notes are being taken and can see who
   started them. A moderator can stop them at any time.
4. When the meeting ends, one Markdown transcript is in the chosen folder:
   searchable, shareable like any file, and ready for questions in Synaplan
   chat ("What did we decide yesterday?").

Audio is never recorded or kept. It only exists while it is being turned
into text.

## How it works

```text
Jitsi Videobridge  ── each speaker's audio (WebSocket) ──▶  transcriber
transcriber        ── short audio windows ──────────────▶  Synaplan + synaScriber
Synaplan           ── speech-to-text ───────────────────▶  Whisper server (GPU, German model)
Synaplan           ── captions, transcript file ────────▶  the meeting, Synaplan Files
Jitsi web loader   ◀─ button, dialog, banner (Keycloak) ─▶  Synaplan + synaScriber
```

- **Audio path:** Jitsi's own bridge transcription streams each speaker
  separately. No browser has to stay open, no bot joins the meeting, no
  recording service is needed.
- **Who decides:** Synaplan. A Prosody module turns transcription on per room
  only when Synaplan says so; clients cannot start it on their own.
- **Identity:** openDesk's Keycloak; every signed-in participant gets their
  own copy of the transcript.
- **Speech-to-text:** a whisper.cpp `whisper-server` with the German
  fine-tune of Whisper large-v3-turbo (5.3 % word error rate on German read
  speech, about 70× faster than real time on one GPU), image
  [synaplan-stt](https://github.com/metadist/synaplan-stt), deployed with the
  `stt` chart of [synaplan-charts](https://github.com/metadist/synaplan-charts).

Packages, requirements and the ToDo: [docs/02_architecture.md](docs/02_architecture.md).

## Sovereign by design

- Runs entirely inside your openDesk / Kubernetes installation.
- No cloud speech service unless an administrator explicitly allows one.
- Audio is processed in memory and discarded; only text is stored.
- Transparent for everyone in the meeting: banner and Jitsi's own indicator.
- Open source under Apache 2.0, built on open models and open components.

## Status and roadmap

| Version | Scope |
|---------|-------|
| **v1.0 (MVP)** | Jitsi in openDesk: floating button, start/stop, language and folder, live captions, transcript in Synaplan Files. Every signed-in person may start. |
| v1.1 | The administrator limits who may start (groups, people); "leave my voice out"; retention. |
| v1.2 | Save the transcript into the person's OpenCloud as well. |
| Later | Element chat and calls, summaries with decisions and action items, translation. |

**Now:** the installer in [`deploy/opendesk/`](deploy/opendesk/) adds
Synaplan and meeting notes to a standard openDesk. On openDesk v1.19.1 with
Synaplan 5.3.2 it passes the two-person journey end to end (button, start,
banner, live captions, stop, transcript in Synaplan Sources › Generated for
both people) — see [docs/STATUS.md](docs/STATUS.md). The remaining steps to
v1.0 are in [docs/07_sprints.md](docs/07_sprints.md). Start reading the plan
at [docs/README.md](docs/README.md).

**Known issue:** a fresh `install` currently stops at the Helm step, because
the pinned synaplan-charts commit is gone (fix and workaround:
[docs/02_architecture.md](docs/02_architecture.md#7-todo)).

## What is in this repository

| Path | What |
|------|------|
| [`synascriber-plugin/`](synascriber-plugin/) | The Synaplan plugin (`manifest.json`, PHP backend, plugin page, Jitsi loader, migration). Mount or copy it to `/plugins/synascriber` and run `app:plugin:install`. |
| [`prosody/mod_synascriber.lua`](prosody/mod_synascriber.lua) | Prosody module on Jitsi's main VirtualHost: lets Synaplan switch bridge transcription on and off per room; clients cannot start it. |
| [`jitsi/static/`](jitsi/static/) | The silent sign-in page served by Jitsi web. |
| [`deploy/opendesk/`](deploy/opendesk/) | **The installer for openDesk**: `./synascriber check / install / verify / uninstall`, the `synascriber` Helm chart, the settings file, the guide. |
| [`deploy/keycloak/ensure-client.py`](deploy/keycloak/ensure-client.py) | Creates the Keycloak public client the loader signs in with (standalone; the installer does this itself). |
| [`tests/e2e/meeting-notes.mjs`](tests/e2e/meeting-notes.mjs) | Playwright journey against a real openDesk: two people, speech audio, start, banner, stop, saved file. |
| [`docs/`](docs/) | The current architecture ([`02`](docs/02_architecture.md)), plan, research, steps, status. |

The transcriber that receives the audio from Jitsi's bridge is the
`synaplan-transcriber` sidecar in the [Synaplan repository](https://github.com/metadist/synaplan)
(plugin mode).

## Repositories

| Repository | Role |
|------------|------|
| [metadist/synaplan](https://github.com/metadist/synaplan) | The Synaplan server (core): plugin host, files, speech-to-text, the transcriber sidecar. |
| [metadist/synaplan-stt](https://github.com/metadist/synaplan-stt) | The speech-to-text image: whisper.cpp `whisper-server` with the German Whisper model, as `cpu` and `cuda`. |
| [metadist/synaplan-charts](https://github.com/metadist/synaplan-charts) | Helm charts: `synaplan`, `stt` for the Whisper speech server, and `deployments/opendesk-addon`, which adds Synaplan to an existing openDesk (the installer uses it). |
| **metadist/synaScriber** (this repo) | The plugin, the Jitsi loader, the Prosody module, openDesk configuration snippets, the plan. |

## More Synaplan integrations

- [Synaplan for Nextcloud](https://github.com/metadist/synaplan-nextcloud) —
  RAG-powered discussions, translations and summaries next to your Nextcloud.
- [Synaplan for OpenCloud](https://github.com/metadist/synaplan-opencloud) —
  Synaplan inside OpenCloud.
- Synaplan for [openDesk](https://www.opendesk.eu) — Synaplan as an app in
  openDesk, sharing its Keycloak, Collabora and OpenCloud.
- [Synaform](https://github.com/metadist/Synaform) — another Synaplan plugin:
  AI-assisted filling of Word templates.

## Requirements (v1.0)

- openDesk with Jitsi — verified on openDesk v1.19.1 (Jitsi `stable-11248`,
  chart 3.12.4); earlier on Jitsi `stable-11031`
- Synaplan 5.3.2 (the installer brings it)
- A speech-to-text server: on a GPU node for real meetings, on CPU for
  trying it out, or one you already run

## License

Apache License 2.0 — see [LICENSE](LICENSE) and [NOTICE](NOTICE).

Developed by [metadist](https://github.com/metadist), with friendly support
by [iabg.de](https://www.iabg.de).
