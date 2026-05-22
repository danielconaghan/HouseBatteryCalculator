import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { useRecommendation } from '../../hooks/useRecommendation';
import RecommendationCard from './RecommendationCard';

export default function DashboardPage() {
  const { logout } = useAuth();
  const navigate = useNavigate();
  const { recommendation, loading, error } = useRecommendation();

  async function handleLogout() {
    await logout();
    navigate('/login');
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white border-b border-gray-200">
        <div className="max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
          <h1 className="text-base font-semibold text-gray-900">Solar Energy</h1>
          <button
            onClick={handleLogout}
            className="text-sm text-gray-500 hover:text-gray-900 transition-colors"
          >
            Sign out
          </button>
        </div>
      </header>

      <main className="max-w-2xl mx-auto px-4 py-8">
        {loading && (
          <div className="flex justify-center items-center py-20">
            <div
              className="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"
              aria-label="Loading"
              role="status"
            />
          </div>
        )}

        {error && (
          <div role="alert" className="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
            <p className="text-red-700 font-medium">{error}</p>
            <p className="text-sm text-red-500 mt-1">Try refreshing the page.</p>
          </div>
        )}

        {recommendation && <RecommendationCard rec={recommendation} />}
      </main>
    </div>
  );
}
