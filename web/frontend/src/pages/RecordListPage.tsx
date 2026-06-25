import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api, { getCsrfCookie, isApiError } from '../lib/axios'
import PageCard from '../components/PageCard'
import LoadingScreen from '../components/LoadingScreen'
import ErrorAlert from '../components/ErrorAlert'
import { cardClass } from '../components/Card'
import PrimaryButton from '../components/PrimaryButton'

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

type Meta = {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

type Summary = {
  totalIncome: number
  totalExpense: number
}

type RecordsResponse = {
  data: KakeiboRecord[]
  meta: Meta
  summary: Summary
}

export default function RecordListPage() {
  const [records, setRecords] = useState<KakeiboRecord[]>([])
  const [meta, setMeta] = useState<Meta | null>(null)
  const [summary, setSummary] = useState<Summary | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [sortOrder, setSortOrder] = useState<'desc' | 'asc'>('desc')

  const handleDelete = async (recordId: number) => {
    if (!confirm('この記録を削除しますか？')) return
    try {
      await getCsrfCookie()
      await api.delete(`/records/${recordId}`)
      setRecords((prev) => prev.filter((r) => r.id !== recordId))
    } catch (err) {
      if (isApiError(err)) {
        setError(err.response.data.message)
      } else {
        setError('削除に失敗しました')
      }
    }
  }

  useEffect(() => {
    setLoading(true)
    api.get<RecordsResponse>('/records', { params: { page, sort: sortOrder } })
      .then((res) => {
        setRecords(res.data.data)
        setMeta(res.data.meta)
        setSummary(res.data.summary)
      })
      .catch((err) => {
        if (isApiError(err)) {
          setError(err.response.data.message)
        } else {
          setError('データの取得に失敗しました')
        }
      })
      .finally(() => setLoading(false))
  }, [page, sortOrder])

  if (loading && page === 1) return <LoadingScreen />

  return (
    <PageCard title="家計簿一覧">
      <ErrorAlert message={error} />

      {summary && (
        <div className="mb-6 flex gap-4 text-sm">
          <div className="flex-1 rounded-xl bg-blue-50 px-4 py-3 text-center">
            <p className="text-gray-500">収入</p>
            <p className="text-lg font-bold text-blue-600">
              {summary.totalIncome.toLocaleString()}円
            </p>
          </div>
          <div className="flex-1 rounded-xl bg-red-50 px-4 py-3 text-center">
            <p className="text-gray-500">支出</p>
            <p className="text-lg font-bold text-red-600">
              {summary.totalExpense.toLocaleString()}円
            </p>
          </div>
        </div>
      )}

      <PrimaryButton to="/records/new" label="+ 家計簿を登録" className="mb-4" />

      {records.length > 0 && (
        <div className="mb-4 flex justify-end">
          <select
            value={sortOrder}
            onChange={(e) => { setSortOrder(e.target.value as 'desc' | 'asc'); setPage(1) }}
            className="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs"
          >
            <option value="desc">新しい順</option>
            <option value="asc">古い順</option>
          </select>
        </div>
      )}

      {records.length === 0 && !loading ? (
        <p className="py-10 text-center text-sm text-gray-500">
          まだ記録がありません
        </p>
      ) : (
        <ul className="space-y-3">
          {records.map((record) => (
            <li key={record.id} className={cardClass}>
              <Link to={`/records/${record.id}`} className="block hover:opacity-80">
                <div className="flex items-center justify-between">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 text-xs text-gray-500">
                      <span>{record.purchaseDate}</span>
                      <span className="rounded bg-gray-100 px-1.5 py-0.5">
                        {record.categoryName}
                      </span>
                    </div>
                    <p className="mt-1 truncate text-sm">
                      {record.details || '（詳細なし）'}
                    </p>
                  </div>
                  <p className={`ml-4 shrink-0 text-base font-bold ${
                    record.amountTypeId === 1 ? 'text-red-600' : 'text-blue-600'
                  }`}>
                    {record.amountTypeId === 1 ? '-' : '+'}
                    {record.amount.toLocaleString()}円
                  </p>
                </div>
              </Link>
              <div className="mt-2 flex justify-end gap-2">
                <Link
                  to={`/records/${record.id}/edit`}
                  className="rounded border border-green-500 px-3 py-1 text-xs text-green-600 hover:bg-green-50"
                >
                  編集
                </Link>
                <button
                  onClick={() => handleDelete(record.id)}
                  className="rounded border border-red-300 px-3 py-1 text-xs text-red-600 hover:bg-red-50"
                >
                  削除
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {meta && meta.lastPage > 1 && (
        <div className="mt-6 flex items-center justify-center gap-4">
          <button
            onClick={() => setPage((p) => p - 1)}
            disabled={page <= 1}
            className="rounded-lg border border-gray-300 px-4 py-2 text-sm disabled:opacity-30"
          >
            前へ
          </button>
          <span className="text-sm text-gray-600">
            {meta.currentPage} / {meta.lastPage}
          </span>
          <button
            onClick={() => setPage((p) => p + 1)}
            disabled={page >= meta.lastPage}
            className="rounded-lg border border-gray-300 px-4 py-2 text-sm disabled:opacity-30"
          >
            次へ
          </button>
        </div>
      )}
    </PageCard>
  )
}
