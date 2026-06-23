import { useState } from 'react'
import api, { getCsrfCookie } from '../lib/axios'
import { useAuth } from '../auth/AuthContext'

export default function LogoutButton() {
  const { setUser } = useAuth()
  const [submitting, setSubmitting] = useState(false)

  const handleLogout = async () => {
    if (submitting) return
    setSubmitting(true)

    try {
      await getCsrfCookie()
      await api.post('/logout')
    } catch {
      // ignore
    } finally {
      setUser(null)
    }
  }

  return (
    <button
      onClick={handleLogout}
      disabled={submitting}
      className="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600 hover:bg-red-50 disabled:opacity-50"
    >
      {submitting ? 'ログアウト中...' : 'ログアウト'}
    </button>
  )
}
