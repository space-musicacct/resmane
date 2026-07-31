export type KakeiboRecord = {
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

export type Meta = {
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

export type Summary = {
  totalIncome: number
  totalExpense: number
}

export type RecordsResponse = {
  data: KakeiboRecord[]
  meta: Meta
  summary: Summary
}

export type SettingLimit = {
  id: number
  userId: number
  upperLimitTypeId: number
  upperLimitTypeName: string
  maxValue: number
  aveMonthlyIncome: number | null
  createdAt: string
  updatedAt: string
}

export type SettingLimitResponse = {
  data: SettingLimit | null
}