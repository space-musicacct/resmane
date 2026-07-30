import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api, { getCsrfCookie, isApiError } from '../lib/axios'
import { useAuth } from '../auth/AuthContext'
import type { ValidationErrors } from '../types'
import PageCard from '../components/PageCard'
import { FormInput } from '../components/FormInput'
import PrimaryButton from '../components/PrimaryButton'
import ErrorAlert from '../components/ErrorAlert'

export default function LoginPage() {
  const navigate = useNavigate()
  const { setUser } = useAuth()

  const [loginId, setLoginId] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<ValidationErrors>({})
  const [generalError, setGeneralError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const handleSubmit = async () => {
    if (submitting) return
    setErrors({})
    setGeneralError('')
    setSubmitting(true)

    try {
      await getCsrfCookie()
      const res = await api.post('/login', { loginId, password })
      setUser(res.data.user)
      navigate('/home')
    } catch (err) {
      if (isApiError(err)) {
        const { status, data } = err.response
        if (status === 422 && data.errors) {
          setErrors(data.errors)
        } else {
          setGeneralError(data.message)
        }
      } else {
        setGeneralError('通信に失敗しました')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <PageCard title="ログイン">
      <ErrorAlert message={generalError} />

      <div className="space-y-5">
        <FormInput
          label="ログインID"
          value={loginId}
          onChange={setLoginId}
          required
          placeholder="半角英数・1〜15文字"
          errors={errors.loginId}
        />
        <FormInput
          label="パスワード"
          type="password"
          value={password}
          onChange={setPassword}
          required
          placeholder="8文字以上"
          errors={errors.password}
        />
      </div>

      <PrimaryButton label="ログイン" submitting={submitting} onClick={handleSubmit} className="mt-10" />

      <p className="mt-6 text-center text-sm">
        アカウントをお持ちでない方は
        <Link to="/register" className="ml-1 font-medium text-blue-600 underline">
          新規登録
        </Link>
      </p>
    </PageCard>
  )
}
