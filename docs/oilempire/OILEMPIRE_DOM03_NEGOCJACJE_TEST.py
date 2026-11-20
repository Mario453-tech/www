import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent
POLICY = json.loads((ROOT / "OILEMPIRE_DOM_POLICY_CANDIDATE.yaml").read_text(encoding="utf-8"))["policies"]["DOM03"]


def clamp(low, high, value):
    return min(high, max(low, value))


def group_demand(satisfaction, hr_skill, negotiator_present):
    n = len(satisfaction)
    if n < POLICY["group_min"]:
        raise ValueError("group too small")
    if any(not isinstance(x, int) or x < 0 or x > 100 for x in satisfaction):
        raise ValueError("invalid satisfaction")
    if not isinstance(hr_skill, int) or hr_skill < 0 or hr_skill > 10:
        raise ValueError("invalid HR skill")
    if not isinstance(negotiator_present, bool):
        raise ValueError("invalid negotiator flag")
    total = sum(satisfaction)
    size_points = (n - 3) // 3
    dissatisfaction_points = max(0, 60 * n - total) // (10 * n)
    negotiator_points = POLICY["negotiator_points_if_active"] if negotiator_present else 0
    advantage = clamp(POLICY["advantage_min"], POLICY["advantage_max"],
                      size_points + dissatisfaction_points - hr_skill - negotiator_points)
    return clamp(POLICY["raise_min_pct"], POLICY["raise_max_pct"],
                 POLICY["raise_base_pct"] + advantage)


class Dom03FormulaTest(unittest.TestCase):
    def test_documented_example(self):
        self.assertEqual(group_demand([40] * 9, 3, True), 3)

    def test_small_group_refused(self):
        with self.assertRaises(ValueError):
            group_demand([50, 50], 0, False)

    def test_three_workers_example(self):
        self.assertEqual(group_demand([50] * 3, 0, False), 5)

    def test_group_size_boundaries(self):
        self.assertEqual(group_demand([60] * 5, 0, False), 4)
        self.assertEqual(group_demand([60] * 6, 0, False), 5)

    def test_satisfaction_threshold_and_exact_floor(self):
        self.assertEqual(group_demand([60] * 3, 0, False), 4)
        self.assertEqual(group_demand([59] * 3, 0, False), 4)
        self.assertEqual(group_demand([50] * 3, 0, False), 5)

    def test_raise_clamps(self):
        self.assertEqual(group_demand([100] * 3, 10, True), 2)
        self.assertEqual(group_demand([0] * 30, 0, False), 12)

    def test_invalid_inputs(self):
        with self.assertRaises(ValueError):
            group_demand([50, 50, 101], 0, False)
        with self.assertRaises(ValueError):
            group_demand([50, 50, 50], 11, False)


if __name__ == "__main__":
    unittest.main(verbosity=2)
