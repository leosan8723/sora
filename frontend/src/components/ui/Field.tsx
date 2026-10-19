import type {
  InputHTMLAttributes,
  ReactNode,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
} from 'react'

interface FieldShellProps {
  id: string
  label: string
  error?: string
  hint?: string
  children: ReactNode
}

function FieldShell({ id, label, error, hint, children }: FieldShellProps) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm text-ink-70">
        {label}
      </label>
      {children}
      {error ? (
        <p className="text-sm text-danger">{error}</p>
      ) : hint ? (
        <p className="text-sm text-ink-50">{hint}</p>
      ) : null}
    </div>
  )
}

const CONTROL_BASE =
  'w-full rounded-panel border bg-surface-soft px-3.5 text-ink transition-[border-color,background-color] duration-200 placeholder:text-ink-30 focus:border-accent/50 focus:bg-surface focus:outline-none'

function controlClasses(error?: string): string {
  return `${CONTROL_BASE} ${error ? 'border-danger/60' : 'border-hairline'}`
}

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string
  error?: string
  hint?: string
}

export function TextField({
  label,
  error,
  hint,
  id,
  name,
  className,
  ...rest
}: TextFieldProps) {
  const fieldId = id ?? name ?? label

  return (
    <FieldShell id={fieldId} label={label} error={error} hint={hint}>
      <input
        id={fieldId}
        name={name}
        aria-invalid={error ? true : undefined}
        className={`${controlClasses(error)} h-11 ${className ?? ''}`}
        {...rest}
      />
    </FieldShell>
  )
}

interface TextAreaFieldProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label: string
  error?: string
  hint?: string
}

export function TextAreaField({
  label,
  error,
  hint,
  id,
  name,
  className,
  ...rest
}: TextAreaFieldProps) {
  const fieldId = id ?? name ?? label

  return (
    <FieldShell id={fieldId} label={label} error={error} hint={hint}>
      <textarea
        id={fieldId}
        name={name}
        aria-invalid={error ? true : undefined}
        className={`${controlClasses(error)} min-h-28 py-2 ${className ?? ''}`}
        {...rest}
      />
    </FieldShell>
  )
}

interface SelectFieldProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label: string
  error?: string
  hint?: string
}

export function SelectField({
  label,
  error,
  hint,
  id,
  name,
  className,
  children,
  ...rest
}: SelectFieldProps) {
  const fieldId = id ?? name ?? label

  return (
    <FieldShell id={fieldId} label={label} error={error} hint={hint}>
      <select
        id={fieldId}
        name={name}
        aria-invalid={error ? true : undefined}
        className={`${controlClasses(error)} h-11 ${className ?? ''}`}
        {...rest}
      >
        {children}
      </select>
    </FieldShell>
  )
}
