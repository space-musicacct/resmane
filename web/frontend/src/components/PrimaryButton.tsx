import { Link } from 'react-router-dom'

export default function PrimaryButton({
  label,
  to,
  submitting,
  submittingLabel,
  onClick,
  className = '',
}: {
  label: string
  to?: string
  submitting?: boolean
  submittingLabel?: string
  onClick?: () => void
  className?: string
}) {
  const baseClass = `block w-full rounded-2xl bg-lime-300 py-4 text-center text-xl font-bold hover:brightness-95 active:brightness-90 ${className}`

  if (to) {
    return (
      <Link to={to} className={baseClass}>
        {label}
      </Link>
    )
  }

  return (
    <button
      onClick={onClick}
      disabled={submitting}
      className={`${baseClass} disabled:opacity-50`}
    >
      {submitting ? (submittingLabel ?? `${label}中...`) : label}
    </button>
  )
}
