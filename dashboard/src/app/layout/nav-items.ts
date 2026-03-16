export interface NavItem {
  label: string;
  route: string;
  icon: string;
}

export const THERAPIST_NAV_ITEMS: NavItem[] = [
  { label: 'Appointments', route: '/appointments', icon: 'calendar_today' },
  { label: 'Schedule', route: '/schedule', icon: 'schedule' },
  { label: 'Patients', route: '/patients', icon: 'people' },
];

export const PATIENT_NAV_ITEMS: NavItem[] = [
  { label: 'My Appointments', route: '/appointments', icon: 'calendar_today' },
];
