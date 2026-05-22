import type { Recommendation, RecommendationAction } from '../../types/recommendation';

const ACTION_LABELS: Record<RecommendationAction, string> = {
  CHARGE: 'Charge tonight',
  PARTIAL_CHARGE: 'Partial charge',
  DO_NOT_CHARGE: 'No charge needed',
};

const ACTION_COLOURS: Record<RecommendationAction, string> = {
  CHARGE: 'bg-blue-100 text-blue-800',
  PARTIAL_CHARGE: 'bg-amber-100 text-amber-800',
  DO_NOT_CHARGE: 'bg-green-100 text-green-800',
};

function ConfidenceBar({ confidence }: { confidence: number }) {
  const pct = Math.round(confidence * 100);
  const colour = pct >= 70 ? 'bg-green-500' : pct >= 40 ? 'bg-amber-500' : 'bg-red-500';
  return (
    <div className="flex items-center gap-3">
      <div className="flex-1 bg-gray-200 rounded-full h-2">
        <div
          className={`h-2 rounded-full ${colour}`}
          style={{ width: `${pct}%` }}
          aria-hidden="true"
        />
      </div>
      <span className="text-sm font-medium text-gray-700 w-10 text-right">{pct}%</span>
    </div>
  );
}

export default function RecommendationCard({ rec }: { rec: Recommendation }) {
  const { action, target_charge_pct, target_charge_kwh, confidence, reasoning, generated_at } =
    rec;

  const generatedAt = new Date(generated_at).toLocaleString('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });

  return (
    <div className="bg-white rounded-xl shadow divide-y divide-gray-100">
      <div className="p-6 flex items-center justify-between">
        <span
          className={`inline-block px-3 py-1 rounded-full text-sm font-semibold ${ACTION_COLOURS[action]}`}
        >
          {ACTION_LABELS[action]}
        </span>
        <span className="text-sm text-gray-500">{generatedAt}</span>
      </div>

      <div className="px-6 py-4 grid grid-cols-2 gap-4">
        <div>
          <p className="text-xs text-gray-500 uppercase tracking-wide">Target charge</p>
          <p className="text-2xl font-bold text-gray-900 mt-1">{target_charge_pct}%</p>
          <p className="text-sm text-gray-500">{target_charge_kwh.toFixed(1)} kWh</p>
        </div>
        <div>
          <p className="text-xs text-gray-500 uppercase tracking-wide">Confidence</p>
          <div className="mt-3">
            <ConfidenceBar confidence={confidence} />
          </div>
        </div>
      </div>

      <div className="px-6 py-4 space-y-3">
        <h2 className="text-xs text-gray-500 uppercase tracking-wide">Reasoning</h2>
        <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
          <dt className="text-gray-500">Forecast solar</dt>
          <dd className="text-gray-900 font-medium text-right">
            {reasoning.forecast_generation_kwh.toFixed(1)} kWh
          </dd>
          <dt className="text-gray-500">Forecast consumption</dt>
          <dd className="text-gray-900 font-medium text-right">
            {reasoning.forecast_consumption_kwh.toFixed(1)} kWh
          </dd>
          <dt className="text-gray-500">Current battery</dt>
          <dd className="text-gray-900 font-medium text-right">
            {reasoning.current_battery_kwh.toFixed(1)} kWh
          </dd>
          <dt className="text-gray-500">Gap</dt>
          <dd className="text-gray-900 font-medium text-right">
            {reasoning.gap_kwh.toFixed(1)} kWh
          </dd>
        </dl>
        {reasoning.factors.length > 0 && (
          <ul className="mt-2 space-y-1">
            {reasoning.factors.map((factor, i) => (
              <li key={i} className="text-sm text-gray-600 flex items-start gap-2">
                <span className="text-gray-400 mt-0.5" aria-hidden="true">
                  ›
                </span>
                {factor}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
