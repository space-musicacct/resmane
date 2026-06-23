import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api, { getCsrfCookie, isApiError } from '../lib/axios'
import type { ValidationErrors } from '../types'
import PageCard from '../components/PageCard'
import RecordForm, { type RecordFormValues } from '../components/RecordForm'

export default function RecordNewPage() {
  const navigate = useNavigate()
  const [errors, setErrors] = useState<ValidationErrors>({})
  const [generalError, setGeneralError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const handleSubmit = async (values: RecordFormValues) => {
    if (submitting) return
    setErrors({})
    setGeneralError('')
    setSubmitting(true)

    try {
      await getCsrfCookie()
      await api.post('/records', {
        purchaseDate: values.purchaseDate || null,
        amountTypeId: Number(values.amountTypeId),
        amount: Number(values.amount),
        details: values.details,
        kakeiboDefaultCategoryId: Number(values.kakeiboDefaultCategoryId),
      })
      navigate('/records')
    } catch (err) {
      if (isApiError(err)) {
        const { status, data } = err.response
        if (status === 422 && data.errors) {
          setErrors(data.errors)
        } else {
          setGeneralError(data.message)
        }
      } else {
        setGeneralError('通信に失敗しました')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <PageCard title="家計簿登録">
      <RecordForm
        errors={errors}
        generalError={generalError}
        submitting={submitting}
        submitLabel="登録"
        onSubmit={handleSubmit}
      />
    </PageCard>
  )
}
