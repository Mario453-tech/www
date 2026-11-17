import json
import re
from pathlib import Path
import yaml

BASE = Path(__file__).resolve().parent
results = []
def check(name, condition, detail=""):
    results.append({"check": name, "status": "PASS" if condition else "FAIL", "detail": detail})

class UniqueLoader(yaml.SafeLoader):
    pass

def unique_mapping(loader, node, deep=False):
    mapping = {}
    for key_node, value_node in node.value:
        key = loader.construct_object(key_node, deep=deep)
        if key in mapping:
            raise ValueError(f"Duplicate YAML key: {key} at line {key_node.start_mark.line + 1}")
        mapping[key] = loader.construct_object(value_node, deep=deep)
    return mapping

UniqueLoader.add_constructor(yaml.resolver.BaseResolver.DEFAULT_MAPPING_TAG, unique_mapping)
try:
    balance = yaml.load((BASE / "OILEMPIRE_GAME_BALANCE_V1.yaml").read_text(encoding="utf-8"), Loader=UniqueLoader)
    check("D01 unique YAML keys", True)
except Exception as error:
    check("D01 unique YAML keys", False, str(error))
    balance = None

if balance:
    expected = {"core", "staff", "legal", "extraction", "incidents", "logistics", "market", "banking", "economy", "monetization", "social", "onboarding", "admin", "refining"}
    check("D02 balance module coverage", set(balance["modules"]) == expected)
    check("D03 source document exists", (BASE / balance["meta"]["source_document"]).is_file())

files = ["OILEMPIRE_PLAN_PROGRAMOWANIA.md", "OILEMPIRE_STANDARD_MODULU_WDROZENIE.md", "OILEMPIRE_BEZPIECZENSTWO_SILNIKA.md", "OILEMPIRE_API_SPECYFIKACJA_TECHNICZNA.md", "OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md", "OILEMPIRE_PANEL_ADMINA_SPECYFIKACJA.md", "README.md"]
files.insert(-1, "OILEMPIRE_KARTY_IMPLEMENTACJI_P01_P05.md")
missing = []
for name in files:
    text = (BASE / name).read_text(encoding="utf-8")
    for target in re.findall(r"\]\((OILEMPIRE_[^\s)#]+)", text):
        if not (BASE / target).is_file():
            missing.append(f"{name}: {target}")
check("D04 canonical document links", not missing, "; ".join(missing))
plan = (BASE / files[0]).read_text(encoding="utf-8")
cards = re.findall(r"^### (P\d{2})\.", plan, re.M)
check("D05 exactly 25 unique work packages", len(cards) == 25 and len(set(cards)) == 25)
expected_keys = set(balance["modules"]) if balance else set()
rows = re.findall(r"^\| (P\d{2}) \| [^|]+ \| ([^|]+) \| [^|]+ \| ([^|]+) \|$", plan, re.M)
keys = {key.strip() for _, key, _ in rows if key.strip() != "—"}
check("D06 all balance roots mapped in plan", bool(expected_keys) and keys == expected_keys)
bad = []
for package, _, deps in rows:
    for dependency in re.findall(r"P\d{2}", deps):
        if dependency not in cards or int(dependency[1:]) >= int(package[1:]):
            bad.append(f"{package} -> {dependency}")
check("D07 dependencies point to earlier existing package", not bad, "; ".join(bad))
security = (BASE / files[2]).read_text(encoding="utf-8")
ids = re.findall(r"^\| (S\d{2}) \|", security, re.M)
check("D08 eighteen unique security acceptance scenarios", len(ids) == len(set(ids)) == 18)
check("D09 UTF-8 without BOM", all(not (BASE / f).read_bytes().startswith(bytes.fromhex("efbbbf")) for f in files))

