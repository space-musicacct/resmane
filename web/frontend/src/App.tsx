import { BrowserRouter, Routes, Route } from 'react-router-dom'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import HomePage from './pages/HomePage'
import RecordNewPage from './pages/RecordNewPage'
import RecordDetailPage from './pages/RecordDetailPage'
import SettingsPage from './pages/SettingsPage'
import RecordEditPage from './pages/RecordEditPage'
import ReviewEditPage from './pages/ReviewEditPage'

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        {/* S-001 ログイン */}
        <Route path="/login" element={<LoginPage />} />
        {/* S-002 ユーザー登録 */}
        <Route path="/register" element={<RegisterPage />} />
        {/* S-003 ホーム / 家計簿一覧 */}
        <Route path="/" element={<HomePage />} />
        {/* S-004 家計簿登録 */}
        <Route path="/records/new" element={<RecordNewPage />} />
        {/* S-005 家計簿詳細 / スレッド */}
        <Route path="/records/:id" element={<RecordDetailPage />} />
        {/* S-006 設定 */}
        <Route path="/settings" element={<SettingsPage />} />
        {/* S-007 家計簿編集 */}
        <Route path="/records/:id/edit" element={<RecordEditPage />} />
        {/* S-008 自己レビュー編集 */}
        <Route path="/records/:recordId/reviews/:reviewId/edit" element={<ReviewEditPage />} />
      </Routes>
    </BrowserRouter>
  )
}
