import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'

export default function LandingPage() {
  const navigate = useNavigate()

  useEffect(() => {
    // LP未実装のため暫定的に /login へリダイレクト
    navigate('/login', { replace: true })
  }, [navigate])

  return null
}
