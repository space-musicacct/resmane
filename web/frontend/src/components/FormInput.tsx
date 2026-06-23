const inputClass = 'w-full rounded-lg border border-gray-400 bg-white px-3 py-2 text-sm'

function Label({ text, required }: { text: string; required?: boolean }) {
  return (
    <label className="block text-sm font-medium mb-1">
      {text}
      {required && <span className="ml-[3px] text-red-500">*</span>}
    </label>
  )
}

function FieldErrors({ messages }: { messages?: string[] }) {
  if (!messages?.length) return null
  return (
    <>
      {messages.map((msg, i) => (
        <p key={i} className="mt-1 text-xs text-red-600">{msg}</p>
      ))}
    </>
  )
}

export function FormInput({
  label,
  type = 'text',
  value,
  onChange,
  required,
  errors,
  placeholder,
  maxLength,
}: {
  label: string
  type?: string
  value: string
  onChange: (value: string) => void
  required?: boolean
  errors?: string[]
  placeholder?: string
  maxLength?: number
}) {
  return (
    <div>
      <Label text={label} required={required} />
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        maxLength={maxLength}
        className={inputClass}
      />
      <FieldErrors messages={errors} />
    </div>
  )
}

export function FormSelect({
  label,
  value,
  onChange,
  required,
  errors,
  options,
  placeholder = '選択してください',
}: {
  label: string
  value: string
  onChange: (value: string) => void
  required?: boolean
  errors?: string[]
  options: { id: number; name: string }[]
  placeholder?: string
}) {
  return (
    <div>
      <Label text={label} required={required} />
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className={inputClass}
      >
        <option value="">{placeholder}</option>
        {options.map((opt) => (
          <option key={opt.id} value={opt.id}>{opt.name}</option>
        ))}
      </select>
      <FieldErrors messages={errors} />
    </div>
  )
}

export function FormTextarea({
  label,
  value,
  onChange,
  required,
  errors,
  placeholder,
  rows,
}: {
  label: string
  value: string
  onChange: (value: string) => void
  required?: boolean
  errors?: string[]
  placeholder?: string
  rows?: number
}) {
  return (
    <div>
      <Label text={label} required={required} />
      <textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        rows={rows}
        className={`${inputClass} h-28 resize-none`}
      />
      <FieldErrors messages={errors} />
    </div>
  )
}
