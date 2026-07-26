import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api, { getCsrfCookie } from '../lib/axios'
import PageCard from '../components/PageCard'
import TabSwitcher from '../components/TabSwitcher'
import { FormInput } from '../components/FormInput'
// import { Message } from 'postcss'


// type KakeiboRecord = {
//   id: number
//   userId: number
//   purchaseDate: string
//   amountTypeId: number
//   amountTypeName: string
//   amount: number
//   details: string
//   categoryId: number
//   categoryName: string
//   createdAt: string
//   updatedAt: string
// }

type Tab = 'standard' | 'user'


export default function Setting() {
  const [loginId, setLoginId] = useState('')
  const [monthlyBudget, setMonthlyBudget] = useState('')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')

  const errors = {
    loginId: [] as string[],
    name: [] as string[],
    email: [] as string[],
    password: [] as string[],
    passwordConfirmation: [] as string[],
  }

  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState<Tab>('standard')

  const handleSave = async () => {
    try{
      await getCsrfCookie()

      await api.post('/settings', {
        monthly_budget: monthlyBudget, 
      })

      alert('保存しました')
    }catch{
      alert('失敗しました')
    }
  }

  const handleUpdate = async () => {
    try{
      await getCsrfCookie()

      await api.put('/user', {
        name,
        email
      })

      alert('更新しました')
    }catch{
      alert('失敗しました')
    }
  }

  const handleWithdraw = async () => {
    if (!confirm('本当に退会しますか?')) return
    try {
      await getCsrfCookie()
      await api.delete(`/user`)

      alert('退会しました')

      navigate('/login')
    } catch {
      alert('退会できませんでした')
    }
  }

  return (
    <PageCard title="設定">
      <TabSwitcher
        tabs={[
          { key: 'standard', label: '基準値設定' },
          { key: 'user', label: 'ユーザー編集', },
        ]}
        activeTab={activeTab}
        onChange={setActiveTab}
        className="mb-8"
      />

      {activeTab === 'standard' && (
        <div className="space-y-6">
          <div>
            <label className="block font-bold mb-2">
              1ヶ月の予算
            </label>
            <input
              type="number"
              value={monthlyBudget}
              onChange={(e) => setMonthlyBudget(e.target.value)}
              className="w-full rounded-xl border p-3"
              placeholder="5000"
            />
          </div>
          <button 
          onClick={handleSave}
          className="w-full rounded-xl bg-green-500 py-3 font-bold text-white">保存</button>
        </div>
      )}

      {activeTab === 'user' && (
        <div className="space-y-6">
          <div className="space-y-5">
            <FormInput
            label="ログインID"
            value={loginId}
            onChange={setLoginId}
            required
            maxLength={15}
            placeholder="半角英数・15文字"
            errors={errors.loginId}
            />

            <FormInput
            label="名前"
            value={name}
            onChange={setName}
            required
            maxLength={50}
            placeholder="50文字"
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
            label="パスワード(確認)"
            type="password"
            value={passwordConfirmation}
            onChange={setPasswordConfirmation}
            required
            placeholder="上の欄と同じパスワードを入力してください"
            errors={errors.passwordConfirmation}
            />
            </div>
  
          <button 
          onClick={handleUpdate}
          className="w-full rounded-xl bg-blue-500 py-3 font-bold text-white">
            更新
          </button>
        </div>
      )}
      <button
        onClick={handleWithdraw}
        className="mt-10 w-full rounded-2xl border border-red-400 py-3 font-bold text-red-600 hover:bg-red-50"
      >
        退会
      </button>

    </PageCard >
  )
}
