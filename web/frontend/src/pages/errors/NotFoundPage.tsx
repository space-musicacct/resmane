import { Link } from 'react-router-dom'
import PageCard from '../../components/PageCard'

export default function NotFoundPage() {
  return (
    <PageCard title="404">
      <div className="text-center space-y-4">
        <p className="text-lg font-medium">ページが見つかりません</p>
        <p className="text-sm text-gray-600">
          お探しのページは存在しないか、削除された可能性があります。
        </p>
        <Link
          to="/"
          className="mt-6 inline-block rounded-2xl bg-[#A6E01A] px-8 py-3 font-bold hover:brightness-95"
        >
          ホームに戻る
        </Link>
      </div>
    </PageCard>
  )
}
