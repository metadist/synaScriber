#!/usr/bin/env python3
"""Create or update the Keycloak public client the synaScriber Jitsi loader signs in with.

The client:
  - public, Authorization Code + PKCE (S256), no direct grants, no service account;
  - redirect URI and web origin: the Jitsi origin (silent sign-in page
    /static/synascriber-silent.html served by Jitsi web);
  - access tokens carry `aud=<audience>` (Synaplan's OIDC client id), plus
    sub, email and name, so Synaplan's bearer authenticator accepts them and
    provisions the person on first use.

Usage:
  KEYCLOAK_ADMIN_USER=... KEYCLOAK_ADMIN_PASSWORD=... \\
    deploy/keycloak/ensure-client.py --url https://id.example.org --realm opendesk \\
      --jitsi-origin https://meet.example.org [--audience synaplan] [--dry-run]

Idempotent; prints "+ created", "~ updated" or "= unchanged" per item.
Requires python3 (stdlib only).
"""
import argparse
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request

SILENT_PATH = "/static/synascriber-silent.html"


def http(method, url, headers=None, form=None, body=None):
    hdrs = dict(headers or {})
    data = None
    if form is not None:
        data = urllib.parse.urlencode(form).encode()
        hdrs["Content-Type"] = "application/x-www-form-urlencoded"
    elif body is not None:
        data = json.dumps(body).encode()
        hdrs["Content-Type"] = "application/json"
    request = urllib.request.Request(url, data=data, method=method, headers=hdrs)
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            raw = response.read()
            return response.status, (json.loads(raw) if raw else None)
    except urllib.error.HTTPError as error:
        raw = error.read().decode(errors="ignore")
        try:
            return error.code, json.loads(raw)
        except ValueError:
            return error.code, raw


def mapper(name, kind, **config):
    cfg = {"id.token.claim": "true", "access.token.claim": "true", "userinfo.token.claim": "true"}
    cfg.update({key.replace("_", "."): value for key, value in config.items()})
    return {"name": name, "protocol": "openid-connect", "protocolMapper": kind, "consentRequired": False, "config": cfg}


def desired(client_id, origin, audience):
    aud = mapper(f"{audience}-audience", "oidc-audience-mapper", included_client_audience=audience)
    aud["config"]["id.token.claim"] = "false"
    aud["config"].pop("userinfo.token.claim")
    client = {
        "clientId": client_id,
        "name": "synaScriber (meeting notes in Jitsi)",
        "enabled": True,
        "protocol": "openid-connect",
        "publicClient": True,
        "standardFlowEnabled": True,
        "implicitFlowEnabled": False,
        "directAccessGrantsEnabled": False,
        "serviceAccountsEnabled": False,
        "frontchannelLogout": True,
        "fullScopeAllowed": False,
        "rootUrl": origin,
        "baseUrl": origin,
        "redirectUris": [origin + SILENT_PATH],
        "webOrigins": [origin],
        "attributes": {"pkce.code.challenge.method": "S256", "post.logout.redirect.uris": "+"},
    }
    mappers = [
        aud,
        mapper("sub", "oidc-usermodel-property-mapper", user_attribute="id", claim_name="sub", jsonType_label="String"),
        mapper("email", "oidc-usermodel-property-mapper", user_attribute="email", claim_name="email", jsonType_label="String"),
        mapper("full name", "oidc-full-name-mapper"),
        mapper("username", "oidc-usermodel-property-mapper", user_attribute="username", claim_name="preferred_username", jsonType_label="String"),
    ]
    return client, mappers


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--url", required=True, help="Keycloak base URL, e.g. https://id.example.org")
    ap.add_argument("--realm", required=True)
    ap.add_argument("--jitsi-origin", required=True, help="e.g. https://meet.example.org")
    ap.add_argument("--audience", default="synaplan", help="Synaplan's OIDC client id (token audience)")
    ap.add_argument("--client-id", default="synascriber")
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    user, password = os.environ.get("KEYCLOAK_ADMIN_USER"), os.environ.get("KEYCLOAK_ADMIN_PASSWORD")
    if not user or not password:
        sys.exit("error: set KEYCLOAK_ADMIN_USER and KEYCLOAK_ADMIN_PASSWORD")
    base = args.url.rstrip("/")
    origin = args.jitsi_origin.rstrip("/")

    status, token = http("POST", f"{base}/realms/master/protocol/openid-connect/token",
                         form={"grant_type": "password", "client_id": "admin-cli", "username": user, "password": password})
    if status != 200:
        sys.exit(f"error: Keycloak admin login failed ({status})")
    auth = {"Authorization": "Bearer " + token["access_token"]}
    admin = f"{base}/admin/realms/{args.realm}"
    prefix = "(dry-run) " if args.dry_run else ""

    client, mappers = desired(args.client_id, origin, args.audience)
    status, found = http("GET", f"{admin}/clients?clientId={urllib.parse.quote(args.client_id)}", headers=auth)
    if status != 200:
        sys.exit(f"error: listing clients failed ({status})")

    if not found:
        print(f"+ {prefix}client {args.client_id} created")
        if args.dry_run:
            return
        status, body = http("POST", f"{admin}/clients", headers=auth, body=dict(client, protocolMappers=mappers))
        if status not in (201, 204):
            sys.exit(f"error: creating client failed ({status}) {str(body)[:200]}")
        return

    have = found[0]
    changed = {key: value for key, value in client.items() if key != "attributes" and have.get(key) != value}
    attributes = dict(have.get("attributes") or {})
    for key, value in client["attributes"].items():
        if attributes.get(key) != value:
            attributes[key] = value
            changed["attributes"] = attributes
    if changed:
        print(f"~ {prefix}client {args.client_id}: {', '.join(sorted(changed))}")
        if not args.dry_run:
            status, body = http("PUT", f"{admin}/clients/{have['id']}", headers=auth, body=dict(have, **changed))
            if status not in (200, 204):
                sys.exit(f"error: updating client failed ({status}) {str(body)[:200]}")
    else:
        print(f"= client {args.client_id}: settings unchanged")

    status, current = http("GET", f"{admin}/clients/{have['id']}/protocol-mappers/models", headers=auth)
    by_name = {m["name"]: m for m in (current or [])}
    for want in mappers:
        got = by_name.get(want["name"])
        if got and got["protocolMapper"] == want["protocolMapper"] and all(got.get("config", {}).get(k) == v for k, v in want["config"].items()):
            print(f"= mapper {want['name']}")
            continue
        print(f"{'~' if got else '+'} {prefix}mapper {want['name']}")
        if args.dry_run:
            continue
        if got:
            status, body = http("PUT", f"{admin}/clients/{have['id']}/protocol-mappers/models/{got['id']}", headers=auth, body=dict(want, id=got["id"]))
        else:
            status, body = http("POST", f"{admin}/clients/{have['id']}/protocol-mappers/models", headers=auth, body=want)
        if status not in (200, 201, 204):
            sys.exit(f"error: mapper {want['name']} failed ({status}) {str(body)[:200]}")


if __name__ == "__main__":
    main()
