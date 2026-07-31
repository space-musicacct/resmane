import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api, { getCsrfCookie, isApiError } from '../lib/axios'
import type { KakeiboRecord, SelfReview, Post } from '../types/record'
import type { ValidationErrors } from '../types'
import PageCard from '../components/PageCard'
import LoadingScreen from '../components/LoadingScreen'
import TabSwitcher from '../components/TabSwitcher'
import ErrorAlert from '../components/ErrorAlert'

type TabKey = 'detail' | 'posts'

const VALID_TABS: TabKey[] = ['detail', 'posts']

function resolveTab(tab: string | undefined): TabKey {
  return VALID_TABS.includes(tab as TabKey) ? (tab as TabKey) : 'detail'
}

export default function RecordDetailPage() {
  const { id, tab } = useParams()
  const navigate = useNavigate()

  const [record, setRecord] = useState<KakeiboRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState('')
  const activeTab = resolveTab(tab)

  const handleDelete = async () => {
    if (!confirm('この記録を削除しますか？')) return

    try {
      await getCsrfCookie()
      await api.delete(`/records/${id}`)
      navigate('/records')
    } catch (err) {
      if (isApiError(err)) {
        setErrorMessage(err.response.data.message)
      } else {
        setErrorMessage('削除に失敗しました')
      }
    }
  }

  useEffect(() => {
    api
      .get(`/records/${id}`)
      .then((res) => setRecord(res.data.data))
      .catch((err) => {
        if (isApiError(err)) {
          setErrorMessage(err.response.data.message)
        } else {
          setErrorMessage('通信に失敗しました')
        }
      })
      .finally(() => setLoading(false))
  }, [id])

  if (loading) return <LoadingScreen />

  if (errorMessage || !record) {
    return (
      <PageCard title="エラー">
        <p className="text-center text-red-600">
          {errorMessage || '家計簿レコードを表示できませんでした'}
        </p>
        <Link
          to="/records"
          className="mt-6 block text-center text-sm font-medium text-blue-600 underline"
        >
          一覧に戻る
        </Link>
      </PageCard>
    )
  }

  return (
    <PageCard title="家計簿詳細">
      <TabSwitcher
        tabs={[
          { key: 'detail' as TabKey, label: '家計簿詳細' },
          { key: 'posts' as TabKey, label: '自己レビュー / AIフィードバック' },
        ]}
        activeTab={activeTab}
        onChange={(key) => navigate(`/records/${id}/${key}`, { replace: true })}
        className="mb-8"
      />

      {activeTab === 'detail' && (
        <>
          <div className="space-y-5">
            <DetailRow label="購入日" value={record.purchaseDate} />
            <DetailRow label="区分" value={record.amountTypeName} />
            <DetailRow
              label="金額"
              value={`${record.amount.toLocaleString()}円`}
              bold
            />
            <DetailRow label="カテゴリー" value={record.categoryName} />
            <DetailRow label="内容" value={record.details} />
          </div>

          <div className="mt-8 flex gap-3">
            <Link
              to={`/records/${record.id}/edit`}
              className="flex-1 rounded-2xl border border-green-500 py-3 text-center font-bold text-green-600 hover:bg-green-50"
            >
              編集
            </Link>
            <button
              type="button"
              onClick={handleDelete}
              className="flex-1 rounded-2xl border border-red-400 py-3 font-bold text-red-600 hover:bg-red-50"
            >
              削除
            </button>
          </div>
        </>
      )}

      {activeTab === 'posts' && <PostsTab recordId={Number(id)} />}
    </PageCard>
  )
}

