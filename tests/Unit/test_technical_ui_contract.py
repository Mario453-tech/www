"""Technical department view contracts, without database fixtures."""
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]


def source(name):
    return (ROOT / name).read_text(encoding='utf-8')


class TechnicalUiContractTest(unittest.TestCase):
    def test_navigation_groups_and_svg(self):
        text = source('templates/views/technical/main.php')
        for category in ('team', 'operations', 'safety', 'report'):
            self.assertIn("'" + category + "'", text)
        self.assertIn('aria-current="page"', text)
        self.assertIn('/assets/img/icons/technical/nav/', text)
        self.assertIn('technical-nav-groups', text)
        for icon in ('people', 'operations', 'safety', 'report', 'team', 'staff',
                     'candidates', 'training', 'tasks', 'wells', 'production',
                     'infrastructure', 'incidents'):
            self.assertTrue((ROOT / f'assets/img/icons/technical/nav/{icon}.svg').is_file())

    def test_navigation_fills_available_width_without_horizontal_scrolling(self):
        css = source('assets/css/technical.css')
        self.assertIn('.t-wrap .technical-nav-groups { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));', css)
        self.assertIn('.t-wrap .technical-nav-subgroups { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 130px), 1fr));', css)
        self.assertIn('.t-wrap .technical-nav-group, .t-wrap .technical-nav-subgroup {', css)
        self.assertIn('justify-content: center;', css)

    def test_team_filter_and_card_details_preserve_actions(self):
        text = source('templates/views/technical/tabs/team.php')
        self.assertIn('data-tech-team-search', text)
        self.assertIn('data-tech-team-status', text)
        self.assertIn('data-tech-team-spec', text)
        self.assertIn('data-tech-accordion="team"', text)
        for key in ('assign_task', 'fire_engineer', 'staff_id', '_token', 'tech-mnt'):
            self.assertIn(key, text)

    def test_candidate_filter_and_real_svg(self):
        text = source('templates/views/technical/tabs/candidates.php')
        self.assertIn('data-tech-candidate-spec', text)
        self.assertIn('assets/icons/technical/candidates/', text)
        for key in ('request_recruitment', 'review_candidate', 'cancel_recruitment', '_token'):
            self.assertIn(key, text)
        for icon in ('geo', 'res', 'pen', 'fsu', 'prd', 'rur', 'drl', 'bhp', 'hse', 'mnt', 'hub', 'wop', 'rou', 'wte'):
            self.assertTrue((ROOT / f'assets/icons/technical/candidates/{icon}.svg').is_file())
        for icon in ('land', 'offshore', 'production', 'condition', 'reserve',
                     'pressure', 'depth', 'transport', 'task'):
            self.assertTrue((ROOT / f'assets/icons/technical/wells/{icon}.svg').is_file())

    def test_training_filter_and_actions(self):
        text = source('templates/views/technical/tabs/trainings.php')
        self.assertIn('data-tech-training-skill', text)
        for key in ('start_training', 'program_id', 'staff_id', '_token'):
            self.assertIn(key, text)

    def test_locale_keys(self):
        keys = ('nav_team', 'nav_operations', 'nav_safety', 'nav_report',
                'team_search', 'team_filter_spec', 'team_filter_status', 'filter_all',
                'team_no_results', 'candidate_filter_spec', 'candidate_no_results',
                'training_filter_skill', 'training_no_results', 'course_level',
                'pipe_estimated_after_loss', 'well_parameters_transport',
                'pipe_parameters_costs', 'well_open_link')
        for lang in ('pl', 'en', 'de'):
            text = source(f'lang/{lang}/technical.php')
            for key in keys:
                self.assertRegex(text, r"['\"]technical\." + key + r"['\"]\s*=>")

    def test_accordion_ignores_nested_details(self):
        text = source('assets/js/technical.js')
        self.assertIn('data-tech-accordion', text)
        self.assertIn('event.target', text)
        self.assertIn('peer.open = false', text)
        self.assertIn('closest(', text)

    def test_all_repeated_technical_disclosures_are_scoped_to_their_module(self):
        for tab, group, name, selector in (
            ('team', 'team', 'technical-staff', 'technical-staff-details'),
            ('wells', 'wells', 'technical-wells', 'well-card'),
            ('infra', 'pipelines', 'technical-pipelines', 'pipe-card'),
            ('candidates', 'candidate-review', 'technical-candidate-review', 'task-assign-toggle'),
            ('incidents', 'incident-panels', 'technical-incident-panels', 'incident-panel'),
        ):
            text = source(f'templates/views/technical/tabs/{tab}.php')
            self.assertIn(f'data-tech-accordion="{group}"', text, tab)
            self.assertIn(f'name="{name}"', text, tab)
            self.assertIn(selector, text, tab)
        incidents = source('templates/views/technical/tabs/incidents.php')
        self.assertEqual(incidents.count('class="g-card incident-panel'), 2)
        self.assertEqual(incidents.count('data-tech-accordion="incident-panels"'), 2)
        self.assertEqual(incidents.count('name="technical-incident-panels"'), 2)
        self.assertNotIn('incident-panel--logistics" open', incidents)
        candidates = source('templates/views/technical/tabs/candidates.php')
        self.assertNotIn("<details <?= !$reviewed ? 'open' : '' ?>>", candidates)

    def test_disclosure_headers_wrap_instead_of_overflowing_narrow_cards(self):
        css = source('assets/css/technical.css')
        self.assertIn('.t-wrap .well-card-hdr, .t-wrap .pipe-hdr { flex-wrap: wrap;', css)
        self.assertIn('.t-wrap .well-card-badges, .t-wrap .pipe-hdr .badge { max-width: 100%;', css)
        self.assertIn('.t-wrap .w-stat-lbl, .t-wrap .well-detail-lbl { flex-wrap: wrap; overflow-wrap: anywhere;', css)

    def test_production_rows_reflow_instead_of_overflowing_on_phones(self):
        css = source('assets/css/technical.css')
        self.assertIn('.t-wrap .cols-prod { grid-template-columns: repeat(2, minmax(0, 1fr));', css)
        self.assertIn('.t-wrap .cols-prod.data-list-head { display: none;', css)
        self.assertIn('content: attr(data-label)', css)
        view = source('templates/views/technical/tabs/prod.php')
        self.assertIn('data-label="<?= htmlspecialchars(t(', view)

    def test_disclosure_indicator_uses_supplied_svg(self):
        css = source('assets/css/technical.css')
        self.assertIn('/assets/icons/technical/menu/rozwin.svg', css)
        self.assertNotIn("content: '⌄'", css)
        self.assertTrue((ROOT / 'assets/icons/technical/menu/rozwin.svg').is_file())


if __name__ == '__main__':
    unittest.main()
