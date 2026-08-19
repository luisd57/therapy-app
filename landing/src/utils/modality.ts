import type { Modality } from '../types/api';

/** Online when the viewer is outside the practice zone - In-Person only happens in Mérida. */
export function preselectedModality(
  viewerZone: string,
  practiceZone: string,
): Modality | null {
  return viewerZone === practiceZone ? null : 'ONLINE';
}
