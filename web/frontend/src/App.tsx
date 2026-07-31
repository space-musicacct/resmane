import { BrowserRouter, Routes, Route, useLocation } from 'react-router-dom'
import { AuthProvider, PrivateRoute, GuestRoute } from './auth/AuthContext'
import Header from './components/Header'
import Footer from './components/Footer'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import HomePage from './pages/HomePage'
import RecordListPage from './pages/RecordListPage'
import RecordNewPage from './pages/RecordNewPage'
import RecordDetailPage from './pages/RecordDetailPage'
import SettingsPage from './pages/SettingsPage'
import RecordEditPage from './pages/RecordEditPage'
import ReviewEditPage from './pages/ReviewEditPage'
import LandingPage from './pages/LandingPage'
import ErrorPage from './pages/errors/ErrorPage'

function AppContent() {
  const location = useLocation()

  const isAuthPage =
    location.pathname === '/' ||
    location.pathname === '/login' ||
    location.pathname === '/register'

  return (
    <>
      {!isAuthPage && <Header />}

      <main className={!isAuthPage ? 'pb-24' : ''}>
        <Routes>
          {/* LP */}
          <Route path="/" element={<LandingPage />} />

          {/* ゲスト専用 */}
          <Route path="/login" element={<GuestRoute><LoginPage /></GuestRoute>} />
          <Route path="/register" element={<GuestRoute><RegisterPage /></GuestRoute>} />

          {/* 認証必須 */}
          <Route path="/home" element={<PrivateRoute><HomePage /></PrivateRoute>} />
          <Route path="/records" element={<PrivateRoute><RecordListPage /></PrivateRoute>} />
          <Route path="/records/new" element={<PrivateRoute><RecordNewPage /></PrivateRoute>} />
          <Route path="/records/:id" element={<PrivateRoute><RecordDetailPage /></PrivateRoute>} />
          <Route path="/records/:id/:tab" element={<PrivateRoute><RecordDetailPage /></PrivateRoute>} />
          <Route path="/settings" element={<PrivateRoute><SettingsPage /></PrivateRoute>} />
          <Route path="/records/:id/edit" element={<PrivateRoute><RecordEditPage /></PrivateRoute>} />
          <Route
            path="/records/:recordId/reviews/:reviewId/edit"
            element={<PrivateRoute><ReviewEditPage /></PrivateRoute>}
          />

          {/* エラーページ */}
          {['403', '500'].map(code => (
            <Route
              key={code}
              path={`/${code}`}
              element={<ErrorPage statusCode={code} />}
            />
          ))}
          <Route path="*" element={<ErrorPage statusCode="404" />} />
        </Routes>
      </main>

      {!isAuthPage && <Footer />}
    </>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <AppContent />
      </AuthProvider>
    </BrowserRouter>
  )
}