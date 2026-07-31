import { useAuth } from '../auth/AuthContext'
import LogoutButton from './LogoutButton'

export default function PageCard({
  title,
  children,
}: {
  title: string
  children: React.ReactNode
}) {
  const { user } = useAuth()

  return (
    <div className="min-h-screen bg-yellow-400 flex justify-center px-4 pt-10 pb-28">
      <div className="w-full max-w-[390px] bg-[#F4F1F1] rounded-[40px] px-8 py-10">
        {user && (
          <div className="flex justify-end -mt-2 mb-4">
            <LogoutButton />
          </div>
        )}
        <h1 className="text-3xl font-bold text-center mb-8">{title}</h1>
        {children}
      </div>
    </div>
  )
}
