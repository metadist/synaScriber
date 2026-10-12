"""Unit tests for the pure parts of deploy/opendesk/synascriber.

  python3 -m unittest discover -s tests/installer
"""
import copy
import importlib.machinery
import importlib.util
import json
import pathlib
import unittest

SCRIPT = pathlib.Path(__file__).resolve().parents[2] / "deploy" / "opendesk" / "synascriber"
loader = importlib.machinery.SourceFileLoader("synascriber", str(SCRIPT))
spec = importlib.util.spec_from_loader("synascriber", loader)
sc = importlib.util.module_from_spec(spec)
loader.exec_module(sc)

OPENDESK_VALUES = {
    "global": {"domain": "example.org", "hosts": {"jitsi": "meet", "keycloak": "id"}},
    "jitsi": {
        "web": {"extraEnvs": {"TURN_ENABLE": "1"}},
        "prosody": {
            "extraEnvs": {"AUTH_TYPE": "hybrid_matrix_token"},
            "extraSecrets": [{"name": "TURN_CREDENTIALS"}],
            "podAnnotations": {"intents.otterize.com/service-name": "jitsi-prosody"},
        },
        "jicofo": {"podAnnotations": {"intents.otterize.com/service-name": "jitsi-jicofo"}},
    },
}


def ours(head="<script></script>", module="aaaa"):
    return {"jitsi": {
        "web": {"extraFiles": [{"path": sc.HEAD_FILE, "content": head}]},
        "prosody": {
            "extraEnvs": {"XMPP_MODULES": "synascriber"},
            "extraEnvFrom": [{"secretRef": {"name": "synascriber-prosody", "optional": True}}],
            "extraVolumes": [{"name": "synascriber-prosody", "configMap": {"name": "synascriber-prosody"}}],
            "podAnnotations": {"synascriber/module": module},
        },
        "jicofo": {"custom": {"configs": {"_custom_jicofo_conf": "jicofo {}"}}},
    }}


class MergeTest(unittest.TestCase):
    def test_merge_keeps_opendesk_values(self):
        merged = sc.merge(OPENDESK_VALUES, ours())
        prosody = merged["jitsi"]["prosody"]
        self.assertEqual(prosody["extraEnvs"], {"AUTH_TYPE": "hybrid_matrix_token", "XMPP_MODULES": "synascriber"})
        self.assertEqual(prosody["extraSecrets"], [{"name": "TURN_CREDENTIALS"}])
        self.assertEqual(prosody["podAnnotations"]["intents.otterize.com/service-name"], "jitsi-prosody")
        self.assertEqual(merged["jitsi"]["web"]["extraEnvs"], {"TURN_ENABLE": "1"})

    def test_merge_appends_to_existing_lists_and_modules(self):
        base = copy.deepcopy(OPENDESK_VALUES)
        base["jitsi"]["web"]["extraFiles"] = [{"path": "/usr/share/jitsi-meet/other.html", "content": "x"}]
        base["jitsi"]["prosody"]["extraEnvs"]["XMPP_MODULES"] = "muc_census"
        merged = sc.merge(base, ours())
        self.assertEqual([f["path"] for f in merged["jitsi"]["web"]["extraFiles"]],
                         ["/usr/share/jitsi-meet/other.html", sc.HEAD_FILE])
        self.assertEqual(merged["jitsi"]["prosody"]["extraEnvs"]["XMPP_MODULES"], "muc_census,synascriber")

    def test_unmerge_restores_opendesk_values(self):
        merged = sc.merge(OPENDESK_VALUES, ours())
        self.assertEqual(sc.unmerge(merged, sc.strip_volatile(ours())), OPENDESK_VALUES)

    def test_new_version_replaces_old_entries(self):
        old = sc.merge(OPENDESK_VALUES, ours(head="old", module="1111"))
        new = sc.merge(sc.unmerge(old, sc.strip_volatile(ours(head="new", module="2222"))), ours(head="new", module="2222"))
        files = new["jitsi"]["web"]["extraFiles"]
        self.assertEqual(files, [{"path": sc.HEAD_FILE, "content": "new"}])
        self.assertEqual(new["jitsi"]["prosody"]["podAnnotations"]["synascriber/module"], "2222")
        self.assertEqual(len(new["jitsi"]["prosody"]["extraVolumes"]), 1)

    def test_unmerge_keeps_a_switch_that_guards_other_settings(self):
        override = {"global": {"releaseSecretsOverride": {"enabled": True, "extraEnvFrom": [
            {"secretRef": {"name": "synascriber-jicofo", "optional": True}}]}}}
        plain = {"jitsi": {"web": {"replicaCount": 1}}}
        alone = sc.merge(plain, {"jitsi": override})
        self.assertEqual(sc.unmerge(alone, {"jitsi": override}), plain)
        shared = {"jitsi": {"global": {"releaseSecretsOverride": {"enabled": True, "extraEnvFrom": [
            {"secretRef": {"name": "vault-secrets"}}]}}}}
        merged = sc.merge(shared, {"jitsi": override})
        self.assertEqual(len(merged["jitsi"]["global"]["releaseSecretsOverride"]["extraEnvFrom"]), 2)
        self.assertEqual(sc.unmerge(merged, {"jitsi": override}), shared)

    def test_merge_is_idempotent(self):
        once = sc.merge(OPENDESK_VALUES, ours())
        twice = sc.merge(sc.unmerge(once, sc.strip_volatile(ours())), ours())
        self.assertEqual(once, twice)


class YamlTest(unittest.TestCase):
    def test_strings_are_quoted_and_nesting_is_indented(self):
        text = sc.yaml({"a": {"b": ["x", {"c": "multi\nline", "d": True}]}, "e": 3})
        self.assertEqual(text, 'a:\n  b:\n    - "x"\n    - c: "multi\\nline"\n      d: true\ne: 3')

    def test_template_braces_survive_as_plain_text(self):
        text = sc.yaml({"url": "ws://x/transcribe?sessionId={{MEETING_ID}}"})
        self.assertIn("{{MEETING_ID}}", text)
        self.assertEqual(json.loads(text.split(": ", 1)[1]), "ws://x/transcribe?sessionId={{MEETING_ID}}")


class ClientsTest(unittest.TestCase):
    def test_clients_point_at_the_right_origins(self):
        t = {"synaplanUrl": "https://synaplan.example.org", "jitsiHost": "meet.example.org"}
        synaplan, button = sc.clients(t)
        self.assertEqual(synaplan["redirectUris"], ["https://synaplan.example.org/api/v1/auth/keycloak/callback"])
        self.assertFalse(synaplan["publicClient"])
        self.assertEqual(button["redirectUris"], ["https://meet.example.org" + sc.SILENT_PATH])
        self.assertEqual(button["attributes"]["pkce.code.challenge.method"], "S256")
        for client in (synaplan, button):
            audiences = [m for m in client["protocolMappers"] if m["protocolMapper"] == "oidc-audience-mapper"]
            self.assertEqual(audiences[0]["config"]["included.client.audience"], "synaplan")


if __name__ == "__main__":
    unittest.main()
