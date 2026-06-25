import PrimaryButton from './PrimaryButton'

export default function BackButton({ className = '' }: { className?: string }) {
  return <PrimaryButton to="/" label="ホームに戻る" className={className} />
}
