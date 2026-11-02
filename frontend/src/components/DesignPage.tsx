import FigmaGlyph from './FigmaGlyph.tsx'
import type { ReactNode, FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { frameAssets } from './frameAssets.ts'

export function FrameIcon({
  frame,
  name,
  className = '',
}: {
  frame: string
  name: string
  className?: string
}) {
  const source = frameAssets[frame]?.[name]
  return source ? (
    <img
      className={`design-icon ${className}`}
      src={source}
      alt=""
      data-figma-asset={`${frame}/${name}`}
    />
  ) : null
}

export function DesignPage({
  title,
  subtitle,
  children,
  footer,
  household = false,
  className = '',
  frame,
}: {
  title: ReactNode
  subtitle?: ReactNode
  children: ReactNode
  footer?: ReactNode
  household?: boolean
  className?: string
  frame: string
}) {
  return (
    <section
      className={`auth-page design-page ${household ? 'design-page--household' : ''} ${className}`}
      data-figma-frame={frame}
    >
      <header className="design-page__head">
        <h1>{title}</h1>
        {subtitle && <p>{subtitle}</p>}
      </header>
      <div className="design-page__body">{children}</div>
      {footer && <footer className="design-page__foot">{footer}</footer>}
    </section>
  )
}

export function DesignRow({
  title,
  detail,
  icon,
  to,
  onClick,
  selected,
  disabled = false,
}: {
  title: ReactNode
  detail?: ReactNode
  icon?: ReactNode
  to?: string
  onClick?: () => void
  selected?: boolean
  disabled?: boolean
}) {
  const content = (
    <>
      {icon && <span className="design-row__icon">{icon}</span>}
      <span className="design-row__copy">
        <strong>{title}</strong>
        {detail && <small>{detail}</small>}
      </span>
      {(to || onClick) && (
        <span className="design-row__arrow" aria-hidden="true">
          {selected ? (
            <FigmaGlyph name="roomCheck" />
          ) : (
            <FrameIcon frame="165:8" name="imgIconArrow" />
          )}
        </span>
      )}
    </>
  )
  const className = `design-row ${selected ? 'design-row--selected' : ''}`
  return to ? (
    <Link className={className} to={to}>
      {content}
    </Link>
  ) : onClick ? (
    <button
      type="button"
      className={className}
      onClick={onClick}
      disabled={disabled}
      aria-pressed={selected}
    >
      {content}
    </button>
  ) : (
    <div className={className}>{content}</div>
  )
}

export function DesignTile({
  title,
  detail,
  icon,
  to,
  onClick,
  selected,
}: {
  title: string
  detail?: string
  icon: ReactNode
  to?: string
  onClick?: () => void
  selected?: boolean
}) {
  const content = (
    <>
      <span className="design-tile__image">{icon}</span>
      <strong>{title}</strong>
      {detail && <small>{detail}</small>}
    </>
  )
  return to ? (
    <Link className="design-tile" to={to}>
      {content}
    </Link>
  ) : (
    <button
      type="button"
      className={`design-tile ${selected ? 'design-tile--selected' : ''}`}
      onClick={onClick}
      aria-pressed={selected}
    >
      {content}
    </button>
  )
}

export function DesignField({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="design-field">
      <span>{label}</span>
      {children}
    </label>
  )
}

export function DesignForm({
  children,
  onSubmit,
  id,
}: {
  children: ReactNode
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
  id?: string
}) {
  return (
    <form id={id} className="design-form" onSubmit={onSubmit}>
      {children}
    </form>
  )
}

export function DesignNotice({ children }: { children: ReactNode }) {
  return <aside className="design-notice">{children}</aside>
}

export function ErrorMessage({ error }: { error: string | null }) {
  return error ? (
    <p role="alert" className="form-error">
      {error}
    </p>
  ) : null
}
