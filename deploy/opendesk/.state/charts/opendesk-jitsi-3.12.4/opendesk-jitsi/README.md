<!--
SPDX-FileCopyrightText: 2023 Bundesministerium des Innern und für Heimat, PG ZenDiS "Projektgruppe für Aufbau ZenDiS"
SPDX-License-Identifier: Apache-2.0
-->
# opendesk-jitsi

Deploy Jitsi using upstream Helm chart with additional configuration for openDesk.

## Installing the Chart

To install the chart with the release name `my-release`, you have two options:

### Install via Repository
```console
helm repo add opendesk-jitsi https://gitlab.opencode.de/api/v4/projects/1377/packages/helm/stable
helm install my-release --version 3.12.4 opendesk-jitsi/opendesk-jitsi
```

### Install via OCI Registry
```console
helm repo add opendesk-jitsi oci://registry.opencode.de/bmi/opendesk/components/platform-development/charts/opendesk-jitsi
helm install my-release --version 3.12.4 opendesk-jitsi/opendesk-jitsi
```

## Requirements

| Repository | Name | Version |
|------------|------|---------|
| https://jitsi-contrib.github.io/jitsi-helm/ | jitsi(jitsi-meet) | 3.8.0 |
| oci://registry.opencode.de/bmi/opendesk/components/external/charts/bitnami-charts | common | ^2.x.x |

