import { Link } from 'react-router-dom'
import PageCard from '../../components/PageCard'

export default function ForbiddenPage() {
  return (
    <PageCard title="403">
      <div className="text-center space-y-4">
        <p className="text-lg font-medium">アクセス権限がありません</p>
        <p className="text-sm text-gray-600">
          このページにアクセスする権限がありません。
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
