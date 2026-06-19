import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'

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

type KakeiboRecordResponse = {
  data: KakeiboRecord
}

type ApiErrorResponse = {
  message: string
  errors: Record<string, string[]>
}

export default function RecordDetailPage() {
  const { id } = useParams()

  const [record, setRecord] = useState<KakeiboRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState('')

  useEffect(() => {
    async function fetchRecord() {
      try {
        const response = await fetch(`/api/v1/records/${id}`)

        if (!response.ok) {
          const errorJson: ApiErrorResponse = await response.json()
          setErrorMessage(errorJson.message)
          return
        }

        const json: KakeiboRecordResponse = await response.json()
        setRecord(json.data)
      } catch {
        setErrorMessage('通信に失敗しました')
      } finally {
        setLoading(false)
      }
    }

    fetchRecord()
  }, [id])

  if (loading) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-100">
      <p className="text-gray-600">読み込み中...</p>
    </div>
  )
  }

  if (errorMessage) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 px-4">
        <div className="rounded-xl bg-white p-6 shadow-md">
          <p className="text-red-600">{errorMessage}</p>
        </div>
      </div>
    )
  }

  if (record === null) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-100 px-4">
        <div className="rounded-xl bg-white p-6 shadow-md">
          <p className="text-gray-700">家計簿レコードを表示できませんでした</p>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-100 px-4 py-8">
      <div className="mx-auto max-w-md rounded-xl bg-white p-6 shadow-md">
        <h1 className="mb-6 text-2xl font-bold text-gray-800">
          家計簿詳細
        </h1>

        <div className="space-y-4">
          <div>
            <p className="text-sm text-gray-500">購入日</p>
            <p className="text-base font-medium text-gray-800">
              {record.purchaseDate}
            </p>
          </div>

          <div>
            <p className="text-sm text-gray-500">区分</p>
            <p className="text-base font-medium text-gray-800">
              {record.amountTypeName}
            </p>
          </div>

          <div>
            <p className="text-sm text-gray-500">金額</p>
            <p className="text-xl font-bold text-gray-900">
              {record.amount}円
            </p>
          </div>

          <div>
            <p className="text-sm text-gray-500">カテゴリ</p>
            <p className="text-base font-medium text-gray-800">
              {record.categoryName}
            </p>
          </div>

          <div>
            <p className="text-sm text-gray-500">詳細</p>
            <p className="text-base text-gray-800">
              {record.details}
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}