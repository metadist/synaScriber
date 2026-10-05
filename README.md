# synaScriber — the openDesk bridge for meeting transcripts

**Sovereign meeting notes for openDesk.** synaScriber is a plugin for the
[Synaplan](https://github.com/metadist/synaplan) AI server. It turns the
spoken words of a Jitsi meeting into a written transcript with speaker names
and times — on your own servers, with your own speech model, without a cloud
service in between.

*With friendly support by [iabg.de](https://www.iabg.de).*

---

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
- **Identity:** openDesk's Keycloak; the transcript belongs to the person who
  started it.
- **Speech-to-text:** Synaplan's own Whisper, served by `whisper-server` on a
  GPU — the German fine-tune of Whisper large-v3-turbo by default
  (5.3 % word error rate on German read speech, about 70× faster than real
  time on one GPU). Packaged as the `stt` chart in
  [synaplan-charts](https://github.com/metadist/synaplan-charts).

## Sovereign by design

- Runs entirely inside your openDesk / Kubernetes installation.
- No cloud speech service unless an administrator explicitly allows one.
- Audio is processed in memory and discarded; only text is stored.
- Transparent for everyone in the meeting: banner, Jitsi's own indicator,
  one chat line on start and stop.
- Open source under Apache 2.0, built on open models and open components.

## Status and roadmap

| Version | Scope |
|---------|-------|
| **v1.0 (MVP)** | Jitsi in openDesk: floating button, start/stop, language and folder, live captions, transcript in Synaplan Files. Every signed-in person may start. |
| v1.1 | The administrator limits who may start (groups, people); "leave my voice out"; retention. |
| v1.2 | Save the transcript into the person's OpenCloud as well. |
| Later | Element chat and calls, summaries with decisions and action items, translation. |

Planning and the feasibility spike are done; the transcription path was
verified on openDesk's Jitsi. Code follows the steps in
[docs/07_sprints.md](docs/07_sprints.md). Start reading at
[docs/README.md](docs/README.md).

## Repositories

| Repository | Role |
|------------|------|
| [metadist/synaplan](https://github.com/metadist/synaplan) | The Synaplan server (core): plugin host, files, speech-to-text, the transcriber sidecar. |
| [metadist/synaplan-charts](https://github.com/metadist/synaplan-charts) | Helm charts: `synaplan`, and `stt` for the Whisper speech server on GPU nodes. |
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

- Synaplan 5.2.0 or later (plugin routes, Whisper server mode)
- openDesk with Jitsi (verified on Jitsi `stable-11031`)
- A GPU node for the Whisper server is recommended; a CPU variant exists
  for small installations

## License

Apache License 2.0 — see [LICENSE](LICENSE) and [NOTICE](NOTICE).

Developed by [metadist](https://github.com/metadist), with friendly support
by [iabg.de](https://www.iabg.de).