function PostsTab({ recordId }: { recordId: number }) {
  const navigate = useNavigate()
  const [reviews, setReviews] = useState<SelfReview[]>([])
  const [posts, setPosts] = useState<Post[]>([])
  const [loadingData, setLoadingData] = useState(true)
  const [error, setError] = useState('')

  const [reviewComment, setReviewComment] = useState('')
  const [evaluation, setEvaluation] = useState(0)
  const [reviewErrors, setReviewErrors] = useState<ValidationErrors>({})
  const [reviewGeneralError, setReviewGeneralError] = useState('')
  const [submittingReview, setSubmittingReview] = useState(false)

  const [chatInput, setChatInput] = useState('')
  const [submittingChat, setSubmittingChat] = useState(false)
  const [chatError, setChatError] = useState('')

  const [generatingFeedback, setGeneratingFeedback] = useState(false)
  const [feedbackError, setFeedbackError] = useState('')

  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const fetchPosts = useCallback(() => {
    return api.get(`/records/${recordId}/posts`).then((res) => {
      setPosts(res.data.data)
      return res.data.data as Post[]
    })
  }, [recordId])

  const fetchReviews = useCallback(() => {
    return api.get(`/records/${recordId}/reviews`).then((res) => {
      setReviews(res.data.data)
    })
  }, [recordId])

  const startPolling = useCallback(() => {
    if (pollingRef.current) return
    pollingRef.current = setInterval(async () => {
      try {
        const latest = await fetchPosts()
        const hasPending = latest.some(
          (p: Post) => p.isAi && p.aiStatus &&
            (p.aiStatus.statusName === 'pending' || p.aiStatus.statusName === 'processing'),
        )
        if (!hasPending && pollingRef.current) {
          clearInterval(pollingRef.current)
          pollingRef.current = null
        }
      } catch {
        /* polling failure is silent */
      }
    }, 3000)
  }, [fetchPosts])

  useEffect(() => {
    Promise.all([fetchReviews(), fetchPosts()])
      .then(([, postsData]) => {
        const hasPending = (postsData as Post[]).some(
          (p) => p.isAi && p.aiStatus &&
            (p.aiStatus.statusName === 'pending' || p.aiStatus.statusName === 'processing'),
        )
        if (hasPending) startPolling()
      })
      .catch((err) => {
        if (isApiError(err)) {
          setError(err.response.data.message)
        } else {
          setError('データの取得に失敗しました')
        }
      })
      .finally(() => setLoadingData(false))

    return () => {
      if (pollingRef.current) {
        clearInterval(pollingRef.current)
        pollingRef.current = null
      }
    }
  }, [recordId, fetchReviews, fetchPosts, startPolling])

  const handleSubmitReview = async () => {
    if (submittingReview) return
    if (evaluation === 0) {
      setReviewErrors({ evaluation: ['評価を選択してください'] })
      return
    }
    setReviewErrors({})
    setReviewGeneralError('')
    setSubmittingReview(true)

    try {
      await getCsrfCookie()
      await api.post(`/records/${recordId}/reviews`, { reviewComment, evaluation })
      setReviewComment('')
      setEvaluation(0)
      await fetchReviews()
    } catch (err) {
      if (isApiError(err)) {
        const { status, data } = err.response
        if (status === 422 && data.errors) {
          setReviewErrors(data.errors)
        } else {
          setReviewGeneralError(data.message)
        }
      } else {
        setReviewGeneralError('通信に失敗しました')
      }
    } finally {
      setSubmittingReview(false)
    }
  }

  const handleDeleteReview = async (reviewId: number) => {
    if (!confirm('この自己レビューを削除しますか？')) return

    try {
      await getCsrfCookie()
      await api.delete(`/records/${recordId}/reviews/${reviewId}`)
      setReviews((prev) => prev.filter((r) => r.id !== reviewId))
    } catch (err) {
      if (isApiError(err)) {
        setError(err.response.data.message)
      } else {
        setError('削除に失敗しました')
      }
    }
  }

  const handleRequestFeedback = async () => {
    if (generatingFeedback) return
    setFeedbackError('')
    setGeneratingFeedback(true)

    try {
      await getCsrfCookie()
      const res = await api.post(`/records/${recordId}/posts`)
      const aiPost: Post | null = res.data.data.aiPost
      if (aiPost) {
        setPosts((prev) => [...prev, aiPost])
        startPolling()
      }
    } catch (err) {
      if (isApiError(err)) {
        setFeedbackError(err.response.data.message)
      } else {
        setFeedbackError('AIフィードバックの生成要求に失敗しました')
      }
    } finally {
      setGeneratingFeedback(false)
    }
  }

  const handleSendChat = async () => {
    if (submittingChat || !chatInput.trim()) return
    setChatError('')
    setSubmittingChat(true)

    const lastAiPost = [...posts].reverse().find((p) => p.isAi)
    const parentId = lastAiPost?.id ?? null

    try {
      await getCsrfCookie()
      const res = await api.post(`/records/${recordId}/posts`, {
        content: chatInput,
        parentId,
      })

      const { userPost, aiPost } = res.data.data
      setPosts((prev) => {
        const next = [...prev]
        if (userPost) next.push(userPost)
        if (aiPost) next.push(aiPost)
        return next
      })
      setChatInput('')
      if (aiPost) startPolling()
    } catch (err) {
      if (isApiError(err)) {
        const { status, data } = err.response
        if (status === 422 && data.errors) {
          setChatError(Object.values(data.errors).flat().join(', '))
        } else {
          setChatError(data.message)
        }
      } else {
        setChatError('通信に失敗しました')
      }
    } finally {
      setSubmittingChat(false)
    }
  }

  if (loadingData) return null

  const hasCompletedAi = posts.some(
    (p) => p.isAi && p.aiStatus?.statusName === 'completed',
  )
  const hasPendingAi = posts.some(
    (p) => p.isAi && p.aiStatus &&
      (p.aiStatus.statusName === 'pending' || p.aiStatus.statusName === 'processing'),
  )

  return (
    <div className="flex flex-col gap-8">
      <ErrorAlert message={error} />

      {/* 自己レビュー */}
      <section>
        <h2 className="text-lg font-bold mb-4">自己レビュー</h2>

        {reviews.length > 0 && (
          <div className="space-y-3 mb-6">
            {reviews.map((r) => (
              <div key={r.id} className="rounded-xl border bg-white p-4">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-yellow-400 text-lg">
                    {'★'.repeat(r.evaluation)}
                    {'☆'.repeat(5 - r.evaluation)}
                  </span>
                  <div className="flex gap-2 text-xs">
                    <button
                      type="button"
                      onClick={() => navigate(`/records/${recordId}/reviews/${r.id}/edit`)}
                      className="text-blue-600 underline"
                    >
                      編集
                    </button>
                    <button
                      type="button"
                      onClick={() => handleDeleteReview(r.id)}
                      className="text-red-600 underline"
                    >
                      削除
                    </button>
                  </div>
                </div>
                <p className="text-sm">{r.reviewComment}</p>
              </div>
            ))}
          </div>
        )}

        <details className="rounded-xl border bg-white">
          <summary className="cursor-pointer px-4 py-3 text-sm font-medium text-blue-600">
            新しいレビューを追加
          </summary>
          <div className="border-t px-4 py-4 space-y-4">
            <ErrorAlert message={reviewGeneralError} />

            <div>
              <label className="block text-sm font-medium mb-1">評価</label>
              <div className="flex">
                {[1, 2, 3, 4, 5].map((star) => (
                  <button
                    key={star}
                    type="button"
                    onClick={() => setEvaluation(star)}
                    className={`flex-1 text-4xl ${
                      star <= evaluation
                        ? 'text-yellow-400'
                        : 'text-gray-300 hover:text-yellow-300'
                    }`}
                  >
                    ★
                  </button>
                ))}
              </div>
              {reviewErrors.evaluation?.map((msg, i) => (
                <p key={i} className="mt-1 text-xs text-red-600">{msg}</p>
              ))}
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">自己レビュー</label>
              <textarea
                value={reviewComment}
                onChange={(e) => setReviewComment(e.target.value)}
                placeholder="この支出について振り返りを入力してください（250文字以内）"
                rows={4}
                maxLength={250}
                className="w-full rounded-lg border border-gray-400 bg-white px-3 py-2 text-sm"
              />
              {reviewErrors.reviewComment?.map((msg, i) => (
                <p key={i} className="mt-1 text-xs text-red-600">{msg}</p>
              ))}
            </div>

            <button
              type="button"
              disabled={submittingReview}
              onClick={handleSubmitReview}
              className="w-full rounded-2xl border border-blue-500 py-3 font-bold text-blue-600 hover:bg-blue-50 disabled:opacity-50"
            >
              {submittingReview ? '保存中...' : '保存'}
            </button>
          </div>
        </details>
      </section>

      {/* AI フィードバック */}
      <section>
        <h2 className="text-lg font-bold mb-4">AIフィードバック</h2>

        {!hasCompletedAi && !hasPendingAi && (
          <div className="mb-4">
            {feedbackError && <p className="mb-2 text-xs text-red-600">{feedbackError}</p>}
            <button
              type="button"
              disabled={generatingFeedback}
              onClick={handleRequestFeedback}
              className="w-full rounded-2xl bg-lime-300 py-3 font-bold hover:brightness-95 disabled:opacity-50"
            >
              {generatingFeedback ? '要求中...' : 'AIフィードバックを生成'}
            </button>
          </div>
        )}

        <div className="flex min-h-[300px] flex-col rounded-2xl border bg-white shadow">
          <div className="flex-1 overflow-y-auto space-y-4 p-4">
            {posts.length === 0 && (
              <div className="py-10 text-center text-sm text-gray-400">
                AIフィードバックはまだありません
              </div>
            )}
            {posts.map((post) => (
              <PostBubble key={post.id} post={post} onRetry={handleRequestFeedback} />
            ))}
          </div>

          {hasCompletedAi && (
            <div className="border-t p-3 flex flex-col gap-2">
              {chatError && <p className="text-xs text-red-600">{chatError}</p>}
              <textarea
                value={chatInput}
                onChange={(e) => {
                  setChatInput(e.target.value)
                  e.target.style.height = 'auto'
                  e.target.style.height = `${e.target.scrollHeight}px`
                }}
                placeholder="メッセージを入力"
                rows={1}
                maxLength={3000}
                className="w-full resize-none rounded-xl border px-4 py-2 outline-none focus:border-blue-500"
              />
              <button
                type="button"
                onClick={handleSendChat}
                disabled={submittingChat || !chatInput.trim()}
                className="self-end rounded-full bg-blue-500 px-5 py-2 text-white disabled:opacity-50"
              >
                {submittingChat ? '送信中...' : '送信'}
              </button>
            </div>
          )}
        </div>
      </section>
    </div>
  )
}

