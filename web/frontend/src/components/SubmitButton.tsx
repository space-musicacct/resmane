export default function SubmitButton({
  label,
  submitting,
  submittingLabel,
  onClick,
}: {
  label: string
  submitting?: boolean
  submittingLabel?: string
  onClick: () => void
}) {
  return (
    <button
      onClick={onClick}
      disabled={submitting}
      className="mt-10 block w-full rounded-2xl bg-[#A6E01A] py-4 text-2xl font-bold hover:brightness-95 active:brightness-90 disabled:opacity-50"
    >
      {submitting ? (submittingLabel ?? `${label}中...`) : label}
    </button>
  )
}
