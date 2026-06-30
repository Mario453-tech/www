const Map<String, String> technicalEn = {
  'technical.title': 'TECHNICAL DEPT',
  'technical.loading': 'Loading technical department...',
  'technical.error': 'Failed to load data. Please try again.',

  // Tabs
  'technical.tab.team': 'TEAM',
  'technical.tab.well_staff': 'WELL STAFF',
  'technical.tab.candidates': 'CANDIDATES',

  // Director
  'technical.director.label': 'Technical Manager',
  'technical.director.tenure': 'Tenure: {days} days',
  'technical.director.experience': 'Experience: {years} yrs',
  'technical.director.salary': 'Salary: {salary} PLN/mo',

  // Manager bonus chips
  'technical.bonus.time': '-{pct}% task time',
  'technical.bonus.cost': '-{pct}% task cost',
  'technical.bonus.org': 'Org. {skill}/10',

  // Engineers header
  'technical.engineers.header': 'Engineers ({count})',
  'technical.engineers.empty': 'No engineers in the team.',

  // Engineer status
  'technical.engineer.available': 'Available',
  'technical.engineer.busy': 'Busy',
  'technical.engineer.on_leave': 'On leave',

  // Engineer card fields
  'technical.engineer.skill': 'Skill',
  'technical.engineer.experience': '{years} yrs experience',
  'technical.engineer.salary': '{salary} PLN/mo',

  // Engineer actions
  'technical.engineer.assign_task': '► Assign task',
  'technical.engineer.fire': 'DISMISS',

  // Fire dialog
  'technical.fire.confirm_title': 'Dismiss engineer?',
  'technical.fire.confirm_body':
      'Are you sure you want to dismiss {name}? This action cannot be undone.',
  'technical.fire.ok': 'Dismiss',
  'technical.fire.success': '{name} has been dismissed.',
  'technical.fire.error': 'Failed to dismiss engineer.',
  'technical.fire.busy_error': 'Cannot dismiss an engineer while a task is in progress.',

  // Assign task sheet
  'technical.assign.title': 'Assign task — {name}',
  'technical.assign.select_task': 'Select task',
  'technical.assign.select_well': 'Select well',
  'technical.assign.no_tasks': 'No tasks available for this specialization.',
  'technical.assign.success': 'Task assigned. Time: {hours_min}–{hours_max} h.',
  'technical.assign.error': 'Failed to assign task.',
  'technical.assign.hours': '{min}–{max} h',
  'technical.assign.cost': '{min}–{max} PLN',
  'technical.assign.loading': 'Loading tasks...',
  'technical.assign.confirm': 'Assign',

  // Well staff tab
  'technical.well_staff.header': 'Well personnel',
  'technical.well_staff.empty': 'No wells with assigned personnel.',
  'technical.well_staff.operator': 'Operator',
  'technical.well_staff.technician': 'Technician',
  'technical.well_staff.missing': 'None',

  // Candidates tab
  'technical.candidates.header': 'Candidates ({count})',
  'technical.candidates.empty': 'No recruitment candidates.',
  'technical.candidate.expires': 'Expires in {hours} h',
  'technical.candidate.reviewed': 'Reviewed',
  'technical.candidate.not_reviewed': 'Not reviewed',
  'technical.candidate.experience': '{years} yrs exp.',
  'technical.candidate.salary': '{salary} PLN/mo',
};
