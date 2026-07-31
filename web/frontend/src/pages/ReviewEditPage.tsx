import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import api, { getCsrfCookie, isApiError } from '../lib/axios'
import type { ValidationErrors } from '../types'
import type { SelfReview } from '../types/record'
import PageCard from '../components/PageCard'
import ErrorAlert from '../components/ErrorAlert'
import PrimaryButton from '../components/PrimaryButton'
import LoadingScreen from '../components/LoadingScreen'

export default function ReviewEditPage() {
  const { recordId, reviewId } = useParams()
  const navigate = useNavigate()

  const [reviewComment, setReviewComment] = useState('')
  const [evaluation, setEvaluation] = useState(0)
  const [loading, setLoading] = useState(true)
  const [errors, setErrors] = useState<ValidationErrors>({})
  const [generalError, setGeneralError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    api.get(`/records/${recordId}/reviews`)
      .then((res) => {
        const reviews: SelfReview[] = res.data.data
        const target = reviews.find((r) => r.id === Number(reviewId))
        if (target) {
          setReviewComment(target.reviewComment)
          setEvaluation(target.evaluation)
        } else {
          setGeneralError('指定された自己レビューが見つかりません')
        }
      })
      .catch((err) => {
        if (isApiError(err)) {
          setGeneralError(err.response.data.message)
        } else {
          setGeneralError('データの取得に失敗しました')
        }
      })
      .finally(() => setLoading(false))
  }, [recordId, reviewId])

  const handleSubmit = async () => {
    if (submitting) return
    setErrors({})
    setGeneralError('')
    setSubmitting(true)

    try {
      await getCsrfCookie()
      await api.put(`/records/${recordId}/reviews/${reviewId}`, {
        reviewComment,
        evaluation,
      })
      navigate(`/records/${recordId}/posts`)
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
    <PageCard title="自己レビュー編集">
      <ErrorAlert message={generalError} />

      <div className="space-y-5">
        <div>
          <label className="block text-sm font-medium mb-1">
            評価<span className="ml-[3px] text-red-500">*</span>
          </label>
          <div className="flex">
            {[1, 2, 3, 4, 5].map((star) => (
              <button
                key={star}
                type="button"
                onClick={() => setEvaluation(star)}
                className={`flex-1 text-5xl ${
                  star <= evaluation
                    ? 'text-yellow-400'
                    : 'text-gray-300 hover:text-yellow-300'
                }`}
              >
                ★
              </button>
            ))}
          </div>
          {errors.evaluation?.map((msg, i) => (
            <p key={i} className="mt-1 text-xs text-red-600">{msg}</p>
          ))}
        </div>

        <div>
          <label className="block text-sm font-medium mb-1">
            自己レビュー<span className="ml-[3px] text-red-500">*</span>
          </label>
          <textarea
            value={reviewComment}
            onChange={(e) => setReviewComment(e.target.value)}
            placeholder="この支出について振り返りを入力してください（250文字以内）"
            rows={6}
            maxLength={250}
            className="w-full rounded-lg border border-gray-400 bg-white px-3 py-2 text-sm"
          />
          {errors.reviewComment?.map((msg, i) => (
            <p key={i} className="mt-1 text-xs text-red-600">{msg}</p>
          ))}
        </div>
      </div>

      <PrimaryButton
        label="更新"
        submitting={submitting}
        onClick={handleSubmit}
        className="mt-10"
      />

      <button
        type="button"
        onClick={() => navigate(`/records/${recordId}/posts`)}
        className="mt-4 block w-full text-center text-sm text-gray-500 underline"
      >
        キャンセル
      </button>
    </PageCard>
  )
}
