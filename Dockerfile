# synaScriber's files for Kubernetes: the Synaplan plugin, Jitsi's silent
# sign-in page and the Prosody module. Init containers copy them into the
# Synaplan and Jitsi pods (synaplan-charts, synascriber.enabled).
FROM busybox:1.37
COPY synascriber-plugin /synascriber/plugin
COPY jitsi/static/synascriber-silent.html /synascriber/jitsi/synascriber-silent.html
COPY prosody/mod_synascriber.lua /synascriber/prosody/mod_synascriber.lua
