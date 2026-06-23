import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import api, { getCsrfCookie, isApiError } from '../lib/axios'
import type { ValidationErrors } from '../types'
import PageCard from '../components/PageCard'
import RecordForm, { type RecordFormValues } from '../components/RecordForm'
import LoadingScreen from '../components/LoadingScreen'

export default function RecordEditPage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [initialValues, setInitialValues] = useState<RecordFormValues | null>(null)
  const [loading, setLoading] = useState(true)
  const [errors, setErrors] = useState<ValidationErrors>({})
  const [generalError, setGeneralError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    api.get(`/records/${id}`)
      .then((res) => {
        const r = res.data.data
        setInitialValues({
          purchaseDate: r.purchaseDate,
          amountTypeId: String(r.amountTypeId),
          amount: String(r.amount),
          details: r.details ?? '',
          kakeiboDefaultCategoryId: String(r.categoryId),
        })
      })
      .catch((err) => {
        if (isApiError(err)) {
          setGeneralError(err.response.data.message)
        } else {
          setGeneralError('データの取得に失敗しました')
        }
      })
      .finally(() => setLoading(false))
  }, [id])

  const handleSubmit = async (values: RecordFormValues) => {
    if (submitting) return
    setErrors({})
    setGeneralError('')
    setSubmitting(true)

    try {
      await getCsrfCookie()
      await api.put(`/records/${id}`, {
        purchaseDate: values.purchaseDate || null,
        amountTypeId: Number(values.amountTypeId),
        amount: Number(values.amount),
        details: values.details,
        kakeiboDefaultCategoryId: Number(values.kakeiboDefaultCategoryId),
      })
      navigate(`/records/${id}`)
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

  if (loading) return <LoadingScreen />

  return (
    <PageCard title="家計簿編集">
      {initialValues ? (
        <RecordForm
          initialValues={initialValues}
          errors={errors}
          generalError={generalError}
          submitting={submitting}
          submitLabel="更新"
          onSubmit={handleSubmit}
        />
      ) : (
        <p className="text-center text-red-600">{generalError}</p>
      )}
    </PageCard>
  )
}
