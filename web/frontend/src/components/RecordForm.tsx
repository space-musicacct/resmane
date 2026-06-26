import { useEffect, useState } from 'react'
import api from '../lib/axios'
import type { ValidationErrors } from '../types'
import { FormInput, FormSelect, FormTextarea } from './FormInput'
import ErrorAlert from './ErrorAlert'
import PrimaryButton from './PrimaryButton'

type MasterItem = { id: number; name: string }

export type RecordFormValues = {
  purchaseDate: string
  amountTypeId: string
  amount: string
  details: string
  kakeiboDefaultCategoryId: string
}

export default function RecordForm({
  initialValues,
  errors,
  generalError,
  submitting,
  submitLabel,
  onSubmit,
}: {
  initialValues?: RecordFormValues
  errors: ValidationErrors
  generalError: string
  submitting: boolean
  submitLabel: string
  onSubmit: (values: RecordFormValues) => void
}) {
  const [amountTypes, setAmountTypes] = useState<MasterItem[]>([])
  const [categories, setCategories] = useState<MasterItem[]>([])
  const [masterLoading, setMasterLoading] = useState(true)

  const [purchaseDate, setPurchaseDate] = useState(initialValues?.purchaseDate ?? '')
  const [amountTypeId, setAmountTypeId] = useState(initialValues?.amountTypeId ?? '')
  const [amount, setAmount] = useState(initialValues?.amount ?? '')
  const [details, setDetails] = useState(initialValues?.details ?? '')
  const [kakeiboDefaultCategoryId, setKakeiboDefaultCategoryId] = useState(initialValues?.kakeiboDefaultCategoryId ?? '')

  const [initialAmountTypeLoaded, setInitialAmountTypeLoaded] = useState(false)

  useEffect(() => {
    api.get('/amountTypes')
      .then((res) => {
        setAmountTypes(res.data.data.map((t: { id: number; typeName: string }) => ({
          id: t.id,
          name: t.typeName,
        })))
      })
      .finally(() => setMasterLoading(false))
  }, [])

  useEffect(() => {
    if (!amountTypeId) {
      setCategories([])
      return
    }
    api.get('/categories', { params: { amountTypeId } })
      .then((res) => {
        setCategories(res.data.data.map((c: { id: number; categoryName: string }) => ({
          id: c.id,
          name: c.categoryName,
        })))
      })
    if (initialAmountTypeLoaded) {
      setKakeiboDefaultCategoryId('')
    } else {
      setInitialAmountTypeLoaded(true)
    }
  }, [amountTypeId])

  if (masterLoading) return null

  return (
    <>
      <ErrorAlert message={generalError} />

      <div className="space-y-5">
        <FormInput
          label="購入日"
          type="date"
          value={purchaseDate}
          onChange={setPurchaseDate}
          placeholder="未入力で今日の日付"
          errors={errors.purchaseDate}
        />
        <FormSelect
          label="収支区分"
          value={amountTypeId}
          onChange={setAmountTypeId}
          options={amountTypes}
          required
          errors={errors.amountTypeId}
        />
        <FormInput
          label="金額"
          type="number"
          value={amount}
          onChange={setAmount}
          placeholder="1以上の整数"
          required
          errors={errors.amount}
        />
        <FormTextarea
          label="内容"
          value={details}
          onChange={setDetails}
          placeholder="何を買ったか（250文字以内）"
          errors={errors.details}
        />
        <FormSelect
          label="カテゴリー"
          value={kakeiboDefaultCategoryId}
          onChange={setKakeiboDefaultCategoryId}
          options={categories}
          required
          placeholder={amountTypeId ? '選択してください' : '先に収支区分を選択してください'}
          errors={errors.kakeiboDefaultCategoryId}
        />
      </div>

      <PrimaryButton
        label={submitLabel}
        submitting={submitting}
        onClick={() => onSubmit({ purchaseDate, amountTypeId, amount, details, kakeiboDefaultCategoryId })}
        className="mt-10"
      />
    </>
  )
}
