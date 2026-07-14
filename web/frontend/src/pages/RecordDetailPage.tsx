import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api, { getCsrfCookie, isApiError } from '../lib/axios'
import PageCard from '../components/PageCard'
import LoadingScreen from '../components/LoadingScreen'
import TabSwitcher from '../components/TabSwitcher'

type KakeiboRecord = {
  id: number
  userId: number
  purchaseDate: string
  amountTypeId: number
  amountTypeName: string
  amount: number
  details: string
  categoryId: number
  categoryName: string
  createdAt: string
  updatedAt: string
}

type Tab = 'detail' | 'review'

type Message = {
  id: number
  sender: 'user' | 'ai'
  text: string
}

export default function RecordDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [record, setRecord] = useState<KakeiboRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState('')
  const [activeTab, setActiveTab] = useState<Tab>('detail')

  const [reviewComment, setReviewComment] = useState('')
  const [evaluation, setEvaluation] = useState(0)

  const [messages, setMessages] = useState<Message[]>([])
  const [input, setInput] = useState('')

  const sendMessage = async () => {
    if (!input.trim()) return
    const userMessage: Message = {
      id: Date.now(),
      sender: 'user',
      text: input,
    }
    setMessages((prev) => [...prev, userMessage])

    try {
      const res = await api.post('/chat', {
        message: input,
        recordId: id,
      })

      const aiMessage: Message = {
        id: Date.now() + 1,
        sender: 'ai',
        text: res.data.message,
      }

      setMessages((prev) => [...prev, aiMessage])
    } catch (err) {
      console.error(err)
    }
    setInput('')
  }

  const handleDelete = async () => {
    if (!confirm('この記録を削除しますか？')) return

    try {
      await getCsrfCookie()
      await api.delete(`/records/${id}`)
      navigate('/records')
    } catch (err) {
      if (isApiError(err)) {
        setErrorMessage(err.response.data.message)
      } else {
        setErrorMessage('削除に失敗しました')
      }
    }
  }

  useEffect(() => {
    api
      .get(`/records/${id}`)
      .then((res) => setRecord(res.data.data))
      .catch((err) => {
        if (isApiError(err)) {
          setErrorMessage(err.response.data.message)
        } else {
          setErrorMessage('通信に失敗しました')
        }
      })
      .finally(() => setLoading(false))
  }, [id])

  if (loading) return <LoadingScreen />

  if (errorMessage || !record) {
    return (
      <PageCard title="エラー">
        <p className="text-center text-red-600">
          {errorMessage || '家計簿レコードを表示できませんでした'}
        </p>

        <Link
          to="/records"
          className="mt-6 block text-center text-sm font-medium text-blue-600 underline"
        >
          一覧に戻る
        </Link>
      </PageCard>
    )
  }

  return (
    <PageCard title="家計簿詳細">
      <TabSwitcher
        tabs={[
          { key: 'detail', label: '家計簿詳細' },
          { key: 'review', label: '自己レビュー / AIフィードバック' },
        ]}
        activeTab={activeTab}
        onChange={(tab) => setActiveTab(tab as Tab)}
        className="mb-8"
      />

      {activeTab === 'detail' && (
        <>
          <div className="space-y-5">
            <DetailRow label="購入日" value={record.purchaseDate} />
            <DetailRow label="区分" value={record.amountTypeName} />
            <DetailRow
              label="金額"
              value={`${record.amount.toLocaleString()}円`}
              bold
            />
            <DetailRow label="カテゴリー" value={record.categoryName} />
            <DetailRow label="内容" value={record.details} />
          </div>

          <div className="mt-8 flex gap-3">
            <Link
              to={`/records/${record.id}/edit`}
              className="flex-1 rounded-2xl border border-green-500 py-3 text-center font-bold text-green-600 hover:bg-green-50"
            >
              編集
            </Link>

            <button
              type="button"
              onClick={handleDelete}
              className="flex-1 rounded-2xl border border-red-400 py-3 font-bold text-red-600 hover:bg-red-50"
            >
              削除
            </button>
          </div>
        </>
      )}

      {activeTab === 'review' && (
        <div className="flex flex-col gap-6">
          {/* 自己レビュー投稿フォーム */}
          <div className="space-y-5">
            <div>
              <label className="block text-sm font-medium mb-1">評価</label>
              <div className="flex">
                {[1, 2, 3, 4, 5].map((star) => (
                  <button
                    key={star}
                    type="button"
                    onClick={() => setEvaluation(star)}
                    className={`flex-1 text-5xl ${
                      star <= evaluation
                        ? 'text-yellow-400'
                        : 'text-gray-300 hover:text-yellow-300'
                    }`}
                  >
                    ★
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">自己レビュー</label>
              <textarea
                value={reviewComment}
                onChange={(e) => setReviewComment(e.target.value)}
                placeholder="この支出について振り返りを入力してください（250文字以内）"
                rows={14}
                maxLength={250}
                className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              />
            </div>

            <button
              type="button"
              className="w-full rounded-2xl border border-blue-500 py-3 font-bold text-blue-600 hover:bg-blue-50"
              onClick={() => {
                console.log({ evaluation, reviewComment })
              }}
            >
              保存
            </button>
          </div>

          {/* AIフィードバック チャットUI */}
          <div className="flex min-h-[460px] flex-col rounded-2xl border bg-white shadow">
            <div className="h-[350px] overflow-y-auto space-y-4 p-4">
              {messages.length === 0 && (
                <div className="py-10 text-center text-sm text-gray-400">
                  AIフィードバックはまだありません
                </div>
              )}
              {messages.map((msg) => (
                <div
                  key={msg.id}
                  className={`flex ${msg.sender === 'user' ? 'justify-end' : 'justify-start'}`}
                >
                  <div
                    className={`max-w-[75%] rounded-2xl px-4 py-3 ${msg.sender === 'user' ? 'bg-blue-200' : 'bg-gray-100'}`}
                  >
                    {msg.text}
                  </div>
                </div>
              ))}
            </div>

            <div className="border-t p-3 flex flex-col gap-2">
              <textarea
                value={input}
                onChange={(e) => {
                  setInput(e.target.value)
                  e.target.style.height = 'auto'
                  e.target.style.height = `${e.target.scrollHeight}px`
                }}
                placeholder="メッセージを入力"
                rows={1}
                className="w-full resize-none rounded-xl border px-4 py-2 outline-none focus:border-blue-500"
              />
              <button
                onClick={sendMessage}
                className="self-end rounded-full bg-blue-500 px-5 py-2 text-white"
              >
                送信
              </button>
            </div>
          </div>
        </div>
      )}
    </PageCard>
  )
}

function DetailRow({
  label,
  value,
  bold,
}: {
  label: string
  value: string
  bold?: boolean
}) {
  return (
    <div className="flex items-start">
      <p className="w-28 shrink-0 text-sm font-medium">{label}</p>
      <p className={bold ? 'text-lg font-bold' : 'text-sm'}>{value}</p>
    </div>
  )
}
