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

function formatYen(amount: number): string {
  return `${amount.toLocaleString()}円`
}

function Header() {
  return (
    <header className="relative h-[102px] bg-white">
      <div className="mx-auto flex h-full max-w-[390px] items-center justify-between px-4">
        <div className="relative flex items-center gap-2">
          <div className="relative">
            <div className="absolute -top-8 left-3 rounded-full border-2 border-yellow-300 px-4 text-xs font-bold text-blue-500">
              ?
            </div>
            <div className="h-4 w-4 rounded-full bg-yellow-400" />
            <div className="mt-1 h-5 w-5 bg-blue-600" />
          </div>

          <h1 className="text-[30px] font-extrabold leading-none text-yellow-300 drop-shadow-sm">
            Re$Mane
          </h1>
        </div>

        <div className="relative flex items-center gap-5">
          <div className="relative h-8 w-10 bg-gray-300">
            <div className="absolute left-2 top-3 h-1 w-1 rounded-full bg-white" />
            <div className="absolute right-2 top-3 h-1 w-1 rounded-full bg-white" />
            <div className="absolute -top-5 left-4 h-2 w-2 rounded-full bg-black" />
            <div className="absolute -bottom-7 -left-4 rounded-full border-2 border-yellow-300 px-5 text-xs font-bold text-blue-500">
              !
            </div>
          </div>

          <button aria-label="設定" className="text-black">
            <GearIcon />
          </button>
        </div>
      </div>
    </header>
  )
}

function GoalCard() {
  return (
    <section className="mx-auto rounded-[36px] bg-[#fffafa] px-9 pb-9 pt-4">
      <div className="mx-auto mb-4 rounded-xl border-2 border-blue-600 px-3 py-2 text-[24px] leading-snug">
        <p>目標</p>
        <p>{budgetSummary.goal}</p>
      </div>

      <div className="mb-4 grid grid-cols-[1fr_148px] items-center gap-4">
        <div className="text-center text-[17px] leading-relaxed">
          <p>今月の予算</p>
          <p>{formatYen(budgetSummary.monthlyBudget)}</p>
          <p className="mt-1">今月の支出</p>
          <p>{formatYen(budgetSummary.monthlyExpense)}</p>
        </div>

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
  // 残額と支出の比率を conic-gradient で表現する
  const expenseRate =
    budgetSummary.monthlyExpense /
    (budgetSummary.monthlyExpense + budgetSummary.remaining)

  const expenseDeg = Math.round(expenseRate * 360)

  return (
    <div
      className="relative h-[156px] w-[156px] rounded-full"
      style={{
        background: `conic-gradient(#ef3b1f 0deg ${expenseDeg}deg, #ffa827 ${expenseDeg}deg 360deg)`,
      }}
      aria-label="今月の支出と残額の円グラフ"
    >
      <div className="absolute left-1/2 top-1/2 flex h-[78px] w-[78px] -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-[#fffafa] text-center text-[16px] leading-tight">
        <span>
          残
          <br />
          {formatYen(budgetSummary.remaining)}
        </span>
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
        <p>{formatYen(latest.amount)}</p>
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

function BottomNavigation() {
  const navItems = [
    { label: 'ホーム', icon: <HomeIcon />, active: true },
    { label: '一覧', icon: <ListIcon /> },
    { label: 'チャット', icon: <ChatIcon />, hasNotice: true },
    { label: 'マイページ', icon: <UserIcon /> },
  ]

  return (
    <nav className="fixed bottom-0 left-1/2 z-10 grid h-[76px] w-full max-w-[390px] -translate-x-1/2 grid-cols-4 items-center gap-6 bg-[#d8b94d] px-3">
      {navItems.map((item) => (
        <button
          key={item.label}
          type="button"
          aria-label={item.label}
          className="relative flex h-[63px] items-center justify-center rounded-lg bg-white"
        >
          {item.hasNotice && (
            <span className="absolute right-4 top-0 h-4 w-4 rounded-full bg-red-500" />
          )}
          <span className={item.active ? 'text-black' : 'text-black'}>
            {item.icon}
          </span>
        </button>
      ))}
    </nav>
  )
}

function GearIcon() {
  return (
    <svg width="36" height="36" viewBox="0 0 24 24" fill="currentColor">
      <path d="M19.43 12.98c.04-.32.07-.65.07-.98s-.02-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46a.5.5 0 0 0-.61-.22l-2.49 1a7.28 7.28 0 0 0-1.69-.98L14.5 2.42A.49.49 0 0 0 14 2h-4a.49.49 0 0 0-.5.42L9.13 5.07c-.61.24-1.18.56-1.69.98l-2.49-1a.5.5 0 0 0-.61.22l-2 3.46a.5.5 0 0 0 .12.64l2.11 1.65c-.05.32-.07.65-.07.98s.02.66.07.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46c.13.22.39.31.61.22l2.49-1c.51.4 1.08.73 1.69.98l.37 2.65c.04.24.25.42.5.42h4c.25 0 .46-.18.5-.42l.37-2.65c.61-.24 1.18-.56 1.69-.98l2.49 1c.23.08.48 0 .61-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z" />
    </svg>
  )
}

function HomeIcon() {
  return (
    <svg width="60" height="54" viewBox="0 0 64 64" fill="none">
      <path
        d="M8 31 32 12l24 19"
        stroke="currentColor"
        strokeWidth="7"
        strokeLinejoin="round"
      />
      <path
        d="M16 29v25h32V29"
        stroke="currentColor"
        strokeWidth="7"
        strokeLinejoin="round"
      />
    </svg>
  )
}

function ListIcon() {
  return (
    <svg width="52" height="52" viewBox="0 0 64 64" fill="none">
      <rect
        x="8"
        y="8"
        width="48"
        height="48"
        rx="4"
        stroke="currentColor"
        strokeWidth="5"
      />
      <path d="M22 22h24M22 32h24M22 42h24" stroke="currentColor" strokeWidth="4" />
      <path d="M16 22h2M16 32h2M16 42h2" stroke="currentColor" strokeWidth="4" />
    </svg>
  )
}

function ChatIcon() {
  return (
    <svg width="58" height="52" viewBox="0 0 64 64" fill="none">
      <path
        d="M9 9h31v27H23L9 48V9Z"
        stroke="currentColor"
        strokeWidth="5"
        strokeLinejoin="round"
      />
      <path
        d="M33 36h22v20L44 47H33"
        stroke="currentColor"
        strokeWidth="5"
        strokeLinejoin="round"
      />
    </svg>
  )
}

function UserIcon() {
  return (
    <svg width="56" height="56" viewBox="0 0 64 64" fill="currentColor">
      <circle cx="32" cy="24" r="12" />
      <path d="M12 56c3-13 13-20 20-20s17 7 20 20H12Z" />
    </svg>
  )
}

export default function HomePage() {
  return (
    <main className="min-h-screen bg-[#d8b94d] pb-[90px] text-black">
      <Header />

      <div className="mx-auto max-w-[390px] px-[33px] pt-[33px]">
        <GoalCard />
      </div>

      <BottomNavigation />
    </main>
  )
}