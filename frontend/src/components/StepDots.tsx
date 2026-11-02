type StepDotsProps = {
  step: number
  label: string
  total?: number
}

export default function StepDots({ step, label, total = 4 }: StepDotsProps) {
  return (
    <div className="welcome__dots" role="img" aria-label={label}>
      {Array.from({ length: total }, (_, index) => (
        <span key={index} className={index + 1 === step ? 'dot dot--active' : 'dot'} />
      ))}
    </div>
  )
}
