# AGENTS.md — synaScriber

synaScriber is a **public** repository: a Synaplan plugin plus the Jitsi /
openDesk pieces for sovereign meeting transcripts. Read
[docs/README.md](docs/README.md) first.

## Public and private

- Everything committed here is public. **Never** commit IP addresses,
  private hostnames, ports of private servers, firewall rules, kube
  namespaces of real clusters, credentials, tokens or customer project
  names. Write "the dev cluster" or "our GPU host".
- Private material lives in **`.local/`** (gitignored): access details,
  servers, detailed planning, the deploy tooling for the dev cluster, the
  quickstart with demo accounts. Start with `.local/README.md` when it exists
  on your machine.
- Before every push: scan the diff for IPs, hostnames and secrets.

## Product rules

- The Synaplan UX bar applies: [Synaplan AGENTS.md](https://github.com/metadist/synaplan/blob/main/AGENTS.md)
  ("Perfect UX & Stability") and the
  [UX contract](https://github.com/metadist/synaplan/blob/main/_devextras/planning/20260907_ux_user_flows.md).
- All UI text in five locales (en, de, es, fr, tr). User-facing name of the
  feature: **Meeting notes**. No jargon in primary copy (see docs/README.md §3).
- Code, comments and commit messages in English.

## Conventions

- Plugin id `synascriber` (`^[a-z0-9_-]+$`), PHP namespace `Plugin\SynaScriber`,
  settings group `P_synascriber`, layout like
  [Synaform](https://github.com/metadist/Synaform).
- Conventional Commits (`feat:`, `fix:`, `docs:`, `chore:`, `refactor:`, `test:`);
  no AI attribution; feature branches and pull requests, not direct pushes
  to `main`.
- Changes in other repositories (`synaplan`, `synaplan-charts`, the openDesk
  edition) go through their own pull requests and gates.
