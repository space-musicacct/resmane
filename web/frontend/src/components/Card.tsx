export const cardClass = 'rounded-xl border border-gray-300 bg-white px-4 py-3'

export default function Card({
  children,
  className = '',
}: {
  children: React.ReactNode
  className?: string
}) {
  return (
    <div className={`${cardClass} ${className}`}>
      {children}
    </div>
  )
}
