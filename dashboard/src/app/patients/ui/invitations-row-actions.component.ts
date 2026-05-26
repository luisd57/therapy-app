import { Component, computed, input, output, Signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { InvitationStatus } from '../utils/invitation.model';

@Component({
  selector: 'app-invitations-row-actions',
  imports: [MatButtonModule, MatIconModule, MatMenuModule],
  templateUrl: './invitations-row-actions.component.html',
})
export class InvitationsRowActionsComponent {
  readonly status = input.required<InvitationStatus>();

  readonly resend = output<void>();
  readonly revoke = output<void>();

  protected readonly canResend: Signal<boolean> = computed(
    (): boolean => this.status() === 'pending' || this.status() === 'expired',
  );
  protected readonly canRevoke: Signal<boolean> = computed(
    (): boolean => this.status() === 'pending',
  );
  protected readonly hasActions: Signal<boolean> = computed(
    (): boolean => this.canResend() || this.canRevoke(),
  );

  protected onResend(): void {
    this.resend.emit();
  }

  protected onRevoke(): void {
    this.revoke.emit();
  }
}
