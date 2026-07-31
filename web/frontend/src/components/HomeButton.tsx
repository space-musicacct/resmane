import PrimaryButton from './PrimaryButton'

export default function HomeButton({ className = '' }: { className?: string }) {
  return <PrimaryButton to="/home" label="ホームに戻る" className={className} />
}
