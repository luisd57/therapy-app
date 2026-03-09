export interface NavItem {
  label: string;
  route: string;
  icon: string;
}

export const NAV_ITEMS: NavItem[] = [
  { label: 'Appointments', route: '/appointments', icon: 'calendar_today' },
  { label: 'Schedule', route: '/schedule', icon: 'schedule' },
  { label: 'Patients', route: '/patients', icon: 'people' },
];
