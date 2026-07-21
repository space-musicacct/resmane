import { useCallback, useEffect, useState } from 'react'
import { Doughnut } from 'react-chartjs-2';
import {
  ArcElement,
  Chart as ChartJS,
  Tooltip,
} from 'chart.js'
import { Link } from 'react-router-dom';
import api, { isApiError } from '../lib/axios'
import type {
  KakeiboRecord,
  RecordsResponse,
  Summary,
} from '../types/record'

ChartJS.register(ArcElement, Tooltip);

type DonutChartProps = {
  summary: Summary
}

type RecentTransactionProps = {
  transaction: KakeiboRecord
}

type HomeContentProps = {
  summary: Summary | null
  records: KakeiboRecord[]
}

type RecentHistoryProps = {
  records: KakeiboRecord[]
}

function HomeContent({ summary, records }: HomeContentProps) {
  return (
    <section className="mx-auto flex flex-col gap-6 rounded-[36px] px-5 py-6">

      {summary && (
        <div className="flex justify-center">
          <Link to="/records">
            <DonutChart summary={summary} />
          </Link>
        </div>
      )}

      <Link to="/records/new" className="block">
        <button
          type="button"
          className="h-[59px] w-full rounded-lg bg-blue-200 text-[16px] font-bold"
        >
          家計簿登録
        </button>
      </Link>

      {records.length > 0 ? (
        <RecentTransaction transaction={records[0]} />
      ) : (
        <div className="mb-3 rounded-xl bg-yellow-200 px-4 py-6 text-center text-gray-600">
          まだ家計簿が登録されていません。
        </div>
      )}

      <RecentHistory records={records.slice(0, 3)} />
    </section>
  )
}

function DonutChart({ summary }: DonutChartProps) {
  const balance = summary.totalIncome - summary.totalExpense

  const data = {
    labels: ['収入', '支出'],
    datasets: [
      {
        data: [summary.totalIncome, summary.totalExpense],
        backgroundColor: ['#3B82F6', '#EF4444'],
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
    <div className="flex w-full items-center justify-between gap-6">
      {/* 左側：収入・支出 */}
      <div className="flex-1 text-left text-[16px] leading-relaxed">
        <p className="text-gray-600">今月の収入</p>
        <p className="font-bold">
          {summary.totalIncome.toLocaleString()}円
        </p>

        <p className="mt-2 text-gray-600">今月の支出</p>
        <p className="font-bold">
          {summary.totalExpense.toLocaleString()}円
        </p>
      </div>

      {/* 右側：ドーナツグラフ */}
      <div className="relative h-[200px] w-[200px] shrink-0">
        <Doughnut data={data} options={options} />

        <div className="absolute inset-0 flex flex-col items-center justify-center text-center leading-tight">
          <span className="text-gray-700 text-[16px]">今月の残予算</span>

          <span
            className={`text-[14px] font-bold ${balance >= 0 ? 'text-blue-600' : 'text-red-600'
              }`}
          >
            {balance < 0 ? '-' : ''}
            {Math.abs(balance).toLocaleString()}円
          </span>
        </div>
      </div>
    </div>
  )
}

function RecentTransaction({ transaction }: RecentTransactionProps) {
  const isIncome = transaction.amountTypeId === 2

  return (
    <div>
      <Link
        to={`/records/${transaction.id}`}
        className="block rounded-xl bg-yellow-200 px-4 py-3 transition active:scale-95"
      >
        <div className="flex items-start justify-between">
          <div>
            <p className="font-semibold">
              {transaction.details || '（詳細なし）'}
            </p>

            <p className="text-xs text-gray-600">
              {new Date(transaction.purchaseDate).toLocaleDateString('ja-JP')}
            </p>
          </div>

          <p
            className={`font-bold ${isIncome ? 'text-blue-600' : 'text-red-600'
              }`}
          >
            {isIncome ? '+' : '-'}
            {transaction.amount.toLocaleString()}円
          </p>
        </div>
      </Link>
    </div>
  )
}

function RecentHistory({ records }: RecentHistoryProps) {
  return (
    <section className="rounded-[20px] bg-yellow-400 px-5 py-5">
      <div className="rounded-md py-3 text-center text-[16px]">
        直近の収入・支出
      </div>

      {records.length === 0 ? (
        <p>まだ記録がありません。</p>
      ) : (
        <>
          {records.map((record) => {
            const isIncome = record.amountTypeId === 2

            return (
              <Link
                key={record.id}
                to={`/records/${record.id}`}
                className="mb-2 block rounded-lg bg-white px-4 py-2"
              >
                <div className="flex items-start justify-between">
                  <div>
                    <p className="font-semibold">
                      {record.details || '（詳細なし）'}
                    </p>
                    <p className="text-xs text-gray-500">
                      {new Date(record.purchaseDate).toLocaleDateString('ja-JP')}
                    </p>
                  </div>

                  <p className={`font-bold ${isIncome ? 'text-blue-600' : 'text-red-600'}`}>
                    {isIncome ? '+' : '-'}
                    {record.amount.toLocaleString()}円
                  </p>
                </div>
              </Link>
            )
          })}

          <div className="mt-4 text-center">
            <Link
              to="/records"
              className="font-semibold text-blue-600 hover:underline"
            >
              もっと見る →
            </Link>
          </div>
        </>
      )}
    </section>
  )
}


export default function HomePage() {
  const [records, setRecords] = useState<KakeiboRecord[]>([])
  const [summary, setSummary] = useState<Summary | null>(null)

  const fetchRecords = useCallback(() => {
    api
      .get<RecordsResponse>('/records', {
        params: {
          page: 1,
          sort: 'desc',
        },
      })
      .then((res) => {
        setRecords(res.data.data)
        setSummary(res.data.summary)
      })
      .catch((err) => {
        if (isApiError(err)) {
          console.error(err.response.data.message)
        } else {
          console.error('データの取得に失敗しました')
        }
      })
  }, [])

  useEffect(() => {
    fetchRecords()
  }, [fetchRecords])

  return (
    <main className="min-h-screen bg-yellow-400 flex justify-center px-4 pt-10 pb-10">
      <div className="w-full max-w-[390px] bg-[#F4F1F1] rounded-[40px] py-4">
        <HomeContent
          summary={summary}
          records={records}
        />
      </div>

    </main>
  )
}



