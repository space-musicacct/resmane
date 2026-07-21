import { Doughnut } from 'react-chartjs-2';
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js';
import { Link } from 'react-router-dom';

ChartJS.register(ArcElement, Tooltip);

type Transaction = {
  id: number
  label: string
  amount: number
}

type BudgetSummary = {
  goal: string
  monthlyBudget: number
  monthlyExpense: number
  remaining: number
}

type DonutChartProps = {
  summary: BudgetSummary
}

type RecentTransactionProps = {
  transaction: Transaction
}

type GoalCardProps = {
  summary: BudgetSummary
  transaction: Transaction
}

const budgetSummary: BudgetSummary = {
  goal: '年末までに3万貯金',
  monthlyBudget: 10000,
  monthlyExpense: 7000,
  remaining: 3000,
}

const recentTransactions: Transaction[] = [
  {
    id: 1,
    label: 'スタバ',
    amount: 850,
  },
]


function GoalCard({ summary, transaction }: GoalCardProps) {
  return (
    <section className="mx-auto rounded-[36px] px-9 pb-9 pt-4">
      <div className="mb-4 rounded-2xl bg-blue-100 px-5 py-4">
        <p className="text-sm text-blue-700 font-medium">
          現在の目標
        </p>

        <p className="mt-1 text-xl font-bold text-gray-900">
          {summary.goal}
        </p>
      </div>

      <div className="mb-4 justify-center flex">
        <Link to="/records">
          <DonutChart summary={summary} />
        </Link>
      </div>
      <Link to='/records/new' className='mb-3 block'>
        <button
          type="button"
          className="mb-3 h-[59px] w-full rounded-lg bg-blue-200 text-[16px] font-bold text-black"
        >
          家計簿登録
        </button>
      </Link>
      <RecentTransaction transaction={transaction} />

      <RecentHistory />
    </section>
  )
}

function DonutChart({ summary }: DonutChartProps) {
  // Chart.js に渡すデータの設定
  const data = {
    labels: ['支出', '残高'],
    datasets: [
      {
        data: [summary.monthlyExpense, summary.remaining], // [7000, 3000]
        backgroundColor: ['#FF4D2D', '#FF9F2D'], // 赤（支出）とオレンジ（残り）
        borderWidth: 0, // 枠線を消してフラットにする
        cutout: '70%', // 真ん中の穴の大きさ（ドーナツの太さ）
      },
    ],
  };

  const options = {
    plugins: {
      tooltip: { enabled: false }, // 今回はシンプルな表示なのでツールチップはオフ
    },
    responsive: true,
    maintainAspectRatio: false,
  };

  return (
    <div className="flex items-center justify-between w-full max-w-[280px] mx-2 my-4">
      {/* 左側のテキストエリア */}
      <div className="text-left text-[15px] leading-relaxed">
        <p className="text-gray-600">今月の予算</p>
        <p className="font-bold">{summary.monthlyBudget.toLocaleString()}円</p>
        <p className="mt-2 text-gray-600">今月の支出</p>
        <p className="font-bold">{summary.monthlyExpense.toLocaleString()}円</p>
      </div>

      {/* 右側のグラフエリア（真ん中にテキストを重ねる） */}
      <div className="relative h-[130px] w-[130px]">
        {/* Chart.jsのドーナツグラフ */}
        <Doughnut data={data} options={options} />

        {/* 真ん中に絶対配置(absolute)で重ねる「残 3000円」 */}
        <div className="absolute inset-0 flex flex-col items-center justify-center text-center leading-tight font-bold">
          <span className="text-gray-700 font-normal text-[11px]">残</span>
          <span className="text-[14px]">{summary.remaining.toLocaleString()}円</span>
        </div>
      </div>
    </div>
  )
}

function RecentTransaction({ transaction }: RecentTransactionProps) {

  return (
    <div className="mb-1 text-[16px]">
      <Link
        to={`/records/${transaction.id}`}
        className="flex items-center justify-between rounded-xl bg-yellow-200 px-4 py-3"
      >
        <div>
          <p className="font-semibold">{transaction.label}</p>
          <p className="text-sm text-gray-600">
            {transaction.amount.toLocaleString()}円
          </p>
        </div>

        <span>〉</span>
      </Link>
    </div>
  )
}

function RecentHistory() {
  return (
    <section className="rounded-[20px] bg-yellow-400 px-7 pb-9 pt-5">
      <div className="mb-8 rounded-md border-2 border-blue-400 py-1 text-center text-[16px]">
        直近の収入・支出
      </div>

      <p className="text-[17px] font-bold leading-snug">
        ここに最近の家計簿履歴
      </p>
    </section>
  )
}


export default function HomePage() {
  return (
    <main className="min-h-screen bg-yellow-400 flex justify-center px-4 pt-10 pb-10">
      <div className="w-full max-w-[390px] bg-[#F4F1F1] rounded-[40px] px-8 py-10">
        <GoalCard
          summary={budgetSummary}
          transaction={recentTransactions[0]}
        />
      </div>

    </main>
  )
}

