import { Component, Signal, computed, inject } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { BreakpointObserver, BreakpointState, Breakpoints } from '@angular/cdk/layout';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatListModule } from '@angular/material/list';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { toSignal } from '@angular/core/rxjs-interop';
import { map } from 'rxjs';
import { AuthService } from '../auth/data-access/auth.service';
import { NavItem, PATIENT_NAV_ITEMS, THERAPIST_NAV_ITEMS } from './nav-items';

@Component({
  selector: 'app-shell',
  imports: [
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatSidenavModule,
    MatToolbarModule,
    MatListModule,
    MatIconModule,
    MatButtonModule,
    MatTooltipModule,
  ],
  templateUrl: './shell.component.html',
  styleUrl: './shell.component.scss',
})
export class ShellComponent {
  readonly authService: AuthService = inject(AuthService);
  private readonly breakpointObserver: BreakpointObserver = inject(BreakpointObserver);

  readonly navItems: Signal<NavItem[]> = computed((): NavItem[] =>
    this.authService.user()?.role === 'ROLE_PATIENT' ? PATIENT_NAV_ITEMS : THERAPIST_NAV_ITEMS,
  );
  readonly isMobile: Signal<boolean> = toSignal(
    this.breakpointObserver
      .observe([Breakpoints.Handset])
      .pipe(map((result: BreakpointState): boolean => result.matches)),
    { initialValue: false },
  );

  onLogout(): void {
    this.authService.logout();
  }
}
