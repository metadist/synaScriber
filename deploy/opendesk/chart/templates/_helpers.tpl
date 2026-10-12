{{- define "synascriber.labels" -}}
app.kubernetes.io/part-of: synascriber
app.kubernetes.io/managed-by: {{ .Release.Service }}
helm.sh/chart: {{ printf "%s-%s" .Chart.Name .Chart.Version }}
{{- end }}

{{/*
Value of key in an existing Secret, else the fallback. Keeps generated
secrets stable across upgrades; renders without a cluster use the fallback.
*/}}
{{- define "synascriber.keep" -}}
{{- $secret := lookup "v1" "Secret" .ns .name -}}
{{- if and $secret (hasKey ($secret.data | default dict) .key) -}}
{{- index $secret.data .key | b64dec -}}
{{- else -}}
{{- .fallback -}}
{{- end -}}
{{- end }}

{{/* ConfigMap keys cannot hold "/": plugin paths are flattened with "__". */}}
{{- define "synascriber.pluginKey" -}}
{{- . | trimPrefix "files/plugin/" | replace "/" "__" -}}
{{- end }}
