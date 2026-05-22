import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { AuthContext } from './AuthContext'
import LoginPage from './LoginPage'
import { ApiError } from '../../api/bff'

const mockLogin = vi.fn()
const mockNavigate = vi.fn()

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom')
  return { ...actual, useNavigate: () => mockNavigate }
})

function renderLoginPage(loginFn = mockLogin) {
  return render(
    <MemoryRouter>
      <AuthContext.Provider value={{ token: null, login: loginFn, logout: vi.fn() }}>
        <LoginPage />
      </AuthContext.Provider>
    </MemoryRouter>,
  )
}

describe('LoginPage', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders email and password fields', () => {
    renderLoginPage()
    expect(screen.getByLabelText('Email')).toBeInTheDocument()
    expect(screen.getByLabelText('Password')).toBeInTheDocument()
  })

  it('calls login with form values and navigates on success', async () => {
    mockLogin.mockResolvedValue(undefined)
    renderLoginPage()

    await userEvent.type(screen.getByLabelText('Email'), 'daniel@example.com')
    await userEvent.type(screen.getByLabelText('Password'), 'secret')
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith('daniel@example.com', 'secret')
      expect(mockNavigate).toHaveBeenCalledWith('/')
    })
  })

  it('shows invalid credentials message on 401', async () => {
    mockLogin.mockRejectedValue(new ApiError(401, 'Unauthorized'))
    renderLoginPage()

    await userEvent.type(screen.getByLabelText('Email'), 'x@x.com')
    await userEvent.type(screen.getByLabelText('Password'), 'wrong')
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Invalid email or password')
    })
  })

  it('shows generic error on unexpected failure', async () => {
    mockLogin.mockRejectedValue(new Error('Network error'))
    renderLoginPage()

    await userEvent.type(screen.getByLabelText('Email'), 'x@x.com')
    await userEvent.type(screen.getByLabelText('Password'), 'pass')
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Something went wrong')
    })
  })

  it('disables the submit button while loading', async () => {
    mockLogin.mockReturnValue(new Promise<void>(() => {}))
    renderLoginPage()

    await userEvent.type(screen.getByLabelText('Email'), 'x@x.com')
    await userEvent.type(screen.getByLabelText('Password'), 'pass')
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /signing in/i })).toBeDisabled()
    })
  })
})
