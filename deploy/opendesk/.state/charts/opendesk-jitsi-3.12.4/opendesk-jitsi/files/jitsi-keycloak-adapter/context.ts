// -----------------------------------------------------------------------------
// SPDX-FileCopyrightText: 2024 Zentrum für Digitale Souveränität der Öffentlichen Verwaltung (ZenDiS) GmbH
// SPDX-FileCopyrightText: 2023 Bundesministerium des Innern und für Heimat, PG ZenDiS "Projektgruppe für Aufbau ZenDiS"
// SPDX-License-Identifier: Apache-2.0
// -----------------------------------------------------------------------------
// This function creates the context inside JWT's payload. It gets userInfo
// (which comes from Keycloak) as parameter.
//
// Update the codes according to your requirements. Welcome to TypeScript :)
// -----------------------------------------------------------------------------
export interface KeycloakUserInfo {
  sub: string;
  name?: string;
  preferred_username?: string;
  opendesk_username?: string;
  email?: string;
}

export function createContext(userInfo: KeycloakUserInfo) {
  // If the user doesn't have opendesk_username in her userInfo then don't
  // generate a token for her.
  if (!userInfo.opendesk_username) throw new Error("no username");

  const context = {
    user: {
      id: userInfo.sub,
      name: userInfo.name?.trim() || userInfo.opendesk_username,
      email: userInfo.email?.trim() || "",
      lobby_bypass: true,
    },
  };
  return context;
}
