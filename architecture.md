# Architecture

synaScriber adds sovereign **Meeting notes** to an openDesk Jitsi
installation. Spoken audio is turned into a Markdown transcript on your own
servers and stored in Synaplan. Audio is processed in memory and discarded;
only text is kept.

The component contract (APIs, session states, security) is in
[docs/02_architecture.md](docs/02_architecture.md). This page is the short
setup and the technical path.

## Setup

Requirements:

- A working openDesk you can sign in to, with Jitsi meetings (tested on
  openDesk v1.19.1).
- `kubectl` with admin access to that cluster.
- Helm 3 (3.17 or newer, not Helm 4), `helmfile` 1.x, the `helm-diff`
  plugin, `git`, and `python3`.
- DNS: `synaplan.<your domain>` must reach the same ingress as openDesk (the
  usual `*.<domain>` record already does).
- A speech server, chosen in `synascriber.env`:
  - `STT=cpu` — German Whisper on cluster CPUs (try-out; captions lag).
  - `STT=gpu` — the same model on an NVIDIA GPU node (real meetings, about
    70× real time).
  - `STT=url` — a Whisper server you already run.
- Optional: an Ollama URL so Synaplan chat can answer questions about the
  notes. Notes are still saved without it.

Install:

```bash
git clone https://github.com/metadist/synaScriber.git
cd synaScriber/deploy/opendesk
cp synascriber.env.example synascriber.env   # set STT (and STT_URL if STT=url)
./synascriber check                          # reads the cluster; changes nothing
./synascriber install                        # asks once, then restarts Jitsi
```

`install` restarts Jitsi, so meetings that are already running end. It is
idempotent. Later commands: `verify`, `plan`, `install` again after a pull,
`uninstall` (notes off, Synaplan stays), `uninstall --all` (Synaplan,
database, and portal tile as well).

What the installer puts on the cluster:

- Helm releases in the openDesk namespace: Synaplan 5.3.2 with MariaDB,
  Redis, and volumes; optionally `synaplan-stt`; and the `synascriber` chart
  (plugin files, transcriber, Prosody module).
- Keycloak clients in the `opendesk` realm: `synaplan` (app sign-in) and
  `synascriber` (silent sign-in from the Jitsi page, PKCE).
- The plugin installed for Synaplan's first local administrator, plus the
  transcriber token and settings.
- A **Synaplan** tile in the openDesk portal.
- Jitsi changes: the Meeting notes script in `head.html`, the silent sign-in
  page, `mod_synascriber` on Prosody, and the transcriber URL in Jicofo.
  Secrets stay in Kubernetes Secrets.

After an openDesk update, Keycloak clients and Jitsi settings get rebuilt.
Copy the two files `install` writes under
`~/.local/state/synascriber/…/opendesk/` into your openDesk environment and
apply the Keycloak bootstrap and Jitsi releases once, or the next openDesk
deploy turns meeting notes off. Step-by-step:
[deploy/opendesk/README.md](deploy/opendesk/README.md).

## How it works

Four parts, and the browser never sees audio or cluster secrets:

| Piece | Where | Role |
|---|---|---|
| Loader | Jitsi web page | Floating button, dialog, banner. Signs the person in with Keycloak and only asks Synaplan to start or stop. |
| Plugin `synascriber` | Synaplan (PHP) | Sessions, policy, speech-to-text, Markdown file. |
| `mod_synascriber` | Prosody (Jitsi) | Turns bridge transcription on for one room when Synaplan says so. Clients cannot start it. |
| Transcriber | Cluster-internal sidecar | Receives each speaker's Opus from the Videobridge and posts short windows to the plugin. |

```text
Jitsi Videobridge  ── each speaker's audio (WebSocket) ──▶  transcriber
transcriber        ── short audio windows ──────────────▶  Synaplan + synaScriber
Synaplan           ── speech-to-text ───────────────────▶  Whisper server (GPU, German model)
Synaplan           ── captions, transcript file ────────▶  the meeting, Synaplan Files
Jitsi web loader   ◀─ button, dialog, banner (Keycloak) ─▶  Synaplan + synaScriber
```

One meeting, in order:

1. The Jitsi page loads `loader.js` from Synaplan. If the plugin is on, the
   button appears.
2. A signed-in person clicks **Meeting notes**. The loader does a silent
   Keycloak sign-in (public client `synascriber`, PKCE, token kept in
   memory) and asks Synaplan which languages and folders are available.
3. They pick a language and a folder and start. The plugin creates one
   session for that room, with an unguessable reference (`ref`), and calls
   Prosody over HMAC.
4. Prosody checks that this person is actually in the live meeting, sets
   room metadata (transcription on, `ref`, language, who started it), posts
   a chat line, and returns the roster. A Jitsi moderator can always turn it
   off even if Synaplan is down.
5. Jicofo tells the Videobridge to open a WebSocket to the transcriber for
   that room. Each participant is a separate audio stream. No bot joins, and
   nobody's browser has to stay open.
6. The transcriber binds that socket to the session with a bearer token plus
   `ref`, cuts speech at pauses (about 0.7 s; hard limit 8 s), and drops
   silence. Each window (Ogg/Opus, at most 30 s) is posted to the plugin and
   deleted after transcription.
7. The plugin runs Whisper as the person who started the notes (usage and
   model follow that account). The default model is a German fine-tune of
   Whisper large-v3-turbo, with Silero voice detection. It stores a text
   segment (speaker, times) and the transcriber sends the line back into
   Jitsi as a live caption. While someone is still talking, short previews
   update the same caption; those previews are not stored.
8. Stop comes from the starter, a Jitsi moderator, an empty room, or an
   admin. The last windows are flushed and the plugin writes one Markdown
   transcript per signed-in participant into Synaplan Sources › Generated,
   in the chosen folder (default **Meetings**). Guests get no copy. The file
   is searchable and can be asked about in Synaplan chat.

Session states are `starting` → `running` (or `paused` if speech recognition
fails) → `stopping` → `saved`, `saved_with_gaps`, `nothing_to_save`, or
`failed`. A watchdog finalizes anything left open after the meeting ends, so
a session cannot stay unfinished for more than about eleven minutes once the
room is gone.

Trust boundaries: the browser talks only to Synaplan with the Keycloak
bearer token. Prosody's control API and the transcriber are ClusterIP-only.
Prosody events are HMAC-signed. Audio is accepted only with the transcriber
token and a live `ref` for that meeting id. End-to-end encrypted meetings
are refused, because the bridge cannot read them.
