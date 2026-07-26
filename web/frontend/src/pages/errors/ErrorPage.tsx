import PageCard from '../../components/PageCard'
import HomeButton from '../../components/HomeButton'

const errors: Record<string, { title: string; message: string }> = {
  '403': { title: 'アクセス権限がありません', message: 'このページにアクセスする権限がありません。' },
  '404': { title: 'ページが見つかりません', message: 'お探しのページは存在しないか、削除された可能性があります。' },
  '500': { title: 'サーバーエラーが発生しました', message: 'しばらく時間をおいてから再度お試しください。' },
}

export default function ErrorPage({ statusCode }: { statusCode: string }) {
  const { title, message } = errors[statusCode] ?? errors['404']

  return (
    <PageCard title={statusCode}>
      <div className="text-center space-y-4">
        <p className="text-lg font-medium">{title}</p>
        <p className="text-sm text-gray-600">{message}</p>
        <HomeButton className="mt-6" />
      </div>
    </PageCard>
  )
}
