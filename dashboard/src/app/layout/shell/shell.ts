import { Component, WritableSignal, inject, signal, OnInit, OnDestroy } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { BreakpointObserver, Breakpoints } from '@angular/cdk/layout';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatListModule } from '@angular/material/list';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { Subscription } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { NAV_ITEMS, NavItem } from '../nav-items';

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
  templateUrl: './shell.html',
  styleUrl: './shell.scss',
})
export class Shell implements OnInit, OnDestroy {
  readonly authService: AuthService = inject(AuthService);
  private readonly breakpointObserver: BreakpointObserver = inject(BreakpointObserver);
  private breakpointSub?: Subscription;

  readonly navItems: NavItem[] = NAV_ITEMS;
  readonly isMobile: WritableSignal<boolean> = signal(false);

  ngOnInit(): void {
    this.breakpointSub = this.breakpointObserver
      .observe([Breakpoints.Handset])
      .subscribe((result) => this.isMobile.set(result.matches));
  }

  ngOnDestroy(): void {
    this.breakpointSub?.unsubscribe();
  }

  onLogout(): void {
    this.authService.logout();
  }
}
