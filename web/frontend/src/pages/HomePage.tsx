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



const budgetSummary: BudgetSummary = {
  goal: '年末までに3万貯金',
  monthlyBudget: 10000,
  monthlyExpense: 7000,
  remaining: 3000,
}

const recentTransactions: Transaction[] = [
  {
    id: 1,
    label: '直近の支出→スタバ',
    amount: 850,
  },
]


function GoalCard() {
  return (
    <section className="mx-auto rounded-[36px] bg-[#fffafa] px-9 pb-9 pt-4">
      <div className="mx-auto mb-4 rounded-xl border-2 border-blue-600 px-3 py-2 text-[24px] leading-snug">
        <p>目標</p>
        <p>{budgetSummary.goal}</p>
      </div>

      <div className="mb-4 justify-center flex">

        <DonutChart />
      </div>
      <Link to='/records/new' className='mb-3 block'>
        <button
          type="button"
          className="mb-3 h-[59px] w-full rounded-lg bg-blue-200 text-[16px] font-bold text-black"
        >
          家計簿登録
        </button>
      </Link>
      <RecentTransaction />

      <AiComment />
    </section>
  )
}

function DonutChart() {
  // Chart.js に渡すデータの設定
  const data = {
    labels: ['支出', '残高'],
    datasets: [
      {
        data: [budgetSummary.monthlyExpense, budgetSummary.remaining], // [7000, 3000]
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
    <div className="flex items-center justify-between w-full max-w-[280px] mx-auto my-4">
      {/* 左側のテキストエリア */}
      <div className="text-left text-[15px] leading-relaxed">
        <p className="text-gray-600">今月の予算</p>
        <p className="font-bold">{budgetSummary.monthlyBudget.toLocaleString()}円</p>
        <p className="mt-2 text-gray-600">今月の支出</p>
        <p className="font-bold">{budgetSummary.monthlyExpense.toLocaleString()}円</p>
      </div>

      {/* 右側のグラフエリア（真ん中にテキストを重ねる） */}
      <div className="relative h-[130px] w-[130px]">
        {/* Chart.jsのドーナツグラフ */}
        <Doughnut data={data} options={options} />

        {/* 真ん中に絶対配置(absolute)で重ねる「残 3000円」 */}
        <div className="absolute inset-0 flex flex-col items-center justify-center text-center leading-tight font-bold">
          <span className="text-gray-700 font-normal text-[11px]">残</span>
          <span className="text-[14px]">{budgetSummary.remaining.toLocaleString()}円</span>
        </div>
      </div>
    </div>
  )
}

function RecentTransaction() {
  const latest = recentTransactions[0]

  return (
    <div className="mb-1 text-[16px]">
      <div className="flex items-center justify-between">
        <p>{latest.label}</p>
      </div>
      <button type="button" className="ml-auto block text-xs">
        詳しくはこちら
      </button>
    </div>
  )
}

function AiComment() {
  return (
    <section className="rounded-[20px] bg-[#f5f343] px-7 pb-9 pt-5">
      <div className="mb-8 rounded-md border-2 border-blue-400 py-1 text-center text-[16px]">
        最近の収入・支出
      </div>

      <p className="text-[17px] font-bold leading-snug">
        節約できて偉い！！
        <br />
        でも食費がちょっと増え
        <br />
        てるよ
      </p>
    </section>
  )
}


export default function HomePage() {
  return (
    <main className="min-h-screen bg-[#d8b94d] pb-[90px] text-black">
      <div className="mx-auto max-w-[390px] px-[33px] pt-[33px]">
        <GoalCard />
      </div>

    </main>
  )
}

