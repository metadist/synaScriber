// SPDX-FileCopyrightText: 2024 Zentrum für Digitale Souveränität der Öffentlichen Verwaltung (ZenDiS) GmbH
// SPDX-FileCopyrightText: 2023 Bundesministerium des Innern und für Heimat, PG ZenDiS "Projektgruppe für Aufbau ZenDiS"
// SPDX-License-Identifier: Apache-2.0
interfaceConfig.APP_NAME =
  "{{ .Values.theme.title }}";
interfaceConfig.DISABLE_JOIN_LEAVE_NOTIFICATIONS = true;
interfaceConfig.DISABLE_PRESENCE_STATUS = true;
interfaceConfig.DISABLE_TRANSCRIPTION_SUBTITLES = true;
interfaceConfig.DISABLE_VIDEO_BACKGROUND = true;
interfaceConfig.DISPLAY_WELCOME_FOOTER = false;
interfaceConfig.ENABLE_DIAL_OUT = false;
interfaceConfig.GENERATE_ROOMNAMES_ON_WELCOME_PAGE = true;
interfaceConfig.RECENT_LIST_ENABLED =
  {{ not .Values.jitsi.web.extraConfig.doNotStoreRoom }};
interfaceConfig.SHOW_JITSI_WATERMARK = true;
interfaceConfig.SUPPORT_URL = "{{ .Values.jitsi.supportURL }}";
interfaceConfig.JITSI_WATERMARK_LINK =
  "https://{{ .Values.global.hosts.nubus }}.{{ .Values.global.domain }}";
