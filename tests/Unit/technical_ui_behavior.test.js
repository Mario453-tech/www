// Exercise real technical.js filters and card accordion with a minimal DOM fixture.
// Sprawdz prawdziwe filtry i akordeon technical.js w malej makiecie DOM.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const listeners = {};
const timers = [];
function control(value = '') {
    return {value, listeners: {}, addEventListener(event, fn) { this.listeners[event] = fn; }, change(value, event = 'change') {
        this.value = value;
        this.listeners[event]();
    }};
}
const search = control();
const teamSpec = control();
const teamStatus = control();
const candidateSpec = control();
const trainingSkill = control();
const incidentStatus = control();
const staffA = {dataset: {search: 'Anna Kowalska Geolog', spec: 'geologist', status: 'available'}, hidden: false, style: {}};
const staffB = {dataset: {search: 'Bob Nowak Technik', spec: 'well_technician', status: 'busy'}, hidden: false, style: {}};
const section = {classList: {contains(name) { return name === 'staff-section-title'; }}, hidden: false, style: {}};
const list = {hidden: false, style: {}, previousElementSibling: section, querySelectorAll() { return [staffA, staffB]; }};
const empty = {hidden: true, style: {}};
const candidateA = {dataset: {candidateSpec: 'geologist'}, hidden: false, style: {}};
const candidateB = {dataset: {candidateSpec: 'well_technician'}, hidden: false, style: {}};
const trainingA = {dataset: {trainingSkill: 'skill_safety'}, hidden: false, style: {}};
const trainingB = {dataset: {trainingSkill: 'skill_drilling'}, hidden: false, style: {}};

function details(group) {
    return {open: false, dataset: {techAccordion: group}, matches(s) { return s === 'details[data-tech-accordion]'; }, closest() { return document; }};
}
const first = details('team');
const second = details('team');
const well = details('wells');
const nested = {open: true, matches() { return false; }};
const nodes = {
    '[data-tech-team-search]': search, '[data-tech-team-spec]': teamSpec,
    '[data-tech-team-status]': teamStatus, '[data-tech-candidate-spec]': candidateSpec,
    '[data-tech-training-skill]': trainingSkill, '[data-tech-team-empty]': empty,
    '[data-tech-incident-status]': incidentStatus,
    '[data-tech-candidate-empty]': {hidden: true, style: {}}, '[data-tech-training-empty]': {hidden: true, style: {}},
};
const collections = {
    '.staff-card[data-search]': [staffA, staffB], '[data-tech-team-list]': [list],
    '.spec-need-card[data-candidate-spec]': [candidateA, candidateB],
    '.trn-program-card[data-training-skill]': [trainingA, trainingB],

    'details[data-tech-accordion]': [first, second, well],
};
const document = {
    readyState: 'loading', hidden: false,
    querySelector(selector) { return selector === 'details[open]' ? (collections[selector] || [])[0] || null : nodes[selector] || null; },
    querySelectorAll(selector) {
        if (selector.startsWith('[data-tech-team-search],')) {
            return [search, teamSpec, teamStatus, candidateSpec, trainingSkill];
        }
        return collections[selector] || [];
    },
    addEventListener(event, fn) { (listeners[event] ||= []).push(fn); },
};
const location = {search: '', pathname: '/technical.php', reloads: 0, reload() { this.reloads++; }};
const context = {window: {TECH_LANG: {}}, document, location,
    setInterval(fn) { timers.push(fn); }, URLSearchParams, console};
const source = fs.readFileSync(path.join(__dirname, '../../assets/js/technical.js'), 'utf8');
vm.runInNewContext(source, context);
listeners.DOMContentLoaded.forEach(fn => fn());
search.change('kowal', 'input');
assert.equal(staffA.hidden, false);
assert.equal(staffB.hidden, true);
assert.equal(staffB.style.display, 'none');
teamSpec.change('well_technician');
assert.equal(empty.hidden, false);
search.change('', 'input');
assert.equal(staffB.hidden, false);
teamStatus.change('available');
assert.equal(staffB.hidden, true);
candidateSpec.change('geologist');
assert.equal(candidateB.hidden, true);
assert.equal(candidateB.style.display, 'none');
trainingSkill.change('skill_safety');
assert.equal(trainingB.hidden, true);
incidentStatus.value = 'active';
first.open = second.open = well.open = true;
listeners.toggle.forEach(fn => fn({target: second}));
assert.equal(first.open, false);
assert.equal(second.open, true);
assert.equal(well.open, true);
listeners.toggle.forEach(fn => fn({target: nested}));
assert.equal(second.open, true);
assert.equal(nested.open, true);
collections['details[open]'] = [second];
timers[1]();
assert.equal(location.reloads, 0);
collections['details[open]'] = [];
timers[1]();
assert.equal(location.reloads, 0);
search.change('', 'input');
teamSpec.change('');
teamStatus.change('');
candidateSpec.change('');
trainingSkill.change('');
incidentStatus.value = '';
timers[1]();
assert.equal(location.reloads, 1);
console.log('technical UI behavior passed');