function PostBubble({ post, onRetry }: { post: Post; onRetry: () => void }) {
  if (post.isAi) {
    const status = post.aiStatus?.statusName
    if (status === 'pending' || status === 'processing') {
      return (
        <div className="flex justify-start">
          <div className="max-w-[75%] rounded-2xl bg-gray-100 px-4 py-3 text-sm text-gray-500">
            AIが考え中です...
          </div>
        </div>
      )
    }
    if (status === 'failed') {
      return (
        <div className="flex justify-start">
          <div className="max-w-[75%] rounded-2xl bg-red-50 px-4 py-3">
            <p className="text-sm text-red-600">生成に失敗しました</p>
            <button
              type="button"
              onClick={onRetry}
              className="mt-1 text-xs text-blue-600 underline"
            >
              再試行
            </button>
          </div>
        </div>
      )
    }
    return (
      <div className="flex justify-start">
        <div className="max-w-[75%] rounded-2xl bg-gray-100 px-4 py-3 text-sm whitespace-pre-wrap">
          {post.content}
        </div>
      </div>
    )
  }

  return (
    <div className="flex justify-end">
      <div className="max-w-[75%] rounded-2xl bg-blue-200 px-4 py-3 text-sm whitespace-pre-wrap">
        {post.content}
      </div>
    </div>
  )
}

function DetailRow({
  label,
  value,
  bold,
}: {
  label: string
  value: string
  bold?: boolean
}) {
  return (
    <div className="flex items-start">
      <p className="w-28 shrink-0 text-sm font-medium">{label}</p>
      <p className={bold ? 'text-lg font-bold' : 'text-sm'}>{value}</p>
    </div>
  )
}