index = (BASE / "README.md").read_text(encoding="utf-8")
indexed_packages = re.findall(r"^[|] (P[0-9]{2}) [^|]*[|]", index, re.M)
check("D10 integrated index covers every work package once", len(indexed_packages) == len(cards) and set(indexed_packages) == set(cards))
chapters = files[:-1] + ["OILEMPIRE_AUDYT_GOTOWOSCI_PROGRAMOWANIA.md"]
check("D11 every chapter links to shared specification index", all("](README.md)" in (BASE / f).read_text(encoding="utf-8") for f in chapters))
check("D12 index links every canonical chapter", all(f"]({f})" in index for f in chapters))


standard = (BASE / "OILEMPIRE_STANDARD_MODULU_WDROZENIE.md").read_text(encoding="utf-8")
manifest_text = standard.split("```yaml" + chr(10), 1)[1].split("```", 1)[0]
manifest = yaml.load(manifest_text, Loader=UniqueLoader)
check("D13 sample manifest belongs to Staff without self dependency", manifest.get("key") == "staff" and all(d["module"] != "staff" for d in manifest.get("requires", {}).get("all", [])))
panel = (BASE / "OILEMPIRE_PANEL_ADMINA_SPECYFIKACJA.md").read_text(encoding="utf-8")
check("D14 panel exposes draining runtime", "runtime draining" in panel and "stan i dane identyczne jak przed wyłączeniem" not in panel)
api = (BASE / "OILEMPIRE_API_SPECYFIKACJA_TECHNICZNA.md").read_text(encoding="utf-8")
check("D15 API preserves process balance version", "kontynuacja procesu używa utrwalonej wersji startowej" in api)
architecture = (BASE / "OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md").read_text(encoding="utf-8")
check("D16 short transactions do not reclaim pending", "Nie przejmuje się niezatwierdzonego pending" in architecture and "fencing_token" in architecture)
check("D17 rejected balance cannot activate", "Rejected jest końcowy" in architecture)
check("D18 money serialization is uniform", 'dziesiętny string' in api and 'wszystkie kwoty w najmniejszej jednostce są dziesiętnymi stringami' in security)


check("D19 all twelve decisions registered", len(re.findall(r"^[|] OPEN[0-9]{2} [|]", plan, re.M)) == 12)
check("D20 refresh recovery is two minutes", "2 minuty" in api and "2 minuty" in security)
check("D21 approved recovery objective", "RTO ≤60 minut" in architecture)
check("D22 documented tax and wealth separation", "Snapshot dobowy 00:00 UTC służy podatkowi" in architecture and "company_wealth_state" in architecture and "bieżący wynik tego samego serwisu" in architecture)
check("D23 approved purchase channels", "Google Play Billing" in api and "Apple In-App Purchase" in api)


foundation = (BASE / "OILEMPIRE_KARTY_IMPLEMENTACJI_P01_P05.md").read_text(encoding="utf-8")
foundation_ids = re.findall(r"^### (P[0-9]{2}-F[0-9]{2})", foundation, re.M)
check("D24 sixteen unique foundation cards", len(foundation_ids) == len(set(foundation_ids)) == 16)
check("D25 every foundation package has its cards", all(sum(x.startswith(p + "-") for x in foundation_ids) == n for p,n in [("P01",3),("P02",4),("P03",3),("P04",3),("P05",3)]))
check("D26 plan links foundation cards", "OILEMPIRE_KARTY_IMPLEMENTACJI_P01_P05.md" in plan)
check("D27 mutation settlement shares transaction", "middleware nie zatwierdza oddzielnego rozliczenia przed mutacją" in architecture and "JEDNĄ transakcję" in foundation)
check("D28 rollback permits repeated content hash", "identyczny hash treści jest dozwolony" in foundation and "yaml_hash BINARY(32) UNIQUE" not in foundation)


