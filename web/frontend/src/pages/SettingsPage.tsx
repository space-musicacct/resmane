import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api, { getCsrfCookie, isApiError } from '../lib/axios'
import { useAuth } from '../auth/AuthContext'
import type { ValidationErrors } from '../types'
import type { SettingLimit } from '../types/record'
import PageCard from '../components/PageCard'
import TabSwitcher from '../components/TabSwitcher'
import { FormInput } from '../components/FormInput'
import ErrorAlert from '../components/ErrorAlert'
import PrimaryButton from '../components/PrimaryButton'

type Tab = 'standard' | 'user'

const UPPER_LIMIT_TYPES = [
  { id: 1, name: '割合' },
  { id: 2, name: '固定額' },
]

export default function SettingsPage() {
  const navigate = useNavigate()
  const { setUser } = useAuth()
  const [activeTab, setActiveTab] = useState<Tab>('standard')

  return (
    <PageCard title="設定">
      <TabSwitcher
        tabs={[
          { key: 'standard' as Tab, label: '基準値設定' },
          { key: 'user' as Tab, label: 'ユーザー編集' },
        ]}
        activeTab={activeTab}
        onChange={setActiveTab}
        className="mb-8"
      />

      {activeTab === 'standard' && <LimitSection />}
      {activeTab === 'user' && <UserSection setUser={setUser} navigate={navigate} />}
    </PageCard>
  )
}

