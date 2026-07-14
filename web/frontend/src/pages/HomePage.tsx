

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

      <button
        type="button"
        className="mb-3 h-[59px] w-full rounded-lg bg-blue-200 text-[16px] font-bold text-black"
      >
        家計簿登録
      </button>

      <RecentTransaction />

      <AiComment />
    </section>
  )
}

function DonutChart() {

  return (
  <div className="flex justify-center my-4">
    <div className="text-center text-[17px] leading-relaxed">
      <p>今月の予算</p>
      <p>{budgetSummary.monthlyBudget.toLocaleString()}円</p>
      <p className="mt-1">今月の支出</p>
      <p>{budgetSummary.monthlyExpense.toLocaleString()}円</p>
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

