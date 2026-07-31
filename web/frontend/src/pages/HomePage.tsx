import { useCallback, useEffect, useState } from 'react'
import { Doughnut } from 'react-chartjs-2';
import {
  ArcElement,
  Chart as ChartJS,
  Tooltip,
} from 'chart.js'
import { Link } from 'react-router-dom';
import api, { isApiError } from '../lib/axios'
import PageCard from '../components/PageCard'
import LoadingScreen from '../components/LoadingScreen'
import ErrorAlert from '../components/ErrorAlert'
import type {
  KakeiboRecord,
  RecordsResponse,
  SettingLimit,
  SettingLimitResponse,
  Summary,
} from '../types/record'

ChartJS.register(ArcElement, Tooltip);

const PERCENTAGE_TYPE_ID = 1
const MAX_FEEDBACK_DISPLAY = 3
const FEEDBACK_TRUNCATE_LENGTH = 80

type Post = {
  id: number
  userId: number
  kakeiboRecordId: number
  isAi: boolean
  aiStatus: { id: number; statusName: string } | null
  parentId: number | null
  content: string | null
  createdAt: string
  updatedAt: string
}

type PostsResponse = {
  data: Post[]
}

function getMonthRange(): { from: string; to: string } {
  const now = new Date()
  const y = now.getFullYear()
  const m = now.getMonth()
  const from = `${y}-${String(m + 1).padStart(2, '0')}-01`
  const lastDay = new Date(y, m + 1, 0).getDate()
  const to = `${y}-${String(m + 1).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`
  return { from, to }
}

function calcBudget(
  summary: Summary,
  settingLimit: SettingLimit | null,
): { label: string; value: number } {
  if (!settingLimit) {
    return {
      label: '今月の収支',
      value: summary.totalIncome - summary.totalExpense,
    }
  }

  let budget: number
  if (settingLimit.upperLimitTypeId === PERCENTAGE_TYPE_ID) {
    const income = settingLimit.aveMonthlyIncome ?? 0
    budget = Math.floor(income * settingLimit.maxValue / 100) - summary.totalExpense
  } else {
    budget = settingLimit.maxValue - summary.totalExpense
  }

  return { label: '今月の残予算', value: budget }
}

function truncate(text: string, max: number): string {
  return text.length > max ? text.slice(0, max) + '...' : text
}

type DonutChartProps = {
  summary: Summary
  settingLimit: SettingLimit | null
}

const GRADIENT_COLORS = [
  { start: '#FB7185', end: '#F43F5E' },
  { start: '#818CF8', end: '#38BDF8' },
]

function DonutChart({ summary, settingLimit }: DonutChartProps) {
  const { label, value: balance } = calcBudget(summary, settingLimit)

  const data = {
    labels: ['支出', '収入'],
    datasets: [
      {
        data: [summary.totalExpense, summary.totalIncome],
        backgroundColor: (context: { chart: ChartJS; dataIndex: number }) => {
          const { ctx, chartArea } = context.chart
          if (!chartArea) return GRADIENT_COLORS[context.dataIndex]?.start ?? '#ccc'
          const g = ctx.createLinearGradient(chartArea.left, chartArea.top, chartArea.right, chartArea.bottom)
          const colors = GRADIENT_COLORS[context.dataIndex]
          g.addColorStop(0, colors.start)
          g.addColorStop(1, colors.end)
          return g
        },
        borderWidth: 0,
        cutout: '70%',
      },
    ],
  }

  const options = {
    plugins: {
      tooltip: { enabled: false },
    },
    responsive: true,
    maintainAspectRatio: false,
  }

  return (
    <div className="flex flex-col items-center gap-4">
      <div className="relative h-[160px] w-[160px]">
        <Doughnut data={data} options={options} />
        <div className="absolute inset-0 flex flex-col items-center justify-center text-center leading-tight">
          <span className="text-gray-700 text-sm">{label}</span>
          <span
            className={`text-base font-bold ${balance >= 0 ? 'text-blue-600' : 'text-red-600'}`}
          >
            {balance < 0 ? '-' : ''}
            {Math.abs(balance).toLocaleString()}円
          </span>
        </div>
      </div>

      <div className="flex w-full gap-4">
        <div className="flex-1 rounded-xl bg-blue-50 px-4 py-3 text-center">
          <p className="text-sm text-gray-500">今月の収入</p>
          <p className="text-lg font-bold text-blue-600">{summary.totalIncome.toLocaleString()}円</p>
        </div>
        <div className="flex-1 rounded-xl bg-red-50 px-4 py-3 text-center">
          <p className="text-sm text-gray-500">今月の支出</p>
          <p className="text-lg font-bold text-red-600">{summary.totalExpense.toLocaleString()}円</p>
        </div>
      </div>

    </div>
  )
}