function LimitSection() {
  const [upperLimitTypeId, setUpperLimitTypeId] = useState('')
  const [maxValue, setMaxValue] = useState('')
  const [aveMonthlyIncome, setAveMonthlyIncome] = useState('')
  const [loading, setLoading] = useState(true)
  const [errors, setErrors] = useState<ValidationErrors>({})
  const [generalError, setGeneralError] = useState('')
  const [successMessage, setSuccessMessage] = useState('')
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    api.get('/settings/limit')
      .then((res) => {
        const data: SettingLimit | null = res.data.data
        if (data) {
          setUpperLimitTypeId(String(data.upperLimitTypeId))
          setMaxValue(String(data.maxValue))
          setAveMonthlyIncome(data.aveMonthlyIncome != null ? String(data.aveMonthlyIncome) : '')
        }
      })
      .catch((err) => {
        if (isApiError(err)) {
          setGeneralError(err.response.data.message)
        } else {
          setGeneralError('設定の取得に失敗しました')
        }
      })
      .finally(() => setLoading(false))
  }, [])

  const handleSave = async () => {
    if (submitting) return
    setErrors({})
    setGeneralError('')
    setSuccessMessage('')
    setSubmitting(true)

    try {
      await getCsrfCookie()
      await api.put('/settings/limit', {
        upperLimitTypeId: Number(upperLimitTypeId),
        maxValue: Number(maxValue),
        aveMonthlyIncome: upperLimitTypeId === '1' && aveMonthlyIncome
          ? Number(aveMonthlyIncome)
          : null,
      })
      setSuccessMessage('保存しました')
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

  if (loading) return null

  return (
    <div className="space-y-5">
      <ErrorAlert message={generalError} />
      {successMessage && (
        <p className="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-600">
          {successMessage}
        </p>
      )}

      <div>
        <label className="block text-sm font-medium mb-1">
          上限区分<span className="ml-[3px] text-red-500">*</span>
        </label>
        <select
          value={upperLimitTypeId}
          onChange={(e) => setUpperLimitTypeId(e.target.value)}
          className="w-full rounded-lg border border-gray-400 bg-white px-3 py-2 text-sm"
        >
          <option value="">選択してください</option>
          {UPPER_LIMIT_TYPES.map((t) => (
            <option key={t.id} value={t.id}>{t.name}</option>
          ))}
        </select>
        {errors.upperLimitTypeId?.map((msg, i) => (
          <p key={i} className="mt-1 text-xs text-red-600">{msg}</p>
        ))}
      </div>

      <FormInput
        label={upperLimitTypeId === '1' ? '上限割合 (%)' : '上限額'}
        type="number"
        value={maxValue}
        onChange={setMaxValue}
        required
        placeholder="1以上の整数"
        errors={errors.maxValue}
      />

      {upperLimitTypeId === '1' && (
        <FormInput
          label="平均月収"
          type="number"
          value={aveMonthlyIncome}
          onChange={setAveMonthlyIncome}
          required
          placeholder="1以上の整数"
          errors={errors.aveMonthlyIncome}
        />
      )}

      <PrimaryButton label="保存" submitting={submitting} onClick={handleSave} className="mt-6" />
    </div>
  )
}

function UserSection({
  setUser,
  navigate,
}: {
  setUser: (u: import('../types').User | null) => void
  navigate: (path: string) => void
}) {
  const { user } = useAuth()

  const [loginId, setLoginId] = useState('')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [currentPassword, setCurrentPassword] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')

  const [errors, setErrors] = useState<ValidationErrors>({})
  const [generalError, setGeneralError] = useState('')
  const [successMessage, setSuccessMessage] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const [withdrawPassword, setWithdrawPassword] = useState('')
  const [withdrawError, setWithdrawError] = useState('')
  const [withdrawing, setWithdrawing] = useState(false)

  useEffect(() => {
    if (user) {
      setLoginId(user.loginId)
      setName(user.name)
      setEmail(user.email)
    }
  }, [user])

  const handleUpdate = async () => {
    if (submitting) return
    setErrors({})
    setGeneralError('')
    setSuccessMessage('')
    setSubmitting(true)

    try {
      await getCsrfCookie()

      const body: Record<string, string> = { loginId, name, email }
      if (password) {
        body.currentPassword = currentPassword
        body.password = password
        body.passwordConfirmation = passwordConfirmation
      }

      const res = await api.put('/user', body)
      setUser(res.data.data)
      setCurrentPassword('')
      setPassword('')
      setPasswordConfirmation('')
      setSuccessMessage('更新しました')
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

  const handleWithdraw = async () => {
    if (!confirm('本当に退会しますか？この操作は取り消せません。')) return
    if (withdrawing) return
    setWithdrawError('')
    setWithdrawing(true)

    try {
      await getCsrfCookie()
      await api.delete('/user', { data: { currentPassword: withdrawPassword } })
      setUser(null)
      navigate('/login')
    } catch (err) {
      if (isApiError(err)) {
        setWithdrawError(err.response.data.message)
      } else {
        setWithdrawError('退会に失敗しました')
      }
    } finally {
      setWithdrawing(false)
    }
  }

  return (
    <div className="space-y-6">
      <ErrorAlert message={generalError} />
      {successMessage && (
        <p className="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-600">
          {successMessage}
        </p>
      )}

      <div className="space-y-5">
        <FormInput
          label="ログインID"
          value={loginId}
          onChange={setLoginId}
          required
          maxLength={15}
          placeholder="半角英数・15文字以内"
          errors={errors.loginId}
        />
        <FormInput
          label="名前"
          value={name}
          onChange={setName}
          required
          maxLength={50}
          placeholder="50文字以内"
          errors={errors.name}
        />
        <FormInput
          label="メールアドレス"
          value={email}
          onChange={setEmail}
          required
          placeholder="hogehoge@example.com"
          errors={errors.email}
        />

        <hr className="border-gray-300" />
        <p className="text-xs text-gray-500">パスワードを変更する場合のみ入力してください</p>

        <FormInput
          label="現在のパスワード"
          type="password"
          value={currentPassword}
          onChange={setCurrentPassword}
          placeholder="現在のパスワード"
          errors={errors.currentPassword}
        />
        <FormInput
          label="新しいパスワード"
          type="password"
          value={password}
          onChange={setPassword}
          placeholder="8文字以上"
          errors={errors.password}
        />
        <FormInput
          label="新しいパスワード（確認）"
          type="password"
          value={passwordConfirmation}
          onChange={setPasswordConfirmation}
          placeholder="上の欄と同じパスワード"
          errors={errors.passwordConfirmation}
        />
      </div>

      <PrimaryButton label="更新" submitting={submitting} onClick={handleUpdate} />

      <hr className="border-gray-300 mt-10" />

      <div className="space-y-3">
        <p className="text-sm font-bold text-red-600">退会</p>
        <FormInput
          label="現在のパスワード"
          type="password"
          value={withdrawPassword}
          onChange={setWithdrawPassword}
          required
          placeholder="退会するには現在のパスワードを入力"
        />
        {withdrawError && <p className="text-xs text-red-600">{withdrawError}</p>}
        <button
          type="button"
          onClick={handleWithdraw}
          disabled={withdrawing}
          className="w-full rounded-2xl border border-red-400 py-3 font-bold text-red-600 hover:bg-red-50 disabled:opacity-50"
        >
          {withdrawing ? '退会処理中...' : '退会'}
        </button>
      </div>
    </div>
  )
}
