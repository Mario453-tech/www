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
check("D22 daily tax and current wealth separated", "company_wealth_state" in architecture and "client_key" in architecture)
check("D23 approved purchase channels", "Google Play Billing" in api and "Apple In-App Purchase" in api)


foundation = (BASE / "OILEMPIRE_KARTY_IMPLEMENTACJI_P01_P05.md").read_text(encoding="utf-8")
foundation_ids = re.findall(r"^### (P[0-9]{2}-F[0-9]{2})", foundation, re.M)
check("D24 sixteen unique foundation cards", len(foundation_ids) == len(set(foundation_ids)) == 16)
check("D25 every foundation package has its cards", all(sum(x.startswith(p + "-") for x in foundation_ids) == n for p,n in [("P01",3),("P02",4),("P03",3),("P04",3),("P05",3)]))
check("D26 plan links foundation cards", "OILEMPIRE_KARTY_IMPLEMENTACJI_P01_P05.md" in plan)
check("D27 mutation settlement shares transaction", "middleware nie zatwierdza oddzielnego rozliczenia przed mutacją" in architecture and "JEDNĄ transakcję" in foundation)
check("D28 rollback permits repeated content hash", "identyczny hash treści jest dozwolony" in foundation and "yaml_hash BINARY(32) UNIQUE" not in foundation)

print(json.dumps(results, ensure_ascii=False, indent=2))
raise SystemExit(1 if any(r["status"] == "FAIL" for r in results) else 0)
