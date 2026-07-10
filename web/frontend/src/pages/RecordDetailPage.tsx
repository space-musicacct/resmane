import { useEffect, useState, type ChangeEvent } from 'react'
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

type ReviewForm = {
  postDate: string
  evaluation: number
  reviewContent: string
  categoryId: string
}

type ReviewTextFieldName = 'postDate' | 'reviewContent' | 'categoryId'

type Tab = 'detail' | 'review'

export default function RecordDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [record, setRecord] = useState<KakeiboRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState('')
  const [activeTab, setActiveTab] = useState<Tab>('detail')

  const [reviewForm, setReviewForm] = useState<ReviewForm>({
    postDate: '',
    evaluation: 0,
    reviewContent: '',
    categoryId: '',
  })

  const handleReviewChange = (
    e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    const name = e.target.name as ReviewTextFieldName
    const value = e.target.value

    setReviewForm((prev) => ({
      ...prev,
      [name]: value,
    }))
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
        <div className="space-y-5">
          <InputRow
            label="投稿日"
            name="postDate"
            type="date"
            value={reviewForm.postDate}
            onChange={handleReviewChange}
          />

          <InputRow
            label="家計簿ID"
            name="kakeiboId"
            type="text"
            value={String(record.id)}
            disabled
          />

          <StarRatingRow
            label="評価"
            value={reviewForm.evaluation}
            onChange={(value) => {
              setReviewForm((prev) => ({
                ...prev,
                evaluation: value,
              }))
            }}
          />

          <TextAreaRow
            label="内容"
            name="reviewContent"
            value={reviewForm.reviewContent}
            onChange={handleReviewChange}
            placeholder="この支出について振り返りを入力してください"
          />

          <SelectRow
            label="カテゴリー"
            name="categoryId"
            value={reviewForm.categoryId}
            onChange={handleReviewChange}
            options={[
              { value: '', label: '選択してください' },
              { value: '1', label: '食費' },
              { value: '2', label: '日用品' },
              { value: '3', label: '交通費' },
              { value: '4', label: '娯楽' },
              { value: '5', label: 'その他' },
            ]}
          />

          <div className="mt-8 flex gap-3">
            <button
              type="button"
              className="flex-1 rounded-2xl border border-blue-500 py-3 font-bold text-blue-600 hover:bg-blue-50"
              onClick={() => {
                console.log(reviewForm)
              }}
            >
              保存
            </button>
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

function InputRow({
  label,
  name,
  type,
  value,
  onChange,
  placeholder,
  disabled,
}: {
  label: string
  name: string
  type: string
  value: string
  onChange?: (e: ChangeEvent<HTMLInputElement>) => void
  placeholder?: string
  disabled?: boolean
}) {
  return (
    <div className="flex items-start">
      <label className="w-28 shrink-0 text-sm font-medium">{label}</label>

      <input
        name={name}
        type={type}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        disabled={disabled}
        className="flex-1 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100"
      />
    </div>
  )
}

function TextAreaRow({
  label,
  name,
  value,
  onChange,
  placeholder,
}: {
  label: string
  name: string
  value: string
  onChange: (e: ChangeEvent<HTMLTextAreaElement>) => void
  placeholder?: string
}) {
  return (
    <div className="flex items-start">
      <label className="w-28 shrink-0 text-sm font-medium">{label}</label>

      <textarea
        name={name}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        rows={4}
        className="flex-1 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
      />
    </div>
  )
}

function StarRatingRow({
  label,
  value,
  onChange,
}: {
  label: string
  value: number
  onChange: (value: number) => void
}) {
  return (
    <div className="flex items-start">
      <p className="w-28 shrink-0 text-sm font-medium">{label}</p>

      <div className="flex gap-1">
        {[1, 2, 3, 4, 5].map((star) => (
          <button
            key={star}
            type="button"
            onClick={() => onChange(star)}
            className={
              star <= value
                ? 'text-2xl text-yellow-400'
                : 'text-2xl text-gray-300 hover:text-yellow-300'
            }
          >
            ★
          </button>
        ))}
      </div>
    </div>
  )
}

function SelectRow({
  label,
  name,
  value,
  onChange,
  options,
}: {
  label: string
  name: string
  value: string
  onChange: (e: ChangeEvent<HTMLSelectElement>) => void
  options: {
    value: string
    label: string
  }[]
}) {
  return (
    <div className="flex items-start">
      <label className="w-28 shrink-0 text-sm font-medium">{label}</label>

      <select
        name={name}
        value={value}
        onChange={onChange}
        className="w-40 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </div>
  )
}