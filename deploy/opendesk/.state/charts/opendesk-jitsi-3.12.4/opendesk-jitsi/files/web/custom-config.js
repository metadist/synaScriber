// SPDX-FileCopyrightText: 2024 Zentrum für Digitale Souveränität der Öffentlichen Verwaltung (ZenDiS) GmbH
// SPDX-FileCopyrightText: 2023 Bundesministerium des Innern und für Heimat, PG ZenDiS "Projektgruppe für Aufbau ZenDiS"
// SPDX-License-Identifier: Apache-2.0
config.disabledNotifications = [
  "notify.moderator",
];
config.disabledSounds = [
  "INCOMING_MSG_SOUND",
];
config.doNotStoreRoom = {{ .Values.jitsi.web.extraConfig.doNotStoreRoom }};
config.dynamicBrandingUrl = "/static/branding.json";
config.disableThirdPartyRequests = {{ .Values.jitsi.web.extraConfig.disableThirdPartyRequests }};