## Values

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| affinity | object | `{}` | Affinity for pod assignment Ref: https://kubernetes.io/docs/concepts/configuration/assign-pod-node/#affinity-and-anti-affinity Note: podAffinityPreset, podAntiAffinityPreset, and  nodeAffinityPreset will be ignored when it's set |
| cleanup.deletePodsOnSuccess | bool | `true` | Keep Pods/Job logs after successful run. |
| cleanup.keepPVCOnDelete | bool | `false` | Keep persistence on delete of this release. |
| containerSecurityContext.allowPrivilegeEscalation | bool | `false` | Enable container privileged escalation. |
| containerSecurityContext.capabilities | object | `{"drop":["ALL"]}` | Security capabilities for container. |
| containerSecurityContext.enabled | bool | `true` | Enable security context. |
| containerSecurityContext.privileged | bool | `false` | Run the container in privileged mode. |
| containerSecurityContext.readOnlyRootFilesystem | bool | `true` | Mounts the container's root filesystem as read-only. |
| containerSecurityContext.runAsGroup | int | `1993` | Process group id. |
| containerSecurityContext.runAsNonRoot | bool | `true` | Run container as user. |
| containerSecurityContext.runAsUser | int | `1993` | Process user id. |
| containerSecurityContext.seccompProfile | object | `{"type":"RuntimeDefault"}` | Set Seccomp profile. |
| extraEnvVars | list | `[]` | Array with extra environment variables to add to containers.  extraEnvVars:   - name: FOO     value: "bar"  |
| extraVolumeMounts | list | `[]` | Optionally specify extra list of additional volumeMounts. |
| extraVolumes | list | `[]` | Optionally specify extra list of additional volumes |
| fullnameOverride | string | `""` | Provide a name to substitute for the full names of resources. |
| global.domain | string | `"souvap-univention.de"` | The Top-Level-Domain (TLD) name which is used in f.e. in Ingress component. |
| global.hosts.intercomService | string | `"ics"` | Subdomain for ICS, results in "https://{{ intercomService }}.{{ domain }}". |
| global.hosts.jitsi | string | `"meet"` | Subdomain for Jitsi, results in "https://{{ jitsi }}.{{ domain }}". |
| global.hosts.keycloak | string | `"id"` | Subdomain for Keycloak, results in "https://{{ keycloak }}.{{ domain }}". |
| global.hosts.nubus | string | `"portal"` | Subdomain for the portal. Results in "https://{{ nubus }}.{{ domain }}". The value is used to set the hyperlink on the watermark. |
| global.imagePullSecrets | list | `[]` | Credentials to fetch images from private registry Ref: https://kubernetes.io/docs/tasks/configure-pod-container/pull-image-private-registry/  imagePullSecrets:   - "docker-registry"  |
| global.registry | string | `"docker.io"` | Container registry address. |
| image.imagePullPolicy | string | `"IfNotPresent"` | Define an ImagePullPolicy.  Ref.: https://kubernetes.io/docs/concepts/containers/images/#image-pull-policy  "IfNotPresent" => The image is pulled only if it is not already present locally. "Always" => Every time the kubelet launches a container, the kubelet queries the container image registry to             resolve the name to an image digest. If the kubelet has a container image with that exact digest cached             locally, the kubelet uses its cached image; otherwise, the kubelet pulls the image with the resolved             digest, and uses that image to launch the container. "Never" => The kubelet does not try fetching the image. If the image is somehow already present locally, the            kubelet attempts to start the container; otherwise, startup fails  |
| image.registry | string | `"ghcr.io"` | Container registry address. This setting has higher precedence than global.registry. |
| image.repository | string | `"nordeck/jitsi-keycloak-adapter"` | Container repository string. |
| image.tag | string | `"v20260623@sha256:d4c08aaba0354b7fa8e552bc10f35e7c54538b93ddd836b5940dc25d41e6d3a4"` | Define image tag. |
| imagePullSecrets | list | `[]` | Credentials to fetch images from private registry Ref: https://kubernetes.io/docs/tasks/configure-pod-container/pull-image-private-registry/  imagePullSecrets:   - "docker-registry"  |
| jitsi.enableAuth | bool | `true` |  |
| jitsi.enableGuests | bool | `true` |  |
| jitsi.extraCommonEnvs.ADAPTER_INTERNAL_URL | string | `"http://jitsi-keycloak-adapter:9000"` |  |
| jitsi.extraCommonEnvs.TOOLBAR_BUTTONS | string | `"camera,chat,closedcaptions,desktop,download,feedback,filmstrip,fullscreen,hangup,help,invite,livestreaming,microphone,mute-everyone,mute-video-everyone,participants-pane,profile,raisehand,security,select-background,settings,shareaudio,shortcuts,stats,tileview,toggle-camera,videoquality"` |  |
| jitsi.image.pullPolicy | string | `"IfNotPresent"` | Default ImagePullPolicy for all Jitsi containers, including the init containers. Components may override it per image. |
| jitsi.jibri.enabled | bool | `false` |  |
| jitsi.jibri.image.tag | string | `"stable-11248@sha256:b45b7500283baf76b8e59a0aea1e4751f7cf2616ba8ebb2b376c36a21e6b7d05"` |  |
| jitsi.jibri.livenessProbe.failureThreshold | int | `10` |  |
| jitsi.jibri.livenessProbe.initialDelaySeconds | int | `15` |  |
| jitsi.jibri.livenessProbe.timeoutSeconds | int | `3` |  |
| jitsi.jibri.livestreaming | bool | `true` |  |
| jitsi.jibri.readinessProbe.failureThreshold | int | `10` |  |
| jitsi.jibri.readinessProbe.initialDelaySeconds | int | `15` |  |
| jitsi.jibri.readinessProbe.timeoutSeconds | int | `3` |  |
| jitsi.jibri.recording | bool | `true` |  |
| jitsi.jibri.resources | object | `{"limits":{"memory":"4Gi"},"requests":{"cpu":0.1,"memory":"384Mi"}}` | Resource requests and limits. No CPU limit is set on purpose to avoid throttling the recording process. |
| jitsi.jibri.shm.enabled | bool | `true` |  |
| jitsi.jibri.shm.size | string | `"2Gi"` |  |
| jitsi.jibri.shm.useHost | bool | `false` |  |
| jitsi.jicofo.extraEnvs.AUTH_TYPE | string | `"xmpp"` |  |
| jitsi.jicofo.extraEnvs.ENABLE_AUTO_LOGIN | string | `"false"` |  |
| jitsi.jicofo.extraEnvs.JICOFO_AUTH_LIFETIME | string | `"100 milliseconds"` |  |
| jitsi.jicofo.image.tag | string | `"stable-11248@sha256:adac7d81618aadd6a2158b72a33a3e23165787092bf5f89be210d92113d7e736"` |  |
| jitsi.jicofo.resources | object | `{"limits":{"memory":"4Gi"},"requests":{"cpu":0.1,"memory":"384Mi"}}` | Resource requests and limits. No CPU limit is set on purpose to avoid throttling the JVM process. |
| jitsi.jigasi.enabled | bool | `false` |  |
| jitsi.jigasi.extraEnvs.JIGASI_ENABLE_REST | string | `"true"` |  |
| jitsi.jigasi.extraEnvs.JIGASI_SIP_DEFAULT_ROOM | string | `"siptest@muc.meet.jitsi"` |  |
| jitsi.jigasi.image.tag | string | `"stable-11248@sha256:a48e76f8d9910f4c7e60213eda964d9e987f9ccb97a0aed75f317922e09c0760"` |  |
| jitsi.jigasi.initContainers.busybox.image | string | `"busybox:1.38.0@sha256:1cfa4e2b09e127b9c4ed43578d3f3c18e7d44ea47b9ea98475c0cbe9086525f8"` | Image for the wait-for-prosody init container which waits until Prosody accepts connections. |
| jitsi.jigasi.resources | object | `{"limits":{"memory":"4Gi"},"requests":{"cpu":0.1,"memory":"384Mi"}}` | Resource requests and limits. No CPU limit is set on purpose to avoid throttling the JVM process. |
| jitsi.jvb.image.tag | string | `"stable-11248@sha256:5542fd70a68148bf1ffc19d9a846ed17279ed20090e7c85beca3788a1c5350bd"` |  |
| jitsi.jvb.portRangeSize | int | `1` | Number of consecutive ports to allocate, one JVB per port. This is how to run more than one JVB: each one is published on its own port of the JVB service and advertised separately by the patchJVB job. |
| jitsi.jvb.replicaCount | int | `1` | Number of pods of a single JVB. The chart rejects a value greater than 1 while the JVB service is enabled, because all pods of a deployment are reachable on the same service port. Use portRangeSize to run more than one JVB. |
| jitsi.jvb.resources | object | `{"limits":{"memory":"4Gi"},"requests":{"cpu":0.1,"memory":"384Mi"}}` | Resource requests and limits. No CPU limit is set on purpose to avoid throttling the media process. |
| jitsi.jvb.service.enabled | bool | `true` |  |
| jitsi.jvb.service.externalTrafficPolicy | string | `""` |  |
| jitsi.jvb.service.type | string | `"LoadBalancer"` |  |
| jitsi.octo.enabled | bool | `true` |  |
| jitsi.prosody.extraEnvs.AUTH_TYPE | string | `"jwt"` |  |
| jitsi.prosody.image.tag | string | `"stable-11248@sha256:5a478c431b7951f8c94c92dde7b7ac216a783d0bffa270cf84eadfa9fc110cb3"` |  |
| jitsi.prosody.resources | object | `{"limits":{"memory":"1Gi"},"requests":{"cpu":0.1,"memory":"32Mi"}}` | Resource requests and limits. No CPU limit is set on purpose to avoid throttling. |
| jitsi.publicURL | string | `"https://jitsi.mydomain.tld"` |  |
| jitsi.supportURL | string | `""` |  |
| jitsi.tz | string | `"Europe/Berlin"` |  |
| jitsi.web.extraConfig.dialinCountryCode | string | `"DE"` |  |
| jitsi.web.extraConfig.dialinPhoneNumbers | string | `""` |  |
| jitsi.web.extraConfig.disableThirdPartyRequests | bool | `false` |  |
| jitsi.web.extraConfig.doNotStoreRoom | bool | `false` |  |
| jitsi.web.extraEnvs.CONFCODE_URL | string | `"https://jitsi-api.jitsi.net/conferenceMapper"` |  |
| jitsi.web.extraEnvs.DIALIN_NUMBERS_URL | string | `"/static/dialin-phone-numbers.json"` |  |
| jitsi.web.extraVolumeMounts[0].mountPath | string | `"/config/custom-config.js"` |  |
| jitsi.web.extraVolumeMounts[0].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[0].subPath | string | `"custom-config.js"` |  |
| jitsi.web.extraVolumeMounts[10].mountPath | string | `"/usr/share/jitsi-meet/static/lang-overwritten-de.json"` |  |
| jitsi.web.extraVolumeMounts[10].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[10].subPath | string | `"lang-overwritten-de.json"` |  |
| jitsi.web.extraVolumeMounts[11].mountPath | string | `"/usr/share/jitsi-meet/static/lang-overwritten-en.json"` |  |
| jitsi.web.extraVolumeMounts[11].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[11].subPath | string | `"lang-overwritten-en.json"` |  |
| jitsi.web.extraVolumeMounts[12].mountPath | string | `"/usr/share/jitsi-meet/static/oidc-adapter.html"` |  |
| jitsi.web.extraVolumeMounts[12].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[12].subPath | string | `"oidc-adapter.html"` |  |
| jitsi.web.extraVolumeMounts[13].mountPath | string | `"/usr/share/jitsi-meet/static/oidc-redirect.html"` |  |
| jitsi.web.extraVolumeMounts[13].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[13].subPath | string | `"oidc-redirect.html"` |  |
| jitsi.web.extraVolumeMounts[14].mountPath | string | `"/usr/share/jitsi-meet/static/url-ics"` |  |
| jitsi.web.extraVolumeMounts[14].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[14].subPath | string | `"url-ics"` |  |
| jitsi.web.extraVolumeMounts[15].mountPath | string | `"/usr/share/jitsi-meet/static/url-logo"` |  |
| jitsi.web.extraVolumeMounts[15].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[15].subPath | string | `"url-logo"` |  |
| jitsi.web.extraVolumeMounts[16].mountPath | string | `"/usr/share/jitsi-meet/static/url-portal"` |  |
| jitsi.web.extraVolumeMounts[16].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[16].subPath | string | `"url-portal"` |  |
| jitsi.web.extraVolumeMounts[1].mountPath | string | `"/config/custom-interface_config.js"` |  |
| jitsi.web.extraVolumeMounts[1].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[1].subPath | string | `"custom-interface_config.js"` |  |
| jitsi.web.extraVolumeMounts[2].mountPath | string | `"/defaults/meet.conf"` |  |
| jitsi.web.extraVolumeMounts[2].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[2].subPath | string | `"meet.oidc.conf"` |  |
| jitsi.web.extraVolumeMounts[3].mountPath | string | `"/usr/share/jitsi-meet/body.html"` |  |
| jitsi.web.extraVolumeMounts[3].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[3].subPath | string | `"body.html"` |  |
| jitsi.web.extraVolumeMounts[4].mountPath | string | `"/usr/share/jitsi-meet/plugin.head.html"` |  |
| jitsi.web.extraVolumeMounts[4].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[4].subPath | string | `"plugin.head.html"` |  |
| jitsi.web.extraVolumeMounts[5].mountPath | string | `"/usr/share/jitsi-meet/images/favicon.svg"` |  |
| jitsi.web.extraVolumeMounts[5].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[5].subPath | string | `"favicon.svg"` |  |
| jitsi.web.extraVolumeMounts[6].mountPath | string | `"/usr/share/jitsi-meet/images/watermark.svg"` |  |
| jitsi.web.extraVolumeMounts[6].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[6].subPath | string | `"watermark.svg"` |  |
| jitsi.web.extraVolumeMounts[7].mountPath | string | `"/usr/share/jitsi-meet/images/welcome-background.jpg"` |  |
| jitsi.web.extraVolumeMounts[7].name | string | `"jitsi-meet-binaries"` |  |
| jitsi.web.extraVolumeMounts[7].subPath | string | `"welcome-background.jpg"` |  |
| jitsi.web.extraVolumeMounts[8].mountPath | string | `"/usr/share/jitsi-meet/static/branding.json"` |  |
| jitsi.web.extraVolumeMounts[8].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[8].subPath | string | `"branding.json"` |  |
| jitsi.web.extraVolumeMounts[9].mountPath | string | `"/usr/share/jitsi-meet/static/dialin-phone-numbers.json"` |  |
| jitsi.web.extraVolumeMounts[9].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumeMounts[9].subPath | string | `"dialin-phone-numbers.json"` |  |
| jitsi.web.extraVolumes[0].configMap.items[0].key | string | `"body.html"` |  |
| jitsi.web.extraVolumes[0].configMap.items[0].path | string | `"body.html"` |  |
| jitsi.web.extraVolumes[0].configMap.items[10].key | string | `"oidc-redirect.html"` |  |
| jitsi.web.extraVolumes[0].configMap.items[10].path | string | `"oidc-redirect.html"` |  |
| jitsi.web.extraVolumes[0].configMap.items[11].key | string | `"plugin.head.html"` |  |
| jitsi.web.extraVolumes[0].configMap.items[11].path | string | `"plugin.head.html"` |  |
| jitsi.web.extraVolumes[0].configMap.items[12].key | string | `"url-ics"` |  |
| jitsi.web.extraVolumes[0].configMap.items[12].path | string | `"url-ics"` |  |
| jitsi.web.extraVolumes[0].configMap.items[13].key | string | `"url-logo"` |  |
| jitsi.web.extraVolumes[0].configMap.items[13].path | string | `"url-logo"` |  |
| jitsi.web.extraVolumes[0].configMap.items[14].key | string | `"url-portal"` |  |
| jitsi.web.extraVolumes[0].configMap.items[14].path | string | `"url-portal"` |  |
| jitsi.web.extraVolumes[0].configMap.items[15].key | string | `"watermark.svg"` |  |
| jitsi.web.extraVolumes[0].configMap.items[15].path | string | `"watermark.svg"` |  |
| jitsi.web.extraVolumes[0].configMap.items[1].key | string | `"branding.json"` |  |
| jitsi.web.extraVolumes[0].configMap.items[1].path | string | `"branding.json"` |  |
| jitsi.web.extraVolumes[0].configMap.items[2].key | string | `"custom-config.js"` |  |
| jitsi.web.extraVolumes[0].configMap.items[2].path | string | `"custom-config.js"` |  |
| jitsi.web.extraVolumes[0].configMap.items[3].key | string | `"custom-interface_config.js"` |  |
| jitsi.web.extraVolumes[0].configMap.items[3].path | string | `"custom-interface_config.js"` |  |
| jitsi.web.extraVolumes[0].configMap.items[4].key | string | `"dialin-phone-numbers.json"` |  |
| jitsi.web.extraVolumes[0].configMap.items[4].path | string | `"dialin-phone-numbers.json"` |  |
| jitsi.web.extraVolumes[0].configMap.items[5].key | string | `"favicon.svg"` |  |
| jitsi.web.extraVolumes[0].configMap.items[5].path | string | `"favicon.svg"` |  |
| jitsi.web.extraVolumes[0].configMap.items[6].key | string | `"lang-overwritten-de.json"` |  |
| jitsi.web.extraVolumes[0].configMap.items[6].path | string | `"lang-overwritten-de.json"` |  |
| jitsi.web.extraVolumes[0].configMap.items[7].key | string | `"lang-overwritten-en.json"` |  |
| jitsi.web.extraVolumes[0].configMap.items[7].path | string | `"lang-overwritten-en.json"` |  |
| jitsi.web.extraVolumes[0].configMap.items[8].key | string | `"meet.oidc.conf"` |  |
| jitsi.web.extraVolumes[0].configMap.items[8].path | string | `"meet.oidc.conf"` |  |
| jitsi.web.extraVolumes[0].configMap.items[9].key | string | `"oidc-adapter.html"` |  |
| jitsi.web.extraVolumes[0].configMap.items[9].path | string | `"oidc-adapter.html"` |  |
| jitsi.web.extraVolumes[0].configMap.name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumes[0].name | string | `"jitsi-meet-files"` |  |
| jitsi.web.extraVolumes[1].configMap.items[0].key | string | `"welcome-background.jpg"` |  |
| jitsi.web.extraVolumes[1].configMap.items[0].path | string | `"welcome-background.jpg"` |  |
| jitsi.web.extraVolumes[1].configMap.name | string | `"jitsi-meet-binaries"` |  |
| jitsi.web.extraVolumes[1].name | string | `"jitsi-meet-binaries"` |  |
| jitsi.web.image.tag | string | `"stable-11248@sha256:4395cab855270c5e66a4e190a7103f0876d0c60d5e64cb7abd01e581fb3bfe0e"` |  |
| jitsi.web.ingress.annotations."ingress.cilium.io/tcp-keep-alive" | string | `"enabled"` |  |
| jitsi.web.ingress.annotations."ingress.cilium.io/websocket" | string | `"enabled"` |  |
| jitsi.web.ingress.annotations."nginx.ingress.kubernetes.io/proxy-read-timeout" | string | `"3600"` |  |
| jitsi.web.ingress.annotations."nginx.ingress.kubernetes.io/proxy-send-timeout" | string | `"3600"` |  |
| jitsi.web.ingress.annotations."nginx.org/websocket-services" | string | `"jitsi-web"` |  |
| jitsi.web.replicaCount | int | `1` |  |
| jitsi.web.resources | object | `{"limits":{"memory":"1Gi"},"requests":{"cpu":0.1,"memory":"32Mi"}}` | Resource requests and limits. No CPU limit is set on purpose to avoid throttling. |
| jitsi.websockets.xmpp.enabled | bool | `true` |  |
| job.podAnnotations | object | `{}` | Annotations for job template Ref: https://kubernetes.io/docs/concepts/overview/working-with-objects/annotations/ |
| lifecycleHooks | object | `{}` | Lifecycle to automate configuration before or after startup |
| livenessProbe.enabled | bool | `true` | Enables kubernetes LivenessProbe. |
| livenessProbe.failureThreshold | int | `5` | Number of failed executions until container is terminated. |
| livenessProbe.initialDelaySeconds | int | `5` | Delay after container start until LivenessProbe is executed. |
| livenessProbe.periodSeconds | int | `3` | Time between probe executions. |
| livenessProbe.successThreshold | int | `1` | Number of successful executions after failed ones until container is marked healthy. |
| livenessProbe.timeoutSeconds | int | `1` | Timeout for command return. |
| nameOverride | string | `""` | String to partially override release name. |
| nodeSelector | object | `{}` | Node labels for pod assignment Ref: https://kubernetes.io/docs/user-guide/node-selection/ |
| patchJVB.backoffLimit | int | `10` | Set the patchJVB job's backoffLimit |
| patchJVB.configuration.loadbalancerStatusField | string | `"ip"` | Relevant for LoadBalancer deployment only. The IP/DNS of your load-balancer will be fetched from the status entry of service - here you can define which field should be taken. |
| patchJVB.configuration.staticLoadbalancerIP | string | `""` | Relevant for NodePort deployments only, where it is optional. In case you have a different egress gateway and thus the incoming ip can not be autodiscovered, provide the ip of your ingress gateway. Leave it empty to let JVB discover its own address; the job sets the node port either way. |
| patchJVB.containerSecurityContext.allowPrivilegeEscalation | bool | `false` | Enable container privileged escalation. |
| patchJVB.containerSecurityContext.capabilities | object | `{"drop":["ALL"]}` | Security capabilities for container. |
| patchJVB.containerSecurityContext.enabled | bool | `true` | Enable security context. |
| patchJVB.containerSecurityContext.privileged | bool | `false` | Run the container in privileged mode. |
| patchJVB.containerSecurityContext.readOnlyRootFilesystem | bool | `true` | Mounts the container's root filesystem as read-only. |
| patchJVB.containerSecurityContext.runAsGroup | int | `1001` | Process group id. |
| patchJVB.containerSecurityContext.runAsNonRoot | bool | `true` | Require the container to run as a non-root user. |
| patchJVB.containerSecurityContext.runAsUser | int | `1001` | Run the container as this (non-zero) user. Matches the kubectl image's built-in non-root user. |
| patchJVB.containerSecurityContext.seccompProfile | object | `{"type":"RuntimeDefault"}` | Seccomp profile for the container. |
| patchJVB.enabled | bool | `true` | Enable post deployment advertisement for LoadBalancer IP or NodePort port to JVB. |
| patchJVB.image.imagePullPolicy | string | `"IfNotPresent"` | Define an ImagePullPolicy.  Ref.: https://kubernetes.io/docs/concepts/containers/images/#image-pull-policy  "IfNotPresent" => The image is pulled only if it is not already present locally. "Always" => Every time the kubelet launches a container, the kubelet queries the container image registry to             resolve the name to an image digest. If the kubelet has a container image with that exact digest             cached locally, the kubelet uses its cached image; otherwise, the kubelet pulls the image with             the resolved digest, and uses that image to launch the container. "Never" => The kubelet does not try fetching the image. If the image is somehow already present locally, the            kubelet attempts to start the container; otherwise, startup fails  |
| patchJVB.image.registry | string | `""` | Container registry address. This setting has higher precedence than global.registry. |
| patchJVB.image.repository | string | `"bitnami/kubectl"` | Container repository string. |
| patchJVB.image.tag | string | `"1.33.4@sha256:681609aff6bf316acf464d9c9e369d84c49d50be6379247291b01ac311a7f5f5"` | Define image tag. |
| patchJVB.imagePullSecrets | list | `[]` | Credentials to fetch images from private registry Ref: https://kubernetes.io/docs/tasks/configure-pod-container/pull-image-private-registry/  imagePullSecrets:   - "docker-registry"  |
| patchJVB.podSecurityContext.enabled | bool | `false` | Enable security context. |
| patchJVB.resources.limits.cpu | string | `"100m"` | The max amount of CPUs to consume. |
| patchJVB.resources.limits.memory | string | `"128Mi"` | The max amount of RAM to consume. |
| patchJVB.resources.requests.cpu | string | `"10m"` | The amount of CPUs which has to be available on the scheduled node. |
| patchJVB.resources.requests.memory | string | `"16Mi"` | The amount of RAM which has to be available on the scheduled node. |
| patchJVB.ttlSecondsAfterFinished | int | `86400` | Seconds after which a finished patchJVB job (and its pod) is automatically cleaned up by the TTL controller. Successful runs are already removed by the Helm hook-delete-policy, so this mainly bounds how long a FAILED job is kept for debugging (default: 1 day). |
| podAnnotations | object | `{}` | Pod Annotations. Ref: https://kubernetes.io/docs/concepts/overview/working-with-objects/annotations/ |
| podLabels | object | `{}` | Pod Labels. Ref: https://kubernetes.io/docs/concepts/overview/working-with-objects/labels/ |
| podSecurityContext.enabled | bool | `false` | Enable security context. |
| rbac.create | bool | `true` | Enable RBAC Role and RoleBinding creation. |
| readinessProbe.enabled | bool | `true` | Enables kubernetes ReadinessProbe. |
| readinessProbe.failureThreshold | int | `15` | Number of failed executions until container is terminated. |
| readinessProbe.initialDelaySeconds | int | `5` | Delay after container start until ReadinessProbe is executed. |
| readinessProbe.periodSeconds | int | `2` | Time between probe executions. |
| readinessProbe.successThreshold | int | `1` | Number of successful executions after failed ones until container is marked healthy. |
| readinessProbe.timeoutSeconds | int | `1` | Timeout for command return. |
| replicaCount | int | `1` | Set the amount of replicas of deployment. |
| resources.limits.cpu | string | `"100m"` | The max amount of CPUs to consume. |
| resources.limits.memory | string | `"128Mi"` | The max amount of RAM to consume. |
| resources.requests.cpu | string | `"10m"` | The amount of CPUs which has to be available on the scheduled node. |
| resources.requests.memory | string | `"16Mi"` | The amount of RAM which has to be available on the scheduled node. |
| service.annotations | object | `{}` | Additional custom annotations |
| service.enabled | bool | `true` | Enable kubernetes service creation. |
| service.ports.adapter.containerPort | int | `9000` | Internal port for keycloak adapter. |
| service.ports.adapter.port | int | `9000` | Accessible port for keycloak adapter. |
| service.ports.adapter.protocol | string | `"TCP"` | Keycloak adapter service protocol. |
| service.type | string | `"ClusterIP"` | Choose the kind of Service, one of "ClusterIP", "NodePort" or "LoadBalancer". |
| serviceAccount.annotations | object | `{}` | Additional custom annotations for the ServiceAccount. |
| serviceAccount.automountServiceAccountToken | bool | `true` | Allows auto mount of ServiceAccountToken on the serviceAccount created. Can be set to false if pods using this serviceAccount do not need to use K8s API. |
| serviceAccount.create | bool | `true` | Enable creation of ServiceAccount for pod. |
| serviceAccount.labels | object | `{}` | Additional custom labels for the ServiceAccount. |
| settings.allowUnsecureCert | string | `"false"` | Set true if Keycloak has not a trusted certificate. For the production environment, Keycloak should have a trusted certificate and this value should be false. |
| settings.internalHostname | string | `"0.0.0.0"` |  |
| settings.jwtAlg | string | `"HS256"` |  |
| settings.jwtAppSecret.existingSecret.key | string | `""` | Key inside the Kubernetes secret that contains the jwtAppSecret. |
| settings.jwtAppSecret.existingSecret.name | string | `""` | Name of the Kubernetes secret that contains the jwtAppSecret. |
| settings.jwtAppSecret.value | string | `""` | jwtAppSecret as plain value |
| settings.jwtExpSecond | string | `"3600"` |  |
| settings.jwtHash | string | `"SHA-256"` |  |
| settings.keycloakClientId | string | `"jitsi"` | Configured client id in keycloak. |
| settings.keycloakRealm | string | `"souvap"` | Keycloak realm name. |
| terminationGracePeriodSeconds | string | `""` | In seconds, time the given to the pod needs to terminate gracefully. Ref: https://kubernetes.io/docs/concepts/workloads/pods/pod/#termination-of-pods |
| theme.colors | object | `{"background":"#121212","primary":"#5e27dd","secondaryGreyLight":"#f5f5f5"}` | Color settings. |
| theme.enableOldTopbar | bool | `true` | Topbar setting |
| theme.imagery | object | `{"faviconSvgB64":"PHN2ZyB3aWR0aD0iMSIgaGVpZ2h0PSIxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciLz4=","logoHeaderInvertedSvgB64":"PHN2ZyB3aWR0aD0iMSIgaGVpZ2h0PSIxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciLz4="}` | Imagery and related settings. |
| theme.imagery.faviconSvgB64 | string | `"PHN2ZyB3aWR0aD0iMSIgaGVpZ2h0PSIxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciLz4="` | An base64 encoded SVG image used as favicon during video conferences. |
| theme.imagery.logoHeaderInvertedSvgB64 | string | `"PHN2ZyB3aWR0aD0iMSIgaGVpZ2h0PSIxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciLz4="` | An base64 encoded SVG image used as watermark during video conferences. |
| theme.title | string | `"Videokonferenz"` | The HTML title also used for the browser tab name. |
| tolerations | list | `[]` | Tolerations for pod assignment Ref: https://kubernetes.io/docs/concepts/configuration/taint-and-toleration/ |
| topologySpreadConstraints | list | `[]` | Topology spread constraints rely on node labels to identify the topology domain(s) that each Node is in Ref: https://kubernetes.io/docs/concepts/workloads/pods/pod-topology-spread-constraints/  topologySpreadConstraints:   - maxSkew: 1     topologyKey: failure-domain.beta.kubernetes.io/zone     whenUnsatisfiable: DoNotSchedule |
| updateStrategy.type | string | `"RollingUpdate"` | Set to Recreate if you use persistent volume that cannot be mounted by more than one pods to make sure the pods is destroyed first. |

## Uninstalling the Chart

To install the release with name `my-release`:

```bash
helm uninstall my-release
```

## Signing

Helm charts are signed with helm native signing method.

You can verify the chart against [the public GPG key](../../files/gpg-pubkeys/opendesk.gpg).

## License

This project uses the following license: Apache-2.0

## Copyright

Copyright (C) 2023 Bundesministerium des Innern und für Heimat, PG ZenDiS "Projektgruppe für Aufbau ZenDiS"
