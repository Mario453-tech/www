import json
import subprocess
import sys
from pathlib import Path

BASE = Path(__file__).resolve().parent
CARD = "OILEMPIRE_KARTY_IMPLEMENTACJI_P01_P05.md"
ARCH = "OILEMPIRE_ARCHITEKTURA_SYSTEMOWA.md"
API = "OILEMPIRE_API_SPECYFIKACJA_TECHNICZNA.md"
CASES = [

    ("D43", "OILEMPIRE_KARTY_IMPLEMENTACJI_P06_P13.md", "### P06-F01", "### MISSING-F01"),
    ("D48", "OILEMPIRE_KARTY_IMPLEMENTACJI_P06_P13.md", "ReleaseAvailability", "MissingRelease"),
    ("D49", ARCH, "Cred --> B2BRep", "Cred --> B2BRep" + chr(10) + "    Cred --> ContractRep"),
    ("D50", ARCH, "| 14 | Staff:", "| 99 | Staff:"),
    ("D52", "OILEMPIRE_KARTY_IMPLEMENTACJI_P06_P13.md", "finance/technical/hr/legal/logistics", "finance/api/v1/technical/hr/legal/logistics"),
    ("D22", ARCH, "Snapshot dobowy 00:00 UTC służy podatkowi", "Snapshot dzienny jest nieokreslony"),
    ("D29", CARD, "202 z challenge_id, challenge_secret,", "202 z challenge_id,"),
    ("D30", CARD, "api_session_id FK", "unmapped_session_id"),
    ("D31", API, "Dopiero dla niewykorzystanego tokenu", "Nieustalona kolejnosc"),
    ("D32", CARD, "current_attempt_no", "missing_attempt_number"),
    ("D33", CARD, "**balance_current:** singleton world_id PK", "**balance_current:** version_id UNIQUE"),
    ("D34", ARCH, "| 12 | due_events:", "| 99 | due_events:"),
    ("D35", CARD, "reservation_kind VARCHAR(32) NOT NULL", "missing_kind"),
    ("D36", CARD, "amount_minor <= available_minor", "amount_minor <= balance_minor"),
    ("D37", CARD, "Rekord outbox balance.activated zapisany w tej samej transakcji", "Outbox utworzony po commicie"),
    ("D38", ARCH, "| Zużycie płynności NPC i okno slippage sprzedaży spot |", "| Stan rynku |"),
    ("D39", ARCH, "100% systemowej wyceny", "70% systemowej wyceny"),
]

def verify():
    run = subprocess.run([sys.executable, str(BASE / "OILEMPIRE_WERYFIKATOR_DOKUMENTACJI.py")], cwd=BASE, capture_output=True, text=True, encoding="utf-8")
    return run.returncode, json.loads(run.stdout)

def main():
    code, baseline = verify()
    if code:
        print("Baseline failed")
        return 1
    detected = 0
    for check_id, name, old, new in CASES:
        path = BASE / name
        original = path.read_bytes()
        try:
            text = original.decode("utf-8")
            if old not in text:
                raise ValueError("Mutation target missing: " + check_id)
            path.write_text(text.replace(old, new, 1), encoding="utf-8", newline="")
            exit_code, result = verify()
            caught = exit_code != 0 and any(row["check"].startswith(check_id + " ") and row["status"] == "FAIL" for row in result)
            print(check_id + ": " + ("DETECTED" if caught else "MISSED"))
            detected += int(caught)
        finally:
            path.write_bytes(original)
    contract_path = BASE / "OILEMPIRE_KONTRAKTY_FUNDAMENTU.yaml"
    original_contract = contract_path.read_bytes()
    for check_id in ["D40", "D41", "D42"]:
        try:
            contract = json.loads(original_contract.decode("utf-8"))
            if check_id == "D40":
                contract["components"]["schemas"]["ChallengeData"]["required"].remove("challenge_secret")
            elif check_id == "D41":
                contract["x-storage-contracts"]["fund_reservations"]["columns"].remove("reservation_kind")
            else:
                contract["x-session-policy"]["refresh_priority"][2:4] = ["unused_token_key_conflict", "consumed_token_revoke"]
            contract_path.write_text(json.dumps(contract), encoding="utf-8")
            exit_code, result = verify()
            caught = exit_code != 0 and any(row["check"].startswith(check_id + " ") and row["status"] == "FAIL" for row in result)
            print(check_id + ": " + ("DETECTED" if caught else "MISSED"))
            detected += int(caught)
        finally:
            contract_path.write_bytes(original_contract)
    domain_path = BASE / "OILEMPIRE_KONTRAKTY_P06_P13.yaml"
    domain_bytes = domain_path.read_bytes()
    for check_id in ["D44", "D45", "D46", "D47", "D51"]:
        try:
            domain = json.loads(domain_bytes.decode("utf-8"))
            if check_id == "D44":
                domain["x-cards"].pop()
            elif check_id == "D45":
                domain["x-balance-roots"].append("unknown_module")
            elif check_id == "D46":
                domain["x-blockers"].pop()
            elif check_id == "D47":
                domain["x-storage-contracts"]["employee_availability"]["columns"].remove("employee_id")
            else:
                first = next(iter(domain["paths"]))
                domain["paths"]["/api/v1/missing-contract-route"] = domain["paths"].pop(first)
            domain_path.write_text(json.dumps(domain), encoding="utf-8")
            exit_code, result = verify()
            caught = exit_code != 0 and any(row["check"].startswith(check_id + " ") and row["status"] == "FAIL" for row in result)
            print(check_id + ": " + ("DETECTED" if caught else "MISSED"))
            detected += int(caught)
        finally:
            domain_path.write_bytes(domain_bytes)
    code, restored = verify()
    total = len(CASES) + 8
    print(f"BASELINE {len(baseline)} PASS; MUTATIONS {detected}/{total} DETECTED; RESTORED " + ("PASS" if code == 0 else "FAIL"))
    return 0 if detected == total and code == 0 else 1

if __name__ == "__main__":
    raise SystemExit(main())
