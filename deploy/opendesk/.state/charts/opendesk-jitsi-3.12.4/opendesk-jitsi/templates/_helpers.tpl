{{/*
SPDX-FileCopyrightText: 2023 Bundesministerium des Innern und für Heimat, PG ZenDiS "Projektgruppe für Aufbau ZenDiS"
SPDX-License-Identifier: Apache-2.0
*/}}
{{/* vim: set filetype=mustache: */}}
{{- define "opendesk-jitsi.keycloakAdapter.fullname" -}}
{{- .Release.Name -}}-jitsi-keycloak-adapter
{{- end -}}
{{- define "opendesk-jitsi.jvbPatcher.fullname" -}}
{{- .Release.Name -}}-jvb-patcher
{{- end -}}
{{/*
Fully qualified name of the JVB resources created by the jitsi-meet subchart
(aliased "jitsi"). Mirrors the subchart's "jitsi-meet.jvb.fullname", which is
"jitsi-meet.fullname" + "-jvb" and deduplicates when the release name already
contains the chart name (e.g. release "jitsi" => "jitsi-jvb", not
"jitsi-jitsi-jvb"). Keep in sync with the subchart so RBAC/patcher names match.
*/}}
{{- define "opendesk-jitsi.jvb.fullname" -}}
{{- $jitsi := .Values.jitsi | default dict -}}
{{- if $jitsi.fullnameOverride -}}
{{- printf "%s-jvb" ($jitsi.fullnameOverride | trunc 63 | trimSuffix "-") -}}
{{- else -}}
{{- $name := default "jitsi" $jitsi.nameOverride -}}
{{- if contains $name .Release.Name -}}
{{- printf "%s-jvb" (.Release.Name | trunc 63 | trimSuffix "-") -}}
{{- else -}}
{{- printf "%s-%s-jvb" .Release.Name $name -}}
{{- end -}}
{{- end -}}
{{- end -}}
