import { Component, computed, input, InputSignal, Signal } from '@angular/core';
import { MatChip } from '@angular/material/chips';
import { InvitationStatus } from '../utils/invitation.model';

@Component({
  selector: 'app-invitation-status-chip',
  imports: [MatChip],
  templateUrl: './invitation-status-chip.component.html',
  styleUrl: './invitation-status-chip.component.scss',
})
export class InvitationStatusChipComponent {
  readonly status: InputSignal<InvitationStatus> = input.required<InvitationStatus>();

  protected readonly label: Signal<string> = computed((): string => {
    switch (this.status()) {
      case 'pending':
        return 'Pending';
      case 'expired':
        return 'Expired';
      case 'used':
        return 'Used';
      case 'revoked':
        return 'Revoked';
    }
  });
}
