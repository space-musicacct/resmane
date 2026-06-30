import type { ReactNode } from 'react'

export type Tab<T extends string> = {
  key: T
  label: ReactNode
}

export default function TabSwitcher<T extends string>({
  tabs,
  activeTab,
  onChange,
  className = '',
}: {
  tabs: Tab<T>[]
  activeTab: T
  onChange: (key: T) => void
  className?: string
}) {
  return (
    <div className={`flex rounded-2xl bg-white p-1 ${className}`}>
      {tabs.map((tab) => (
        <button
          key={tab.key}
          onClick={() => onChange(tab.key)}
          className={`flex-1 rounded-xl py-2 text-sm font-bold transition-colors ${
            activeTab === tab.key
              ? 'bg-lime-300 text-black'
              : 'text-gray-500 hover:text-gray-700'
          }`}
        >
          {tab.label}
        </button>
      ))}
    </div>
  )
}
