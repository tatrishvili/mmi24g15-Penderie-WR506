import { axe, toHaveNoViolations } from 'jest-axe'

/**
 * Runs axe-core against a rendered element and throws on every accessibility
 * violation (labels, roles, accessible names, keyboard structure, ...).
 *
 * jest-axe already disables the colour-contrast rule: jsdom has no layout
 * engine, so contrast is verified by Lighthouse instead (see .lighthouserc.cjs).
 */
export async function expectNoA11yViolations(element: Element): Promise<void> {
  const results = await axe(element)
  const { pass, message } = toHaveNoViolations.toHaveNoViolations(results)

  if (!pass) {
    throw new Error(message())
  }
}
