export default function ErrorAlert({ message }: { message: string }) {
  if (!message) return null
  return (
    <p className="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">
      {message}
    </p>
  )
}
