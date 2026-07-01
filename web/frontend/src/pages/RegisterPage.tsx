import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api, { getCsrfCookie, isApiError } from '../lib/axios'
import { useAuth } from '../auth/AuthContext'
import type { ValidationErrors } from '../types'
import PageCard from '../components/PageCard'
import { FormInput } from '../components/FormInput'
import SubmitButton from '../components/SubmitButton'
import ErrorAlert from '../components/ErrorAlert'

export default function RegisterPage() {
  const navigate = useNavigate()
  const { setUser } = useAuth()

  const [loginId, setLoginId] = useState('')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
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
      const res = await api.post('/register', {
        loginId,
        email,
        name,
        password,
        passwordConfirmation,
      })
      setUser(res.data.user)
      navigate('/')
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
    <PageCard title="新規登録">
      <ErrorAlert message={generalError} />

      <div className="space-y-5">
        <FormInput
          label="ログインID"
          value={loginId}
          onChange={setLoginId}
          required
          maxLength={15}
          placeholder="半角英数・1〜15文字"
          errors={errors.loginId}
        />
        <FormInput
          label="名前"
          value={name}
          onChange={setName}
          required
          maxLength={50}
          placeholder="1〜50文字"
          errors={errors.name}
        />
        <FormInput
          label="メールアドレス"
          type="email"
          value={email}
          onChange={setEmail}
          required
          placeholder="hogehoge@example.com"
          errors={errors.email}
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
        <FormInput
          label="パスワード（確認）"
          type="password"
          value={passwordConfirmation}
          onChange={setPasswordConfirmation}
          required
          placeholder="上の欄で入力したパスワードと同じものを入力"
          errors={errors.passwordConfirmation}
        />
      </div>

      <SubmitButton label="登録" submitting={submitting} onClick={handleSubmit} />

      <p className="mt-6 text-center text-sm">
        アカウントをお持ちの方は
        <Link to="/login" className="ml-1 font-medium text-blue-600 underline">
          ログイン
        </Link>
      </p>
    </PageCard>
  )
}
