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
indexed_packages = re.findall(r"^\\| (P\\d{2}) [^|]*\\|", index, re.M)
check("D10 integrated index covers every work package once", len(indexed_packages) == len(cards) and set(indexed_packages) == set(cards))
chapters = files[:-1] + ["OILEMPIRE_AUDYT_GOTOWOSCI_PROGRAMOWANIA.md"]
check("D11 every chapter links to shared specification index", all("](README.md)" in (BASE / f).read_text(encoding="utf-8") for f in chapters))
check("D12 index links every canonical chapter", all(f"]({f})" in index for f in chapters))

print(json.dumps(results, ensure_ascii=False, indent=2))
raise SystemExit(1 if any(r["status"] == "FAIL" for r in results) else 0)
