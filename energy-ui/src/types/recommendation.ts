export type RecommendationAction = 'CHARGE' | 'PARTIAL_CHARGE' | 'DO_NOT_CHARGE';

export interface Reasoning {
  forecast_generation_kwh: number;
  forecast_consumption_kwh: number;
  current_battery_kwh: number;
  gap_kwh: number;
  factors: string[];
}

export interface Recommendation {
  action: RecommendationAction;
  target_charge_pct: number;
  target_charge_kwh: number;
  confidence: number;
  reasoning: Reasoning;
  generated_at: string;
  valid_until: string;
}
