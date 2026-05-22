import { createContext, useCallback, useState, type ReactNode } from 'react';
import { bff } from '../../api/bff';

interface AuthContextValue {
  token: string | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(
    () => localStorage.getItem('auth_token'),
  );

  const login = useCallback(async (email: string, password: string) => {
    const { data } = await bff.login(email, password);
    localStorage.setItem('auth_token', data.token);
    setToken(data.token);
  }, []);

  const logout = useCallback(async () => {
    try {
      await bff.logout();
    } finally {
      localStorage.removeItem('auth_token');
      setToken(null);
    }
  }, []);

  return (
    <AuthContext.Provider value={{ token, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