login_row = next((line for line in foundation.splitlines() if line.startswith("**POST /api/v1/auth/login:**")), "")
verify_row = next((line for line in foundation.splitlines() if line.startswith("**POST /auth/2fa/verify:**")), "")
check("D29 challenge secret issued before verify", "202 z challenge_id, challenge_secret," in login_row and "challenge_secret" in verify_row and "challenge_secret" in api)
check("D30 access token session revocation contract", "personal_access_tokens (Sanctum)" in foundation and "api_session_id FK" in foundation and "Każdy Bearer request" in foundation and "Każde żądanie Bearer" in api)
check("D31 refresh reuse precedence", "także zmieniony payload pod tym samym kluczem" in foundation and "Dopiero dla niewykorzystanego tokenu" in api)
op_row = next((line for line in foundation.splitlines() if line.startswith("**operations:**")), "")
check("D32 current operation lease schema", all(field in op_row for field in ["operation_id PK", "current_attempt_no", "fencing_token", "lease_owner", "lease_until", "state_version"]))
check("D33 global balance singleton", "**balance_current:** singleton world_id PK" in foundation and "active_version_id" in foundation and "SELECT FOR UPDATE tego rekordu" in foundation)
rank_rows = {parts[1].strip(): parts[2].strip() for line in architecture.splitlines() if line.startswith("|") for parts in [line.split("|")] if len(parts) >= 4 and parts[1].strip().isdigit()}
check("D34 lock order covers mutable roots", all(str(rank) in rank_rows and table in rank_rows[str(rank)] for rank,table in [(1,"operation_idempotency"),(5,"operations"),(11,"company_settlement_state"),(12,"due_events"),(21,"fund_reservations")]))
reservation_row = next((line for line in foundation.splitlines() if line.startswith("**fund_reservations:**")), "")
check("D35 reservation unique columns declared", "reservation_kind VARCHAR(32) NOT NULL" in reservation_row and "UNIQUE(operation_id, account_id, reservation_kind)" in reservation_row)
check("D36 available balance guards transfer", "amount_minor <= available_minor" in foundation and "reservation_id i amount równego pełnej kwocie" in foundation)
check("D37 activation outbox atomic insert", "Rekord outbox balance.activated zapisany w tej samej transakcji" in foundation)
check("D38 slippage not sweeper exclusive", "| Cena ropy, matching limit, slippage, wydarzenia światowe |" not in architecture and "| Zużycie płynności NPC i okno slippage sprzedaży spot |" in architecture)
shield_rule = next((line for line in architecture.splitlines() if "Nowo zarejestrowana korporacja posiada" in line), "")
check("D39 shield uses undiscounted assets and empty storage", "100% systemowej wyceny" in shield_rule and "początkowa ropa = 0" in shield_rule and "× 0,70" not in shield_rule and "do rozstrzygnięcia produktowego" not in shield_rule)


contract = yaml.load((BASE / "OILEMPIRE_KONTRAKTY_FUNDAMENTU.yaml").read_text(encoding="utf-8"), Loader=UniqueLoader)
schemas = contract["components"]["schemas"]
flow_errors = []
for binding in contract["x-flow-bindings"]:
    producer, consumer = schemas[binding["producer"]], schemas[binding["consumer"]]
    for field in binding["fields"]:
        if field not in producer["required"] or field not in consumer["required"] or producer["properties"].get(field) != consumer["properties"].get(field):
            flow_errors.append(field)
check("D40 machine contract satisfies challenge flow", not flow_errors, ",".join(flow_errors))
storage_errors = []
for name, table in contract["x-storage-contracts"].items():
    for key in table.get("unique", []) + [table.get("primary", [])]:
        storage_errors += [name + "." + field for field in key if field not in table["columns"]]
check("D41 machine storage keys reference declared columns", not storage_errors, ",".join(storage_errors))
check("D42 refresh policy ordering is explicit", contract["x-session-policy"]["refresh_priority"] == ["invalid_session","identical_replay","consumed_token_revoke","unused_token_key_conflict","rotate"])

print(json.dumps(results, ensure_ascii=False, indent=2))
raise SystemExit(1 if any(r["status"] == "FAIL" for r in results) else 0)
