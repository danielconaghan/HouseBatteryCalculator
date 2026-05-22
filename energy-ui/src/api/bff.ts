import type { Recommendation } from '../types/recommendation';

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const token = localStorage.getItem('auth_token');

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };

  const response = await fetch(path, { ...init, headers });

  if (!response.ok) {
    const body = await response.json().catch(() => ({})) as { error?: { message?: string } };
    throw new ApiError(response.status, body?.error?.message ?? response.statusText);
  }

  return response.json() as Promise<T>;
}

export const bff = {
  login: (email: string, password: string) =>
    request<{ data: { token: string } }>('/api/v1/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),

  logout: () =>
    request<{ data: { message: string } }>('/api/v1/auth/logout', {
      method: 'POST',
    }),

  getRecommendation: () =>
    request<{ data: Recommendation }>('/api/v1/recommendation'),
};
