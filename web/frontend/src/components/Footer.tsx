import { Link, useLocation } from 'react-router-dom'
import {
  IoHomeOutline,
  IoAdd,
  IoListOutline,
} from 'react-icons/io5'

export default function Footer() {
  const location = useLocation()

  const isActive = (path: string) => location.pathname === path

  return (
    <footer className="fixed bottom-0 left-0 z-50 w-full bg-white border-t">
      <nav className="relative flex items-center justify-around h-16">
        <Link
          to="/"
          className={`flex flex-col items-center text-xs ${
            isActive('/') ? 'text-yellow-500' : 'text-gray-500'
          }`}
        >
          <IoHomeOutline className="text-2xl" />
          <span>ホーム</span>
        </Link>

        <Link
          to="/records/new"
          className="absolute -top-6 left-1/2 -translate-x-1/2 flex flex-col items-center"
        >
          <div className="flex items-center justify-center w-16 h-16 rounded-full bg-yellow-400 shadow-lg">
            <IoAdd className="text-4xl text-white" />
          </div>
          <span className="mt-1 text-xs text-gray-600">記録追加</span>
        </Link>

        <Link
          to="/records"
          className={`flex flex-col items-center text-xs ${
            isActive('/records') ? 'text-yellow-500' : 'text-gray-500'
          }`}
        >
          <IoListOutline className="text-2xl" />
          <span>記録一覧</span>
        </Link>
      </nav>
    </footer>
  )
}