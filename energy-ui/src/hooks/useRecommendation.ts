import { useEffect, useState } from 'react';
import { bff, ApiError } from '../api/bff';
import type { Recommendation } from '../types/recommendation';

interface State {
  recommendation: Recommendation | null;
  loading: boolean;
  error: string | null;
}

export function useRecommendation(): State {
  const [state, setState] = useState<State>({
    recommendation: null,
    loading: true,
    error: null,
  });

  useEffect(() => {
    let cancelled = false;

    bff
      .getRecommendation()
      .then(({ data }) => {
        if (!cancelled) setState({ recommendation: data, loading: false, error: null });
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          const message =
            err instanceof ApiError && err.status === 503
              ? 'The energy service is temporarily unavailable.'
              : 'Failed to load recommendation.';
          setState({ recommendation: null, loading: false, error: message });
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return state;
}