type AiFeedbackSectionProps = {
  latestRecord: KakeiboRecord | null
  aiFeedbacks: Post[]
}

function AiFeedbackSection({ latestRecord, aiFeedbacks }: AiFeedbackSectionProps) {
  if (!latestRecord) {
    return (
      <div className="rounded-xl bg-white px-5 py-6 text-center text-base text-gray-500">
        まだ家計簿が登録されていません。
      </div>
    )
  }

  const visiblePosts = aiFeedbacks
    .filter((p) => {
      if (p.isAi) return p.aiStatus?.statusName === 'completed' && p.content
      return p.content
    })
    .slice(0, MAX_FEEDBACK_DISPLAY)

  return (
    <div>
      <p className="mb-1 text-sm text-gray-400">最新の記録へのスレッド</p>
      <p className="mb-3 text-base font-semibold">{latestRecord.details}</p>

      {visiblePosts.length === 0 ? (
        <div className="rounded-xl bg-white px-5 py-6 text-center text-base text-gray-400">
          まだ投稿がありません
        </div>
      ) : (
        <div className="space-y-2">
          {visiblePosts.map((post) => (
            <div
              key={post.id}
              className={`rounded-xl px-4 py-3 text-base ${
                post.isAi
                  ? 'bg-white text-gray-700'
                  : 'bg-blue-50 text-gray-700'
              }`}
            >
              <span className="mr-2 text-xs font-bold text-gray-400">
                {post.isAi ? 'AI' : 'あなた'}
              </span>
              {truncate(post.content!, FEEDBACK_TRUNCATE_LENGTH)}
            </div>
          ))}
        </div>
      )}

      <div className="mt-3 text-right">
        <Link
          to={`/records/${latestRecord.id}/posts`}
          className="text-base font-semibold text-blue-600 hover:underline"
        >
          フィードバックを見る
        </Link>
      </div>
    </div>
  )
}


export default function HomePage() {
  const [records, setRecords] = useState<KakeiboRecord[]>([])
  const [summary, setSummary] = useState<Summary | null>(null)
  const [settingLimit, setSettingLimit] = useState<SettingLimit | null>(null)
  const [aiFeedbacks, setAiFeedbacks] = useState<Post[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const fetchData = useCallback(async () => {
    setIsLoading(true)
    setError('')

    const { from, to } = getMonthRange()

    try {
      const [recordsRes, limitRes] = await Promise.all([
        api.get<RecordsResponse>('/records', {
          params: { page: 1, sort: 'desc', from, to },
        }),
        api.get<SettingLimitResponse>('/settings/limit'),
      ])

      setRecords(recordsRes.data.data)
      setSummary(recordsRes.data.summary)
      setSettingLimit(limitRes.data.data)

      const latestRecord = recordsRes.data.data[0]
      if (latestRecord) {
        const postsRes = await api.get<PostsResponse>(
          `/records/${latestRecord.id}/posts`
        )
        setAiFeedbacks(postsRes.data.data)
      }
    } catch (err) {
      if (isApiError(err)) {
        setError(err.response.data.message)
      } else {
        setError('データの取得に失敗しました')
      }
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  if (isLoading) {
    return <LoadingScreen />
  }

  const latestRecord = records[0] ?? null

  return (
    <PageCard title="今月のまとめ">
      {error ? (
        <ErrorAlert message={error} />
      ) : (
        <div className="flex flex-col gap-8">
          {summary && (
            <DonutChart summary={summary} settingLimit={settingLimit} />
          )}

          <AiFeedbackSection
            latestRecord={latestRecord}
            aiFeedbacks={aiFeedbacks}
          />
        </div>
      )}
    </PageCard>
  )
}
