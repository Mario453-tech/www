import json
import math
import unittest
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent
ACTIVE = yaml.safe_load((ROOT / "OILEMPIRE_GAME_BALANCE_V1.yaml").read_text(encoding="utf-8"))
CANDIDATE = json.loads((ROOT / "OILEMPIRE_DOM_POLICY_CANDIDATE.yaml").read_text(encoding="utf-8"))
WELL = ACTIVE["modules"]["extraction"]["params"]["extraction"]["strata"]
ROAD = ACTIVE["modules"]["logistics"]["params"]["logistics"]["road"]["incidents"]


def hazard_probability(base_probability, exposed_hours, base_hours, multiplier=1.0):
    if not 0 <= base_probability <= 1:
        raise ValueError("probability outside 0..1")
    if exposed_hours < 0 or base_hours <= 0 or multiplier < 0:
        raise ValueError("invalid exposure")
    return 1 - (1 - base_probability) ** (exposed_hours * multiplier / base_hours)


class Dom15CandidateTest(unittest.TestCase):
    def test_canonical_well_values_are_unchanged(self):
        expected = CANDIDATE["policies"]["DOM15"]["well_base_pct_by_stratum"]
        for stratum, percent in expected.items():
            self.assertEqual(WELL[stratum]["base_failure_risk_pct_per_tick"], percent)

    def test_canonical_road_hourly_value_is_unchanged(self):
        self.assertEqual(ROAD["base_chance_pct_per_trip_h"], 0.8)

    def test_daily_candidate_preserves_daily_well_probability(self):
        base_h = CANDIDATE["policies"]["DOM15"]["well_baseline_unit_h"]
        self.assertEqual(base_h, 24)
        for row in WELL.values():
            p = row["base_failure_risk_pct_per_tick"] / 100
            self.assertAlmostEqual(hazard_probability(p, base_h, base_h), p)
            self.assertLess(hazard_probability(p, 5 / 60, base_h), p)

    def test_road_short_trips_preserve_current_linear_quote(self):
        p = ROAD["base_chance_pct_per_trip_h"] / 100
        for minutes in (12, 18):
            candidate = hazard_probability(p, minutes / 60, 1)
            linear = p * minutes / 60
            self.assertLess(abs(candidate - linear), 0.00001)

    def test_partial_slots_do_not_change_total_road_hazard(self):
        p = ROAD["base_chance_pct_per_trip_h"] / 100
        split = (2, 5, 5)
        survive = math.prod(1 - hazard_probability(p, minutes / 60, 1) for minutes in split)
        self.assertAlmostEqual(1 - survive, hazard_probability(p, 12 / 60, 1))

    def test_naive_five_minute_well_tick_is_unacceptable(self):
        for row in WELL.values():
            p = row["base_failure_risk_pct_per_tick"] / 100
            self.assertGreater(hazard_probability(p, 288, 1), 0.4)


if __name__ == "__main__":
    unittest.main(verbosity=2)
