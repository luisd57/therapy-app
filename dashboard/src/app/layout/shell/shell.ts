import { Component, inject, signal, OnInit, OnDestroy } from '@angular/core';
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
import { NAV_ITEMS } from '../nav-items';

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
  template: `
    <mat-sidenav-container class="shell-container">
      <mat-sidenav
        #sidenav
        [mode]="isMobile() ? 'over' : 'side'"
        [opened]="!isMobile()"
      >
        <mat-nav-list>
          @for (item of navItems; track item.route) {
            <a
              mat-list-item
              [routerLink]="item.route"
              routerLinkActive="active-link"
              (click)="isMobile() && sidenav.close()"
            >
              <mat-icon matListItemIcon>{{ item.icon }}</mat-icon>
              <span matListItemTitle>{{ item.label }}</span>
            </a>
          }
        </mat-nav-list>
      </mat-sidenav>

      <mat-sidenav-content>
        <mat-toolbar color="primary">
          @if (isMobile()) {
            <button mat-icon-button (click)="sidenav.toggle()">
              <mat-icon>menu</mat-icon>
            </button>
          }
          <span>Therapy Dashboard</span>
          <span class="spacer"></span>
          <span class="user-name">{{ authService.user()?.full_name }}</span>
          <button mat-icon-button (click)="onLogout()" matTooltip="Logout">
            <mat-icon>logout</mat-icon>
          </button>
        </mat-toolbar>
        <main class="content">
          <router-outlet />
        </main>
      </mat-sidenav-content>
    </mat-sidenav-container>
  `,
  styles: `
    .shell-container {
      height: 100vh;
    }

    mat-sidenav {
      width: 240px;
    }

    .spacer {
      flex: 1 1 auto;
    }

    .user-name {
      font-size: 0.875rem;
      margin-right: 0.5rem;
    }

    .content {
      padding: 1.5rem;
    }

    .active-link {
      --mat-list-active-indicator-color: var(--mat-sys-secondary-container);
    }
  `,
})
export class Shell implements OnInit, OnDestroy {
  readonly authService = inject(AuthService);
  private readonly breakpointObserver = inject(BreakpointObserver);
  private breakpointSub?: Subscription;

  readonly navItems = NAV_ITEMS;
  readonly isMobile = signal(false);

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
