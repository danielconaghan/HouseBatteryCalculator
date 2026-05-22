import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { AuthContext } from '../auth/AuthContext'
import DashboardPage from './DashboardPage'
import * as bffModule from '../../api/bff'
import type { Recommendation } from '../../types/recommendation'

const mockNavigate = vi.fn()
const mockLogout = vi.fn()

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom')
  return { ...actual, useNavigate: () => mockNavigate }
})

const MOCK_REC: Recommendation = {
  action: 'CHARGE',
  target_charge_pct: 70,
  target_charge_kwh: 8.1,
  confidence: 0.85,
  reasoning: {
    forecast_generation_kwh: 5.2,
    forecast_consumption_kwh: 8.0,
    current_battery_kwh: 3.5,
    gap_kwh: 4.5,
    factors: ['High confidence forecast'],
  },
  generated_at: '2026-05-21T21:00:00+00:00',
  valid_until: '2026-05-22T05:30:00+00:00',
}

function renderDashboard() {
  return render(
    <MemoryRouter>
      <AuthContext.Provider value={{ token: 'fake-token', login: vi.fn(), logout: mockLogout }}>
        <DashboardPage />
      </AuthContext.Provider>
    </MemoryRouter>,
  )
}

describe('DashboardPage', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows loading spinner initially', () => {
    vi.spyOn(bffModule.bff, 'getRecommendation').mockReturnValue(new Promise(() => {}))
    renderDashboard()
    expect(screen.getByRole('status', { name: 'Loading' })).toBeInTheDocument()
  })

  it('renders recommendation card when data loads', async () => {
    vi.spyOn(bffModule.bff, 'getRecommendation').mockResolvedValue({ data: MOCK_REC })
    renderDashboard()

    await waitFor(() => {
      expect(screen.getByText('Charge tonight')).toBeInTheDocument()
      expect(screen.getByText('70%')).toBeInTheDocument()
    })
  })

  it('shows 503 error message when energy service unavailable', async () => {
    vi.spyOn(bffModule.bff, 'getRecommendation').mockRejectedValue(
      new bffModule.ApiError(503, 'Service unavailable'),
    )
    renderDashboard()

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('temporarily unavailable')
    })
  })

  it('shows generic error on unexpected failure', async () => {
    vi.spyOn(bffModule.bff, 'getRecommendation').mockRejectedValue(new Error('Network error'))
    renderDashboard()

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Failed to load recommendation')
    })
  })

  it('calls logout and navigates to /login on sign out', async () => {
    mockLogout.mockResolvedValue(undefined)
    vi.spyOn(bffModule.bff, 'getRecommendation').mockReturnValue(new Promise(() => {}))
    renderDashboard()

    await userEvent.click(screen.getByRole('button', { name: /sign out/i }))

    await waitFor(() => {
      expect(mockLogout).toHaveBeenCalled()
      expect(mockNavigate).toHaveBeenCalledWith('/login')
    })
  })
})
