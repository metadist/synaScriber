# 08 — Speech-to-text service on Kubernetes (`synaplan-charts`)

**Status:** 2026-10-05. Image, chart and helmfile release are in draft PR
[synaplan-charts#51](https://github.com/metadist/synaplan-charts/pull/51)
(branch `feat/stt-whisper-server`): rendered, validated, chart CI green; the
image was built and benchmarked on our GPU host. Not merged, not published,
not yet installed on a GPU cluster. Step `MN-8a2` in
[07](./07_sprints.md#mn-8--engine-and-quality-beside-mn-3mn-7-gates-mn-9).

**Goal:** every target that installs openDesk + Synaplan + the meeting
notes plugin on a Kubernetes cluster with GPU nodes gets the German Whisper
server as one more helmfile release: same chart registry, same mirror
process, nothing downloaded at runtime.

---

## 1. Decision: own image, own chart, own release

| Option | Verdict |
|--------|---------|
| Sub-deployment of the `synaplan` chart, like `tts` | **No.** A GPU pod has its own lifecycle (GPU pool, taints, rollouts that must not wait for a second GPU), may serve more than one Synaplan, and should be sized and upgraded on its own. |
| **Separate chart `stt` + separate helmfile release**, like `triton` | **Yes.** Synaplan only needs a URL (`speech.whisperServerUrl`). The release is switched by one value. |
| Upstream image + model as an OCI artifact pulled by `oras` into a PVC | **No as default.** Two artifacts to mirror and keep in step, a PVC per cluster, an init container with registry credentials. Still possible: the chart has `initContainers` / `extraVolumes` and `server.model`. |
| **Own image `synaplan-stt` with the model baked in**, like `synaplan-tts` with its voices | **Yes.** One artifact; mirror it and it runs air-gapped. |

## 2. What is on the branch

| Path | What |
|------|------|
| `images/stt/Dockerfile` | Multi-stage build: convert the Transformers checkpoint to ggml, quantize to q8_0, add the Silero VAD model, final stage on the pinned upstream whisper.cpp image (`cuda` or `cpu`). |
| `images/stt/entrypoint.sh` | Starts `whisper-server` from `STT_*` variables; container arguments are appended. |
| `images/stt/README.md` | Contents, licences, API, measured numbers. |
| `.github/workflows/stt-image.yaml` | Builds both variants on PRs; publishes `0.0.0-dev.<commit>-<variant>` from `main` and `<version>-<variant>` from a `stt-image-v<version>` tag. |
| `charts/stt/` | Deployment, Service, optional NetworkPolicy, `helm test`, README from `.gotmpl`. |
| `ci/stt/lint-values.yaml` | GPU variant with NetworkPolicy, VAD, runtime class, GPU node selector and toleration. |
| `charts/synaplan` | New value `speech.whisperServerUrl` → `WHISPER_SERVER_URL` on web, worker and scheduler; a duplicate in `env` fails the render. |
| `deployments/synaplan-with-triton/` | `services.stt.mode: "" \| gpu \| cpu` adds the `stt` release, a NetworkPolicy that admits only Synaplan, and sets `speech.whisper` + `speech.whisperServerUrl` on the Synaplan release. Default `""` renders exactly as before. |
| `.github/workflows/ci.yaml`, `README.md`, `CONTRIBUTING.md`, `examples/values-opendesk.yaml` | `stt-v*` release tag; docs; a commented `whisperServerUrl` in the openDesk example. |

Gate run on the branch: `make docs lint template validate package` green
for all three charts; `helmfile list` / `template` for modes off, `gpu`,
`cpu`; kubeconform on the helmfile output.

## 3. The image `ghcr.io/metadist/synaplan-stt`

| | |
|---|---|
| Tags | `<version>-cuda` (NVIDIA GPU), `<version>-cpu` (any x86-64 node) |
| Server | whisper.cpp **v1.9.4** (commit `927cfce3`), upstream images `main-cuda-927cfce…` / `main-927cfce…` pinned by digest |
| Model | `primeline/whisper-large-v3-turbo-german` at revision `9e7012da`, converted with whisper.cpp's `convert-h5-to-ggml.py` (one-line bf16 → f32 cast), quantized **q8_0**, 874 MB, at `/models/ggml-large-v3-turbo-german-q8_0.bin` |
| VAD | `ggml-silero-v5.1.2.bin` from `ggml-org/whisper-vad`, sha256-checked |
| Licences | whisper.cpp MIT, OpenAI Whisper MIT, primeline Apache-2.0, Silero MIT; texts and model cards in `/usr/share/doc/synaplan-stt/` |
| Size | cuda 4.3 GB, cpu 1.9 GB |
| Runtime | UID 65532, works with a read-only root filesystem and a `/tmp` emptyDir; ffmpeg leaves no files behind |
| Reproducible | The build's q8_0 file has the same sha256 as the file benchmarked by hand (`8583eb7d…`) |

Every download in the build is pinned by commit, revision, digest or
sha256. The cuda variant needs the host driver (`libcuda.so.1`): it does
not start on a node without a GPU, so the CPU fallback is the cpu image,
not a flag.

## 4. The chart `stt`

| Value | Default | Note |
|-------|---------|------|
| `image.variant` | `cuda` | `cpu` switches tag and drops the GPU request |
| `image.tag` / `digest` | `<appVersion>-<variant>` / empty | digest rendered as `tag@digest` |
| `gpu.resourceName` / `count` / `runtimeClassName` | `nvidia.com/gpu` / `1` / empty | limit added only for `cuda` |
| `server.language` | `de` | a request's `language` wins |
| `server.threads` | `4` | cpu variant: the CPU limit |
| `server.model` | the baked q8_0 | point elsewhere with `extraVolumes` |
| `server.vad.enabled` | `false` | Silero in the server; the transcriber already windows by voice ([05 §3](./05_stt_quality.md#3-pipeline-in-the-transcriber-plugin-mode)) |
| `strategy` | RollingUpdate, `maxSurge: 0` | an update never needs a second free GPU |
| `resources` | 1 CPU / 2 Gi request, 4 Gi limit | |
| `networkPolicy.enabled` / `from` | off / same namespace | the helmfile release turns it on for Synaplan pods only |
| Probes | `/health`; startup up to 5 min, liveness 60 s tolerance | one long request holds the decoder |
| Security | non-root, read-only root, no capabilities, no service-account token | |

Capacity per pod (measured, §6): one GPU pod ≈ 70× real time, so one pod
serves dozens of meetings with one active speaker each. More pods need more
GPUs, a time-sliced share or MIG slices; `replicaCount` scales, there is no
HPA (GPU-bound, not CPU-bound).

## 5. Synaplan and helmfile wiring

**Synaplan chart:** `speech.whisperServerUrl` → `WHISPER_SERVER_URL`. Only
a Synaplan with the Whisper server mode (`MN-8a`) reads it; older images
ignore it and keep the CLI path.

**Example deployment in `synaplan-charts`:**

```yaml
# deployments/synaplan-with-triton/environments/<env>/values.yaml
services:
  stt:
    mode: gpu            # "" off, gpu, cpu
    values:              # passed to the stt chart as-is
      nodeSelector:
        nvidia.com/gpu.present: "true"
```

**openDesk editions** that install Synaplan with helmfile add the same
release next to their Synaplan and Triton releases (`MN-8a3`, done in the
edition's own repo by its owners):

1. Chart `stt` from the same chart registry as `synaplan` and `triton`
   (`oci://ghcr.io/metadist/synaplan-charts/stt`, or the edition's mirror).
2. Image `synaplan-stt:<version>-cuda` in the edition's image list, so the
   registry mirror and the software bill of materials pick it up; the
   release maps the mirror registry onto `image.repository`.
3. One switch (e.g. `synaplan.stt.mode`), default off; `installed:` follows
   it. The Synaplan release gets `speech.whisper: true` and
   `speech.whisperServerUrl: http://stt.<namespace>.svc.cluster.local:8080`
   when it is on.
4. GPU pool values per environment: node selector, tolerations, runtime
   class, `gpu.resourceName` if the cluster uses MIG or time-slicing names.
5. NetworkPolicy from the Synaplan pods only (`app.kubernetes.io/instance: synaplan`).
6. Render diff empty for every environment that leaves the switch off.

## 6. Versions: what has to move

| Component | Today | Needed for meeting notes on a GPU cluster | Why |
|-----------|-------|-------------------------------------------|-----|
| Synaplan app | dev cluster 5.1.0; latest release 5.1.3; openDesk editions may be older | **5.2.0** (first release with `MN-1` + `MN-8a`) | public plugin routes, `meeting` file source, transcriber image, Whisper server mode. The plugin's `minSynaplanVersion` is 5.2.0. `MN-1`/`MN-8a` are `feat` commits, so the release workflow raises the minor version by itself. |
| `synaplan` chart | 0.6.0 (`main` has unreleased changes on it) | **0.7.0** | `speech.whisperServerUrl`; set `appVersion` 5.2.0 when that release exists |
| `stt` chart | — | **0.1.0** (new) | |
| `synaplan-stt` image | — | **0.1.0** (new) | |
| whisper.cpp | rolling `main-cuda` on our GPU host; 1.7.4 in `synaplan-base-php` | **1.9.4** pinned (image); 1.9.x in `synaplan-base-php` (`MN-8a`) | VAD, server JSON with scores |
| Meeting notes plugin | — | **1.0.0** (`MN-9`) | |
| Transcriber sidecar image | `synaplan-transcriber` ([synaplan#2252](https://github.com/metadist/synaplan/pull/2252)) | same tag as Synaplan 5.2.0 (`MN-1c`) | plugin mode (`MN-5`) |
| openDesk | as installed | **no change** | bridge transcription works on Jitsi `stable-11031` as shipped ([01 §3](./01_findings.md#3-spike-2026-10-04-bridge-transcription-on-the-dev-cluster)); only values change (`MN-12`) |

So: yes, Synaplan has to be bumped — to the 5.2.0 release that carries the
prerequisites — and the edition's Synaplan chart pin to 0.7.0. Bumping to
5.1.3 alone does not help the plugin.

## 7. Measured (2026-10-04, our GPU host, FLEURS de_de dev)

| Model | Variant | WER | Time per 12.5 s utterance | GPU memory | Cold start |
|-------|---------|-----|---------------------------|------------|------------|
| stock large-v3-turbo f16 | cuda | 5.88 % (363) | 0.17 s | 2.5 GB | — |
| German f16 | cuda | 5.36 % (363) | 0.17 s | 2.5 GB | — |
| **German q8_0** | **cuda** | **5.30 % (363)** | **0.17 s** | **1.6 GB** | **1.4 s** (non-root, empty CUDA cache) |
| German q5_0 | cuda | 5.41 % (363) | 0.17 s | 1.3 GB | 0.9 s |
| **German q8_0** | **cpu, 8 threads** | 3.65 % (first 30) | **5.8 s** (≈ 2× real time) | — | 1.2 s |
| German q5_0 | cpu, 8 threads | 4.17 % (first 30) | 7.6 s | — | 1.6 s |
| `synaplan-stt` image (q8_0) | cuda | **5.30 % (363)** | 0.18 s | — | ≈ 1 s |

q8_0 is as accurate as f16, half the size, and on CPU faster than q5_0 —
one model file for both variants.

## 8. Release order

1. PR `feat/stt-whisper-server` → `main` (CI builds both image variants).
2. Tag `stt-image-v0.1.0` → `synaplan-stt:0.1.0-cuda` / `0.1.0-cpu`; note
   the digests.
3. Tag `stt-v0.1.0` (chart; `appVersion` 0.1.0 already set).
4. Tag `synaplan-v0.7.0` (chart with `speech.whisperServerUrl`).
5. Synaplan 5.2.0 with `MN-1` + `MN-8a`; then `appVersion` 5.2.0 in the
   synaplan chart and a patch release of the chart.
6. Edition change (`MN-8a3`) and the meeting notes values (`MN-12`).

## 9. Still to verify

- [ ] Install on a cluster with GPU nodes (NVIDIA GPU operator): pod
      scheduled, `helm test` green, NetworkPolicy enforced (CNI with policy
      support), a Synaplan pod reaches it, another namespace does not.
- [ ] Time-slicing: two `stt` pods (or `stt` + an LLM) on one GPU, latency
      under parallel load (`MN-8c` set C).
- [ ] First run of the `STT Image` workflow on GitHub runners: disk and
      time for the cuda variant (the job frees runner disk first).
- [ ] arm64: upstream whisper.cpp images are x86-64; arm GPU nodes would
      need a source build.
- [ ] A multilingual variant (stock large-v3-turbo q8_0) for installs whose
      meetings are mostly not German — same image recipe, other model
      argument.
